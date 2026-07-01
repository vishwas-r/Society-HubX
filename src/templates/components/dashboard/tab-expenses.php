<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Expenses Tab
 * @var array $data Dashboard data.
 */
?>
<!-- 5. SOCIETY FINANCE (EXPENSES) TAB -->
<div id="tab-expenses" class="tab-content d-none">
      <!-- Financial Overview Row: Funds Card (5) + Chart (7) -->
      <div class="row g-4 mb-4 align-items-stretch">
          <!-- Society Funds Card -->
          <div class="col-md-5">
              <div class="card border-0 shadow-sm text-white rounded-3 h-100" style="background: #1e293b; min-height: 250px;">
                  <div class="card-body p-4 d-flex flex-column justify-content-between">
                       <div>
                           <p class="text-secondary small fw-bold text-uppercase mb-1" style="color: #94a3b8 !important;">Total Society Funds</p>
                           <h2 class="display-6 fw-bold mb-0">₹<?php echo SNESTX_in_fmt($data['current_balance']['total'] ?? 0); ?></h2>
                       </div>
                       
                       <div class="pt-3 border-top border-secondary">
                            <div class="row">
                                 <div class="col-6">
                                     <div class="small text-secondary fw-bold text-uppercase" style="color: #64748b !important; font-size: 10px;">Bank</div>
                                     <div class="fw-bold text-primary">₹<?php echo SNESTX_in_fmt($data['current_balance']['bank'] ?? 0); ?></div>
                                 </div>
                                 <div class="col-6 border-start border-secondary">
                                     <div class="small text-secondary fw-bold text-uppercase" style="color: #64748b !important; font-size: 10px;">Cash</div>
                                     <div class="fw-bold text-warning">₹<?php echo SNESTX_in_fmt($data['current_balance']['cash'] ?? 0); ?></div>
                                 </div>
                            </div>
                       </div>
                  </div>
              </div>
          </div>

          <!-- Society Expense Trend Visualization -->
          <div class="col-md-7">
              <div class="bg-white rounded-3 shadow-sm border border-light p-4 h-100" style="min-height: 250px;">
                  <h6 class="fw-bold text-dark mb-3 small text-uppercase">Monthly Expense Trend</h6>
                  <div id="expensesChart" style="height: 200px; width: 100%;"></div>
              </div>
          </div>
      </div>

      <!-- Tabs for Expenses Sub-views -->
      <ul class="nav nav-tabs mb-3 border-light">
          <li class="nav-item">
              <button class="nav-link active fw-bold text-primary border-0 bg-transparent" data-subtab-target="fin-maintenance">Maintenance Status</button>
          </li>
          <li class="nav-item">
              <button class="nav-link fw-bold text-secondary border-0 bg-transparent" data-subtab-target="fin-expenses">Expenses List</button>
          </li>
      </ul>
      
      <!-- Sub-tab 1: Maintenance Status -->
      <div id="sub-tab-fin-maintenance" class="sub-tab-content d-block">
        <div class="bg-white rounded-3 shadow-sm border border-light p-4">
            <h5 class="fw-bold mb-3 small text-uppercase text-secondary">
                Flat Payment Status
            </h5>

            <div class="row g-2">
            <?php foreach(($data['monthly_summary'] ?? []) as $s):  

                $status_type = $s['status_type'] ?? 'danger';

                /* Map status_type to CSS classes */
                $class_map = [
                    'paid'    => ['tooltip' => 'tooltip-success', 'div' => 'div-success'],
                    'warning' => ['tooltip' => 'tooltip-warning', 'div' => 'div-warning'],
                    'danger'  => ['tooltip' => 'tooltip-danger',  'div' => 'div-danger'],
                    'chronic' => ['tooltip' => 'tooltip-chronic', 'div' => 'div-chronic']
                ];

                $ui = $class_map[$status_type] ?? $class_map['danger'];
                $tooltip_class = $ui['tooltip'];
                $div_class = $ui['div'];

                // Tooltip Logic
                if ($status_type === 'paid') {
                    $tooltip = ($s['resident'] ?? 'Unknown Member') . " - No Outstanding Dues (All Months Paid)";
                } else {
                    $unpaid_list = !empty($s['unpaid_months']) ? implode(', ', $s['unpaid_months']) : 'Current';
                    $tooltip = ($s['resident'] ?? 'Unknown Member') . " - Unpaid Months: " . $unpaid_list;
                }
            ?>

                <div class="col-4 col-sm-3 col-md-2 col-lg-1">
                    <div class="<?php echo $div_class; ?> p-2 rounded-2 text-center position-relative d-flex flex-column justify-content-center align-items-center shadow-sm"
                        title="<?php echo esc_attr($tooltip); ?>"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        data-bs-custom-class="<?php echo $tooltip_class; ?>">

                        <div class="fw-bold lh-1" style="font-size: 0.85rem;">
                            <?php echo (!empty($s['block']) ? esc_html($s['block']) . '-' : '') . esc_html($s['flat_no'] ?? 'N/A'); ?>
                        </div>

                        <?php if($status_type === 'paid'): ?>
                            <div class="opacity-75 mt-1" style="font-size: 0.6rem;">
                                <i class="bi bi-check-lg"></i>
                            </div>
                        <?php else: ?>
                            <div class="opacity-75 mt-1 fw-bold" style="font-size: 0.6rem;">
                                DUE
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

            <?php endforeach; ?>
            </div>
        </div>
      </div>

      <style>
        /* Smooth fade for tooltips */
        .tooltip.fade {
            transition: opacity 0.2s ease-in-out;
        }

        .tooltip-inner {
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 10px;
            letter-spacing: 0.3px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
        }

        .tooltip .tooltip-arrow::before {
            display: none;
        }

        .tooltip .tooltip-arrow {
            width: 14px;
            height: 7px;
        }

        .tooltip .tooltip-arrow::after {
            content: "";
            position: absolute;
            width: 14px;
            height: 7px;
            clip-path: polygon(50% 100%, 0 0, 100% 0);
        }

        /* Placement fixes */
        .bs-tooltip-top .tooltip-arrow { bottom: -7px; }
        .bs-tooltip-bottom .tooltip-arrow { top: -7px; }
        .bs-tooltip-bottom .tooltip-arrow::after { transform: rotate(180deg); }
        .bs-tooltip-start .tooltip-arrow { right: -7px; width: 7px; height: 14px; }
        .bs-tooltip-start .tooltip-arrow::after { width: 7px; height: 14px; clip-path: polygon(100% 50%, 0 0, 0 100%); }
        .bs-tooltip-end .tooltip-arrow { left: -7px; width: 7px; height: 14px; }
        .bs-tooltip-end .tooltip-arrow::after { width: 7px; height: 14px; clip-path: polygon(0 50%, 100% 0, 100% 100%); }

        /* Tooltip variants */
        .tooltip-success .tooltip-inner { background: linear-gradient(135deg, #198754, #157347); color: #fff; }
        .tooltip-success .tooltip-arrow::after { background: linear-gradient(135deg, #198754, #157347); }

        .tooltip-warning .tooltip-inner { background: linear-gradient(135deg, #ffc107, #e0a800); color: #212529; }
        .tooltip-warning .tooltip-arrow::after { background: linear-gradient(135deg, #ffc107, #e0a800); }

        .tooltip-danger .tooltip-inner { background: linear-gradient(135deg, #dc3545, #b02a37); color: #fff; }
        .tooltip-danger .tooltip-arrow::after { background: linear-gradient(135deg, #dc3545, #b02a37); }

        .tooltip-chronic .tooltip-inner { background: linear-gradient(135deg, #7f1d1d, #450a0a); color: #fff; }
        .tooltip-chronic .tooltip-arrow::after { background: linear-gradient(135deg, #7f1d1d, #450a0a); }

        /* Div gradient variants */
        .div-success { background: linear-gradient(135deg, #198754, #157347); color: #fff; }
        .div-warning { background: linear-gradient(135deg, #ffc107, #e0a800); color: #212529; }
        .div-danger { background: linear-gradient(135deg, #dc3545, #b02a37); color: #fff; }
        .div-chronic { background: linear-gradient(135deg, #7f1d1d, #450a0a); color: #fff; }
      </style>




      <!-- Sub-tab 2: Expenses List -->
      <div id="sub-tab-fin-expenses" class="sub-tab-content d-none">
          <div class="bg-white rounded-3 shadow-sm border border-light overflow-hidden">
              <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0 text-sm">
                      <thead class="bg-light text-secondary text-uppercase small">
                          <tr>
                              <th class="ps-4 py-3">Date</th>
                              <th class="py-3">Description</th>
                              <th class="py-3">Category</th>
                              <th class="text-end pe-4 py-3">Amount</th>
                          </tr>
                      </thead>
                      <tbody>
                          <?php if (empty($data['detailed_expenses'])): ?>
                              <tr><td colspan="4" class="text-center py-5 text-muted italic">No expenses recorded for this period.</td></tr>
                          <?php else: ?>
                              <?php foreach ( ($data['detailed_expenses'] ?? []) as $ex ): ?>
                                  <tr>
                                      <td class="ps-4 text-secondary small"><?php echo wp_date('d M, Y', strtotime($ex['date'] ?? 'now')); ?></td>
                                      <td>
                                          <div class="fw-bold text-dark"><?php echo esc_html($ex['description'] ?? 'N/A'); ?></div>
                                          <div class="small text-muted" style="font-size: 10px;"><?php echo esc_html($ex['payee'] ?? 'General Vendor'); ?></div>
                                      </td>
                                      <td><span class="badge bg-primary-subtle text-primary rounded-pill px-2 py-1" style="font-size: 9px;"><?php echo esc_html($ex['category'] ?? 'General'); ?></span></td>
                                      <td class="text-end pe-4 fw-bold">₹<?php echo SNESTX_in_fmt($ex['amount'] ?? 0); ?></td>
                                  </tr>
                              <?php endforeach; ?>
                          <?php endif; ?>
                      </tbody>
                  </table>
              </div>
          </div>
      </div>
</div>
