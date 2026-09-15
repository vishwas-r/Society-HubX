<?php
/**
 * Tally ERP 9 / Tally Prime Accounting Export Bridge.
 * Generates standards-compliant Tally XML for Ledgers, Sales Invoices, and Payment Receipts.
 *
 * @package NAMMASOCIETY51_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NAMMASOCIETY51_Tally_Exporter {

	/**
	 * Generate and stream Tally XML export download.
	 *
	 * @param string $month Month in YYYY-MM format, or empty for all.
	 * @param string $export_type 'all', 'invoices', or 'receipts'.
	 */
	public static function export_xml( $month = '', $export_type = 'all' ) {
		$xml = self::build_tally_xml( $month, $export_type );
		$filename = 'tally_export_' . ( $month ? sanitize_file_name( $month ) : 'all' ) . '_' . gmdate( 'Ymd_His' ) . '.xml';

		// Clean any previous output buffer
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		header( 'Content-Type: application/xml; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated XML string for file download.
		exit;
	}

	/**
	 * Build complete Tally XML document.
	 *
	 * @param string $month
	 * @param string $export_type
	 * @return string
	 */
	public static function build_tally_xml( $month = '', $export_type = 'all' ): string {
		$db = new NAMMASOCIETY51_DB_Router();
		$company_name = get_option( 'nammasociety51_society_name', 'Housing Society' );
		$bank_name = get_option( 'nammasociety51_bank_name', 'Bank Account' );

		// Query Invoices
		$inv_where = array();
		if ( ! empty( $month ) ) {
			$inv_where['month'] = $month;
		}
		$invoices = $db->get( 'invoices', ! empty( $inv_where ) ? array( 'where' => $inv_where ) : array() );

		// Query Payments
		$payments = $db->get( 'payments' );
		if ( ! empty( $month ) ) {
			$payments = array_filter( $payments, function( $p ) use ( $month ) {
				return isset( $p['date'] ) && strpos( $p['date'], $month ) === 0;
			} );
		}

		// Build unique resident debtor ledgers
		$debtor_ledgers = array();
		foreach ( $invoices as $inv ) {
			$flat = $inv['flat_no'] ?? '';
			$block = $inv['block'] ?? '';
			$resident = $inv['resident_name'] ?? 'Resident';
			$ledger_name = trim( ( $block ? $block . '-' : '' ) . $flat . ' (' . $resident . ')' );
			$debtor_ledgers[ $flat ] = $ledger_name;
		}

		$xml = array();
		$xml[] = '<ENVELOPE>';
		$xml[] = '  <HEADER>';
		$xml[] = '    <TALLYREQUEST>Import Data</TALLYREQUEST>';
		$xml[] = '  </HEADER>';
		$xml[] = '  <BODY>';
		$xml[] = '    <IMPORTDATA>';
		$xml[] = '      <REQUESTDESC>';
		$xml[] = '        <REPORTNAME>All Masters and Vouchers</REPORTNAME>';
		$xml[] = '        <STATICVARIABLES>';
		$xml[] = '          <SVCURRENTCOMPANY>' . esc_html( $company_name ) . '</SVCURRENTCOMPANY>';
		$xml[] = '        </STATICVARIABLES>';
		$xml[] = '      </REQUESTDESC>';
		$xml[] = '      <REQUESTDATA>';

		// 1. Master Ledgers: Debtor Flats
		foreach ( $debtor_ledgers as $flat_no => $ledger_title ) {
			$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
			$xml[] = '          <LEDGER NAME="' . esc_attr( $ledger_title ) . '" ACTION="Create">';
			$xml[] = '            <NAME>' . esc_html( $ledger_title ) . '</NAME>';
			$xml[] = '            <PARENT>Sundry Debtors</PARENT>';
			$xml[] = '            <ISBILLWISEON>Yes</ISBILLWISEON>';
			$xml[] = '            <OPENINGBALANCE>0</OPENINGBALANCE>';
			$xml[] = '          </LEDGER>';
			$xml[] = '        </TALLYMESSAGE>';
		}

		// 2. Master Ledgers: General Incomes, GST Heads, and Bank
		$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
		$xml[] = '          <LEDGER NAME="Society Maintenance Charges" ACTION="Create">';
		$xml[] = '            <NAME>Society Maintenance Charges</NAME>';
		$xml[] = '            <PARENT>Direct Incomes</PARENT>';
		$xml[] = '            <HSNCODE>999598</HSNCODE>';
		$xml[] = '          </LEDGER>';
		$xml[] = '        </TALLYMESSAGE>';

		$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
		$xml[] = '          <LEDGER NAME="CGST Output (9%)" ACTION="Create">';
		$xml[] = '            <NAME>CGST Output (9%)</NAME>';
		$xml[] = '            <PARENT>Duties &amp; Taxes</PARENT>';
		$xml[] = '            <TAXTYPE>GST</TAXTYPE>';
		$xml[] = '            <GSTDUTYHEAD>Central Tax</GSTDUTYHEAD>';
		$xml[] = '            <RATEOFTAXCALCULATION>9</RATEOFTAXCALCULATION>';
		$xml[] = '          </LEDGER>';
		$xml[] = '        </TALLYMESSAGE>';

		$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
		$xml[] = '          <LEDGER NAME="SGST Output (9%)" ACTION="Create">';
		$xml[] = '            <NAME>SGST Output (9%)</NAME>';
		$xml[] = '            <PARENT>Duties &amp; Taxes</PARENT>';
		$xml[] = '            <TAXTYPE>GST</TAXTYPE>';
		$xml[] = '            <GSTDUTYHEAD>State Tax</GSTDUTYHEAD>';
		$xml[] = '            <RATEOFTAXCALCULATION>9</RATEOFTAXCALCULATION>';
		$xml[] = '          </LEDGER>';
		$xml[] = '        </TALLYMESSAGE>';

		$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
		$xml[] = '          <LEDGER NAME="' . esc_attr( $bank_name ) . '" ACTION="Create">';
		$xml[] = '            <NAME>' . esc_html( $bank_name ) . '</NAME>';
		$xml[] = '            <PARENT>Bank Accounts</PARENT>';
		$xml[] = '          </LEDGER>';
		$xml[] = '        </TALLYMESSAGE>';

		$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
		$xml[] = '          <LEDGER NAME="Cash" ACTION="Create">';
		$xml[] = '            <NAME>Cash</NAME>';
		$xml[] = '            <PARENT>Cash-in-Hand</PARENT>';
		$xml[] = '          </LEDGER>';
		$xml[] = '        </TALLYMESSAGE>';

		// 3. Vouchers: Invoices (Sales Vouchers with Statutory GST Breakdown)
		if ( $export_type === 'all' || $export_type === 'invoices' ) {
			foreach ( $invoices as $inv ) {
				$inv_id = $inv['id'] ?? uniqid( 'inv_' );
				$flat_no = $inv['flat_no'] ?? '';
				$debtor_name = $debtor_ledgers[ $flat_no ] ?? ( 'Flat ' . $flat_no );
				$total_amount = floatval( $inv['amount'] ?? 0 );
				$base_amount = floatval( $inv['base_amount'] ?? $total_amount );
				$cgst_amount = floatval( $inv['cgst_amount'] ?? 0 );
				$sgst_amount = floatval( $inv['sgst_amount'] ?? 0 );
				$date_str = ! empty( $inv['created_at'] ) ? date( 'Ymd', strtotime( $inv['created_at'] ) ) : date( 'Ymd' );
				$narration = $inv['description'] ?? ( 'Maintenance invoice ' . $inv_id . ' SAC: 999598' );

				$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
				$xml[] = '          <VOUCHER VCHTYPE="Sales" ACTION="Create">';
				$xml[] = '            <DATE>' . esc_html( $date_str ) . '</DATE>';
				$xml[] = '            <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>';
				$xml[] = '            <VOUCHERNUMBER>' . esc_html( $inv_id ) . '</VOUCHERNUMBER>';
				$xml[] = '            <NARRATION>' . esc_html( $narration ) . '</NARRATION>';
				$xml[] = '            <ALLLEDGERENTRIES.LIST>';
				$xml[] = '              <LEDGERNAME>' . esc_html( $debtor_name ) . '</LEDGERNAME>';
				$xml[] = '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>';
				$xml[] = '              <AMOUNT>-' . number_format( $total_amount, 2, '.', '' ) . '</AMOUNT>';
				$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				$xml[] = '            <ALLLEDGERENTRIES.LIST>';
				$xml[] = '              <LEDGERNAME>Society Maintenance Charges</LEDGERNAME>';
				$xml[] = '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>';
				$xml[] = '              <AMOUNT>' . number_format( $base_amount, 2, '.', '' ) . '</AMOUNT>';
				$xml[] = '            </ALLLEDGERENTRIES.LIST>';

				if ( $cgst_amount > 0 ) {
					$xml[] = '            <ALLLEDGERENTRIES.LIST>';
					$xml[] = '              <LEDGERNAME>CGST Output (9%)</LEDGERNAME>';
					$xml[] = '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>';
					$xml[] = '              <AMOUNT>' . number_format( $cgst_amount, 2, '.', '' ) . '</AMOUNT>';
					$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				}
				if ( $sgst_amount > 0 ) {
					$xml[] = '            <ALLLEDGERENTRIES.LIST>';
					$xml[] = '              <LEDGERNAME>SGST Output (9%)</LEDGERNAME>';
					$xml[] = '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>';
					$xml[] = '              <AMOUNT>' . number_format( $sgst_amount, 2, '.', '' ) . '</AMOUNT>';
					$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				}

				$xml[] = '          </VOUCHER>';
				$xml[] = '        </TALLYMESSAGE>';
			}
		}

		// 4. Vouchers: Payment Receipts (Receipt Vouchers)
		if ( $export_type === 'all' || $export_type === 'receipts' ) {
			// Map invoices by id for quick lookup
			$inv_map = array();
			foreach ( $invoices as $i ) {
				$inv_map[ $i['id'] ] = $i;
			}

			foreach ( $payments as $p ) {
				$pay_id = $p['id'] ?? uniqid( 'pay_' );
				$inv_id = $p['invoice_id'] ?? '';
				$inv_obj = $inv_map[ $inv_id ] ?? null;
				$flat_no = $inv_obj ? ( $inv_obj['flat_no'] ?? '' ) : '';
				$debtor_name = $debtor_ledgers[ $flat_no ] ?? ( $flat_no ? 'Flat ' . $flat_no : 'Resident Payment' );

				$method = strtolower( $p['method'] ?? 'bank' );
				$target_account = ( $method === 'cash' ) ? 'Cash' : $bank_name;
				$amount = floatval( $p['amount'] ?? 0 );
				$date_str = ! empty( $p['date'] ) ? date( 'Ymd', strtotime( $p['date'] ) ) : date( 'Ymd' );
				$ref = $p['reference'] ?? '-';
				$narration = sprintf( 'Payment received for %s via %s (Ref: %s)', $debtor_name, strtoupper( $p['method'] ?? 'ONLINE' ), $ref );

				$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
				$xml[] = '          <VOUCHER VCHTYPE="Receipt" ACTION="Create">';
				$xml[] = '            <DATE>' . esc_html( $date_str ) . '</DATE>';
				$xml[] = '            <VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME>';
				$xml[] = '            <VOUCHERNUMBER>' . esc_html( $pay_id ) . '</VOUCHERNUMBER>';
				$xml[] = '            <NARRATION>' . esc_html( $narration ) . '</NARRATION>';
				$xml[] = '            <ALLLEDGERENTRIES.LIST>';
				$xml[] = '              <LEDGERNAME>' . esc_html( $target_account ) . '</LEDGERNAME>';
				$xml[] = '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>';
				$xml[] = '              <AMOUNT>-' . number_format( $amount, 2, '.', '' ) . '</AMOUNT>';
				$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				$xml[] = '            <ALLLEDGERENTRIES.LIST>';
				$xml[] = '              <LEDGERNAME>' . esc_html( $debtor_name ) . '</LEDGERNAME>';
				$xml[] = '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>';
				$xml[] = '              <AMOUNT>' . number_format( $amount, 2, '.', '' ) . '</AMOUNT>';
				$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				$xml[] = '          </VOUCHER>';
				$xml[] = '        </TALLYMESSAGE>';
			}
		}

		// 5. Vouchers: Approved Expenses (Payment Vouchers)
		if ( $export_type === 'all' || $export_type === 'expenses' ) {
			$expenses = $db->get( 'expenses' );
			if ( ! empty( $month ) ) {
				$expenses = array_filter( $expenses, function( $ex ) use ( $month ) {
					return isset( $ex['date'] ) && strpos( $ex['date'], $month ) === 0;
				} );
			}

			foreach ( $expenses as $ex ) {
				if ( ( $ex['status'] ?? '' ) !== 'approved' ) {
					continue;
				}

				$exp_id = $ex['id'] ?? uniqid( 'exp_' );
				$amount = floatval( $ex['amount'] ?? 0 );
				$category = ucwords( str_replace( '_', ' ', $ex['category'] ?? 'Repairs' ) );
				$method = strtolower( $ex['account_type'] ?? 'bank' );
				$target_source = ( $method === 'cash' ) ? 'Cash' : $bank_name;
				$date_str = ! empty( $ex['date'] ) ? date( 'Ymd', strtotime( $ex['date'] ) ) : date( 'Ymd' );
				$narration = sprintf( 'Expense: %s - Payee: %s', $ex['description'] ?? '', $ex['payee'] ?? 'Vendor' );

				$xml[] = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">';
				$xml[] = '          <VOUCHER VCHTYPE="Payment" ACTION="Create">';
				$xml[] = '            <DATE>' . esc_html( $date_str ) . '</DATE>';
				$xml[] = '            <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>';
				$xml[] = '            <VOUCHERNUMBER>' . esc_html( $exp_id ) . '</VOUCHERNUMBER>';
				$xml[] = '            <NARRATION>' . esc_html( $narration ) . '</NARRATION>';
				$xml[] = '            <ALLLEDGERENTRIES.LIST>';
				$xml[] = '              <LEDGERNAME>' . esc_html( $category ) . '</LEDGERNAME>';
				$xml[] = '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>';
				$xml[] = '              <AMOUNT>-' . number_format( $amount, 2, '.', '' ) . '</AMOUNT>';
				$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				$xml[] = '            <ALLLEDGERENTRIES.LIST>';
				$xml[] = '              <LEDGERNAME>' . esc_html( $target_source ) . '</LEDGERNAME>';
				$xml[] = '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>';
				$xml[] = '              <AMOUNT>' . number_format( $amount, 2, '.', '' ) . '</AMOUNT>';
				$xml[] = '            </ALLLEDGERENTRIES.LIST>';
				$xml[] = '          </VOUCHER>';
				$xml[] = '        </TALLYMESSAGE>';
			}
		}

		$xml[] = '      </REQUESTDATA>';
		$xml[] = '    </IMPORTDATA>';
		$xml[] = '  </BODY>';
		$xml[] = '</ENVELOPE>';

		return implode( "\n", $xml );
	}
}

