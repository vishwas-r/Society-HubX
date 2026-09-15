<?php
/**
 * Directory Modals Component (Cross-Linked Modals for Units, Vehicles & Residents)
 *
 * Provides:
 * 1. #unitDetailsModal - shows Unit specs, linked owners/residents (with links to resident modal), linked vehicles (with links to vehicle modal)
 * 2. #vehicleDetailsModal - shows vehicle specs, mapped unit (with link to unit modal), mapped owner (with link to resident modal)
 * 3. #residentDetailsModal - shows resident specs, mapped unit (with link to unit modal), all family members, and mapped vehicles (with link to vehicle modal)
 *
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$db = new NAMMASOCIETY51_DB_Router();
$all_flats = $db->get( 'flats' );
if ( empty( $all_flats ) || ! is_array( $all_flats ) ) {
	$all_flats = array();
}

$all_vehicles = $db->get( 'vehicles' );
if ( empty( $all_vehicles ) || ! is_array( $all_vehicles ) ) {
	$all_vehicles = array();
}

$all_residents = $db->get( 'residents' );
if ( empty( $all_residents ) || ! is_array( $all_residents ) ) {
	$all_residents = array();
}

$all_family = $db->get( 'family_members' );
if ( empty( $all_family ) || ! is_array( $all_family ) ) {
	$all_family = array();
}

// Add residents of type 'family' into family array if not already present
foreach ( $all_residents as $r ) {
	if ( isset( $r['type'] ) && strtolower( $r['type'] ) === 'family' ) {
		$exists = false;
		foreach ( $all_family as $f ) {
			if ( ( $f['name'] ?? '' ) === ( $r['name'] ?? '' ) && ( $f['flat_no'] ?? '' ) === ( $r['flat_no'] ?? '' ) ) {
				$exists = true;
				break;
			}
		}
		if ( ! $exists ) {
			$all_family[] = array(
				'id'           => $r['id'] ?? '',
				'name'         => $r['name'] ?? '',
				'relationship' => $r['relationship'] ?? ( $r['relation'] ?? 'Family Member' ),
				'relation'     => $r['relationship'] ?? ( $r['relation'] ?? 'Family Member' ),
				'flat_no'      => $r['flat_no'] ?? '',
				'phone'        => $r['phone'] ?? '',
				'email'        => $r['email'] ?? '',
				'age'          => $r['age'] ?? '',
			);
		}
	}
}
?>

<!-- 1. UNIT DETAILS MODAL -->
<div class="modal fade" id="unitDetailsModal" tabindex="-1" aria-labelledby="unitDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
			<div class="modal-header border-bottom px-4 py-3 bg-light">
				<div class="d-flex align-items-center gap-3">
					<div class="rounded-3 bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
						<i class="bi bi-building fs-4"></i>
					</div>
					<div>
						<h5 class="modal-title fw-bold text-dark m-0" id="unitModalTitle">Unit Details</h5>
						<p class="text-secondary small m-0" id="unitModalSubtitle">Residential Unit</p>
					</div>
				</div>
				<button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<div class="modal-body p-4">
				<!-- Status Banner -->
				<div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border mb-3">
					<span class="small fw-bold text-secondary text-uppercase">Occupancy Status</span>
					<span id="unitModalStatusBadge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 rounded-pill px-3 py-1.5 fw-bold">Occupied</span>
				</div>

				<!-- Unit Specs Grid -->
				<div class="row g-2 mb-4">
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Configuration</div>
							<div class="fw-bold text-dark" id="unitModalType">2BHK</div>
						</div>
					</div>
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Super Built-up</div>
							<div class="fw-bold text-dark" id="unitModalArea">1,250 sqft</div>
						</div>
					</div>
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Parking Bay</div>
							<div class="fw-bold text-dark" id="unitModalParking">P-12</div>
						</div>
					</div>
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Intercom</div>
							<div class="fw-bold text-dark" id="unitModalIntercom">Ext 204</div>
						</div>
					</div>
				</div>

				<!-- Linked Owners & Residents -->
				<div class="mb-4">
					<div class="d-flex align-items-center justify-content-between mb-2">
						<h6 class="fw-bold text-dark text-uppercase small m-0" style="letter-spacing: 0.5px;">
							<i class="bi bi-people text-primary me-1"></i> Registered Owners & Residents
						</h6>
						<span id="unitModalResidentsCount" class="badge bg-light text-secondary border">0</span>
					</div>
					<div id="unitModalResidentsList" class="d-flex flex-column gap-2">
						<!-- Injected dynamically -->
					</div>
				</div>

				<!-- Linked Vehicles -->
				<div>
					<div class="d-flex align-items-center justify-content-between mb-2">
						<h6 class="fw-bold text-dark text-uppercase small m-0" style="letter-spacing: 0.5px;">
							<i class="bi bi-car-front text-info me-1"></i> Registered Vehicles
						</h6>
						<span id="unitModalVehiclesCount" class="badge bg-light text-secondary border">0</span>
					</div>
					<div id="unitModalVehiclesList" class="d-flex flex-column gap-2">
						<!-- Injected dynamically -->
					</div>
				</div>
			</div>

			<div class="modal-footer border-top bg-light px-4 py-3">
				<button type="button" class="btn btn-secondary px-4 fw-semibold rounded-3 shadow-none" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- 2. VEHICLE DETAILS MODAL -->
<div class="modal fade" id="vehicleDetailsModal" tabindex="-1" aria-labelledby="vehicleDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
			<div class="modal-header border-bottom px-4 py-3 bg-light">
				<div class="d-flex align-items-center gap-3">
					<div id="vehicleModalIconBox" class="rounded-3 bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
						<i id="vehicleModalIcon" class="bi bi-car-front fs-4"></i>
					</div>
					<div>
						<h5 class="modal-title fw-bold text-dark m-0 font-monospace" id="vehicleModalPlate">KA-01-AB-1234</h5>
						<p class="text-secondary small m-0" id="vehicleModalMakeModel">Registered Vehicle</p>
					</div>
				</div>
				<button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<div class="modal-body p-4">
				<!-- Vehicle Specs Grid -->
				<div class="row g-2 mb-4">
					<div class="col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Category</div>
							<div class="fw-bold text-dark" id="vehicleModalCategory">4-Wheeler</div>
						</div>
					</div>
					<div class="col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Parking Bay</div>
							<div class="fw-bold text-dark" id="vehicleModalSlot">Designated Bay</div>
						</div>
					</div>
					<div class="col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Assigned Unit</div>
							<div class="fw-bold text-dark" id="vehicleModalAssignedUnit">Unit 101</div>
						</div>
					</div>
					<div class="col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Sticker / RFID</div>
							<div class="fw-bold text-dark" id="vehicleModalSticker">Verified Active</div>
						</div>
					</div>
				</div>

				<!-- Mapped Unit Card -->
				<div class="mb-3">
					<div class="small fw-bold text-secondary text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Mapped Unit</div>
					<div class="p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between">
						<div class="d-flex align-items-center gap-3">
							<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
								<i class="bi bi-building"></i>
							</div>
							<div>
								<div class="fw-bold text-dark" id="vehicleModalFlatTitle">Flat 101</div>
								<div class="text-secondary small" id="vehicleModalFlatSub">Main Block • Occupied</div>
							</div>
						</div>
						<button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-3 px-3" id="vehicleModalViewUnitBtn">
							View Unit →
						</button>
					</div>
				</div>

				<!-- Registered Owner / Resident Card -->
				<div>
					<div class="small fw-bold text-secondary text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Registered Owner / Resident</div>
					<div class="p-3 rounded-3 bg-light border" id="vehicleModalOwnerCard">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<div class="d-flex align-items-center gap-3">
								<div class="rounded-circle bg-success text-white fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;" id="vehicleModalOwnerAvatar">
									O
								</div>
								<div>
									<div class="fw-bold text-dark" id="vehicleModalOwnerName">Owner Name</div>
									<div class="text-secondary small" id="vehicleModalOwnerRole">OWNER • Primary Contact</div>
								</div>
							</div>
							<button type="button" class="btn btn-sm btn-outline-success fw-bold rounded-3 px-3" id="vehicleModalViewOwnerBtn">
								View Profile →
							</button>
						</div>
						<div id="vehicleModalOwnerPhoneRow" class="mt-2 pt-2 border-top d-flex align-items-center justify-content-between">
							<span class="small text-secondary"><i class="bi bi-telephone me-1"></i> <span id="vehicleModalOwnerPhoneText">+91 98765 43210</span></span>
							<a href="#" id="vehicleModalOwnerPhoneCall" class="btn btn-sm btn-success py-1 px-3 rounded-pill fw-bold" style="font-size: 11px;">
								<i class="bi bi-telephone-fill me-1"></i> Call
							</a>
						</div>
					</div>
				</div>
			</div>

			<div class="modal-footer border-top bg-light px-4 py-3">
				<button type="button" class="btn btn-secondary px-4 fw-semibold rounded-3 shadow-none" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<!-- 3. RESIDENT DETAILS MODAL -->
<div class="modal fade" id="residentDetailsModal" tabindex="-1" aria-labelledby="residentDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
			<div class="modal-header border-bottom px-4 py-3 bg-light">
				<div class="d-flex align-items-center gap-3">
					<div id="residentModalAvatarCircle" class="rounded-circle bg-success text-white fw-bold d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 18px;">
						R
					</div>
					<div>
						<div class="d-flex align-items-center gap-2">
							<h5 class="modal-title fw-bold text-dark m-0" id="residentModalName">Resident Name</h5>
							<span id="residentModalTypeBadge" class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 rounded-pill px-2 py-1 small fw-bold">OWNER</span>
						</div>
						<p class="text-secondary small m-0" id="residentModalSub">Unit 101 • Active Member</p>
					</div>
				</div>
				<button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<div class="modal-body p-4">
				<!-- Resident Specs Grid -->
				<div class="row g-2 mb-4">
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Resident Type</div>
							<div class="fw-bold text-dark" id="residentModalType">Owner</div>
						</div>
					</div>
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Block / Tower</div>
							<div class="fw-bold text-dark" id="residentModalBlock">Block A</div>
						</div>
					</div>
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Blood Group</div>
							<div class="fw-bold text-dark" id="residentModalBlood">O+</div>
						</div>
					</div>
					<div class="col-sm-3 col-6">
						<div class="p-3 rounded-3 bg-light border text-center h-100">
							<div class="text-secondary small fw-semibold mb-1" style="font-size: 11px;">Status</div>
							<div class="fw-bold text-success" id="residentModalStatus">Active</div>
						</div>
					</div>
				</div>

				<!-- Mapped Unit Card -->
				<div class="mb-4">
					<div class="small fw-bold text-secondary text-uppercase mb-2" style="font-size: 11px; letter-spacing: 0.5px;">Assigned Residential Unit</div>
					<div class="p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between">
						<div class="d-flex align-items-center gap-3">
							<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
								<i class="bi bi-building"></i>
							</div>
							<div>
								<div class="fw-bold text-dark" id="residentModalFlatTitle">Unit 101</div>
								<div class="text-secondary small" id="residentModalFlatSub">Main Block • Occupied</div>
							</div>
						</div>
						<button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-3 px-3" id="residentModalViewUnitBtn">
							View Unit →
						</button>
					</div>
				</div>

				<!-- Registered Family Members Section -->
				<div class="mb-4">
					<div class="d-flex align-items-center justify-content-between mb-2">
						<h6 class="fw-bold text-dark text-uppercase small m-0" style="letter-spacing: 0.5px;">
							<i class="bi bi-people-fill text-primary me-1"></i> Registered Family Members
						</h6>
						<span id="residentModalFamilyCount" class="badge bg-light text-secondary border">0</span>
					</div>
					<div id="residentModalFamilyList" class="d-flex flex-column gap-2">
						<!-- Injected dynamically -->
					</div>
				</div>

				<!-- Registered Vehicles Section -->
				<div class="mb-4">
					<div class="d-flex align-items-center justify-content-between mb-2">
						<h6 class="fw-bold text-dark text-uppercase small m-0" style="letter-spacing: 0.5px;">
							<i class="bi bi-car-front text-info me-1"></i> Registered Vehicles
						</h6>
						<span id="residentModalVehiclesCount" class="badge bg-light text-secondary border">0</span>
					</div>
					<div id="residentModalVehiclesList" class="d-flex flex-column gap-2">
						<!-- Injected dynamically -->
					</div>
				</div>

				<!-- Direct Connect & Contact Section -->
				<div>
					<h6 class="fw-bold text-dark text-uppercase small mb-2" style="letter-spacing: 0.5px;">
						<i class="bi bi-telephone-outbound text-secondary me-1"></i> Connect & Contact
					</h6>
					<div class="d-flex flex-wrap gap-2">
						<a href="#" id="residentModalCallBtn" class="btn btn-primary px-4 fw-bold rounded-3 d-flex align-items-center gap-2 shadow-sm">
							<i class="bi bi-telephone-fill"></i>
							<span id="residentModalCallText">Call Resident</span>
						</a>
						<a href="#" id="residentModalEmailBtn" class="btn btn-outline-secondary px-4 fw-bold rounded-3 d-flex align-items-center gap-2">
							<i class="bi bi-envelope-fill"></i>
							<span id="residentModalEmailText">Send Email</span>
						</a>
					</div>
				</div>
			</div>

			<div class="modal-footer border-top bg-light px-4 py-3">
				<button type="button" class="btn btn-secondary px-4 fw-semibold rounded-3 shadow-none" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

<script>
window.nammasocietyDirectoryData = {
	flats: <?php echo wp_json_encode( $all_flats ); ?>,
	vehicles: <?php echo wp_json_encode( $all_vehicles ); ?>,
	residents: <?php echo wp_json_encode( $all_residents ); ?>,
	family: <?php echo wp_json_encode( $all_family ); ?>
};

(function() {
	function getCanonicalUnitKey(val, fallbackBlock) {
		if (!val) return '';
		let flatStr = '';
		let blockStr = fallbackBlock || '';

		if (typeof val === 'object' && val !== null) {
			flatStr = String(val.flat_no || val.flat_number || val.id || '');
			blockStr = val.block || fallbackBlock || '';
		} else {
			flatStr = String(val).trim();
		}

		// Remove entity prefixes like 'flat_', 'res_', 'veh_'
		flatStr = flatStr.replace(/^(flat_|res_|veh_)/i, '');
		// Remove vehicle type suffixes like '_car', '_bike1'
		flatStr = flatStr.replace(/(_car|_bike\d*)$/i, '');

		// Normalize block string
		let normalizedBlock = String(blockStr).trim().replace(/^block\s*/i, '').trim().toLowerCase();

		// Check if flatStr contains block prefix, e.g. 'A-101', 'A 101', 'A_101', 'Block A-101'
		flatStr = flatStr.replace(/^block\s*/i, '');
		const match = flatStr.match(/^([a-z0-9]+)[-_\s]+(.*)$/i);
		if (match) {
			if (/^[a-z]+$/i.test(match[1])) {
				normalizedBlock = match[1].toLowerCase();
				flatStr = match[2];
			}
		}

		const cleanFlat = flatStr.replace(/[^a-z0-9]/gi, '').toLowerCase();
		const cleanBlock = normalizedBlock.replace(/[^a-z0-9]/gi, '').toLowerCase();

		if (cleanBlock && cleanFlat) {
			return cleanBlock + '_' + cleanFlat;
		}
		return cleanFlat || cleanBlock;
	}

	function formatBlockName(block) {
		if (!block) return 'Main Block';
		let str = String(block).trim();
		if (!/^block\s+/i.test(str)) {
			str = 'Block ' + str;
		}
		return str;
	}

	function switchModal(hideModalId, showModalId) {
		const hideEl = document.getElementById(hideModalId);
		const showEl = document.getElementById(showModalId);
		if (hideEl) {
			const bsHide = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(hideEl) : null;
			if (bsHide) bsHide.hide();
		}
		setTimeout(function() {
			if (showEl) {
				let bsShow = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(showEl) : null;
				if (!bsShow && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
					bsShow = new bootstrap.Modal(showEl);
				}
				if (bsShow) bsShow.show();
			}
		}, 180);
	}

	// 1. Open Unit Details Modal
	window.nammasocietyOpenUnitModal = function(query, blockContext) {
		const data = window.nammasocietyDirectoryData;
		const queryStr = String(query || '').trim();
		const targetKey = getCanonicalUnitKey(queryStr, blockContext);

		// Match flat by canonical key first, then by exact id or flat_number
		const flat = data.flats.find(f => getCanonicalUnitKey(f) === targetKey) ||
			data.flats.find(f => String(f.id) === queryStr || (String(f.flat_number) === queryStr && (!blockContext || getCanonicalUnitKey(f, blockContext) === targetKey))) ||
			{ id: queryStr, flat_number: queryStr, block: blockContext || '', status: 'Occupied' };

		const unitKey = getCanonicalUnitKey(flat, blockContext || flat.block);
		const rawBlock = String(flat.block || blockContext || '').trim().replace(/^block\s*/i, '');
		let displayUnit = unitNo;
		if (rawBlock && !String(unitNo).toLowerCase().startsWith(rawBlock.toLowerCase())) {
			displayUnit = rawBlock + ' - ' + unitNo;
		}
		document.getElementById('unitModalTitle').textContent = 'Unit ' + displayUnit;
		document.getElementById('unitModalSubtitle').textContent = blockDisplay + ' • ' + (flat.floor ? 'Floor ' + flat.floor : 'Residential Unit');
		
		const badge = document.getElementById('unitModalStatusBadge');
		badge.textContent = isOccupied ? 'Occupied' : 'Vacant';
		badge.className = 'badge ' + (isOccupied ? 'bg-success bg-opacity-10 text-success border border-success border-opacity-10' : 'bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-10') + ' rounded-pill px-3 py-1.5 fw-bold';

		document.getElementById('unitModalType').textContent = flat.type || flat.flat_type || '2BHK';
		document.getElementById('unitModalArea').textContent = (flat.sq_foot || flat.sqft_area || '1,250') + ' sqft';
		document.getElementById('unitModalParking').textContent = flat.parking_slot || 'P-12';
		document.getElementById('unitModalIntercom').textContent = flat.intercom_num || 'Ext 204';

		// Linked Residents: Strictly matching canonical unit key (never merge across blocks!)
		const linkedResidents = data.residents.filter(r => getCanonicalUnitKey(r) === unitKey);
		const resListEl = document.getElementById('unitModalResidentsList');
		document.getElementById('unitModalResidentsCount').textContent = linkedResidents.length;
		resListEl.innerHTML = '';

		if (linkedResidents.length > 0) {
			linkedResidents.forEach(r => {
				const isOwner = String(r.type || '').toLowerCase() === 'owner';
				const row = document.createElement('div');
				row.className = 'p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2';
				row.innerHTML = `
					<div class="d-flex align-items-center gap-3">
						<div class="rounded-circle ${isOwner ? 'bg-primary' : 'bg-info'} text-white fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
							${(r.name || 'R')[0].toUpperCase()}
						</div>
						<div>
							<div class="fw-bold text-dark">${r.name || 'Resident'}</div>
							<div class="text-secondary small">
								<span class="badge ${isOwner ? 'bg-primary' : 'bg-info text-dark'} rounded-pill px-2 py-0.5" style="font-size: 9px;">${(r.type || 'RESIDENT').toUpperCase()}</span>
								• ${r.status || 'Active'}
							</div>
						</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						${r.phone ? `<a href="tel:${r.phone}" class="btn btn-sm btn-outline-secondary rounded-3 px-2 py-1"><i class="bi bi-telephone-fill"></i></a>` : ''}
						<button type="button" class="btn btn-sm btn-outline-primary fw-bold rounded-3 px-3 js-link-to-resident" data-resident-id="${r.id || r.name}">
							View Profile →
						</button>
					</div>
				`;
				resListEl.appendChild(row);
			});
		} else {
			resListEl.innerHTML = `
				<div class="p-3 rounded-3 bg-light border border-dashed text-center text-secondary small fst-italic">
					No registered residents listed under this unit.
				</div>
			`;
		}

		// Linked Vehicles: Strictly matching canonical unit key (never merge across blocks!)
		const linkedVehicles = data.vehicles.filter(v => getCanonicalUnitKey(v) === unitKey);
		const vehListEl = document.getElementById('unitModalVehiclesList');
		document.getElementById('unitModalVehiclesCount').textContent = linkedVehicles.length;
		vehListEl.innerHTML = '';

		if (linkedVehicles.length > 0) {
			linkedVehicles.forEach(v => {
				const isBike = String(v.type || '').toLowerCase().includes('2') || String(v.type || '').toLowerCase() === 'bike';
				const row = document.createElement('div');
				row.className = 'p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2';
				row.innerHTML = `
					<div class="d-flex align-items-center gap-3">
						<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
							<i class="bi ${isBike ? 'bi-bicycle' : 'bi-car-front'}"></i>
						</div>
						<div>
							<div class="fw-bold text-dark font-monospace">${v.plate_no || v.number || 'Vehicle'}</div>
							<div class="text-secondary small">${[v.brand, v.model].filter(Boolean).join(' ') || (isBike ? 'Two-Wheeler' : 'Four-Wheeler')} • Slot: ${v.parking_slot || 'P-Bay'}</div>
						</div>
					</div>
					<button type="button" class="btn btn-sm btn-outline-info fw-bold rounded-3 px-3 js-link-to-vehicle" data-vehicle-id="${v.id || v.plate_no || v.number}">
						View Vehicle →
					</button>
				`;
				vehListEl.appendChild(row);
			});
		} else {
			vehListEl.innerHTML = `
				<div class="p-3 rounded-3 bg-light border border-dashed text-center text-secondary small fst-italic">
					No vehicles registered under this unit.
				</div>
			`;
		}

		// Wire up cross-modal buttons inside Unit Modal
		resListEl.querySelectorAll('.js-link-to-resident').forEach(btn => {
			btn.addEventListener('click', function() {
				const rid = this.getAttribute('data-resident-id');
				switchModal('unitDetailsModal', 'residentDetailsModal');
				setTimeout(() => window.nammasocietyOpenResidentModal(rid), 190);
			});
		});

		vehListEl.querySelectorAll('.js-link-to-vehicle').forEach(btn => {
			btn.addEventListener('click', function() {
				const vid = this.getAttribute('data-vehicle-id');
				switchModal('unitDetailsModal', 'vehicleDetailsModal');
				setTimeout(() => window.nammasocietyOpenVehicleModal(vid), 190);
			});
		});

		const modalEl = document.getElementById('unitDetailsModal');
		let bs = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(modalEl) : null;
		if (!bs && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
			bs = new bootstrap.Modal(modalEl);
		}
		if (bs) bs.show();
	};

	// 2. Open Vehicle Details Modal
	window.nammasocietyOpenVehicleModal = function(query) {
		const data = window.nammasocietyDirectoryData;
		const queryStr = String(query || '').toLowerCase().trim();
		const vehicle = data.vehicles.find(v => 
			String(v.id).toLowerCase() === queryStr || 
			String(v.plate_no || '').toLowerCase() === queryStr ||
			String(v.number || '').toLowerCase() === queryStr
		);

		if (!vehicle) return;

		const isBike = String(vehicle.type || '').toLowerCase().includes('2') || String(vehicle.type || '').toLowerCase() === 'bike';
		const plate = vehicle.plate_no || vehicle.number || 'Vehicle';
		const makeModel = [vehicle.brand, vehicle.model].filter(Boolean).join(' ') || (isBike ? 'Two-Wheeler' : 'Four-Wheeler');

		document.getElementById('vehicleModalPlate').textContent = plate;
		document.getElementById('vehicleModalMakeModel').textContent = makeModel;
		document.getElementById('vehicleModalCategory').textContent = isBike ? '2-Wheeler' : '4-Wheeler';
		document.getElementById('vehicleModalSlot').textContent = vehicle.parking_slot || 'Designated Bay';
		
		let vehFlatDisp = vehicle.flat_no || '-';
		const vehBlock = String(vehicle.block || '').trim().replace(/^block\s*/i, '');
		if (vehBlock && !String(vehFlatDisp).toLowerCase().startsWith(vehBlock.toLowerCase())) {
			vehFlatDisp = vehBlock + ' - ' + vehFlatDisp;
		}
		document.getElementById('vehicleModalAssignedUnit').textContent = 'Unit ' + vehFlatDisp;
		document.getElementById('vehicleModalSticker').textContent = vehicle.sticker ? '#' + vehicle.sticker : 'Verified Active';

		const iconBox = document.getElementById('vehicleModalIconBox');
		const iconEl = document.getElementById('vehicleModalIcon');
		iconEl.className = 'bi ' + (isBike ? 'bi-bicycle' : 'bi-car-front') + ' fs-4';

		// Mapped Unit Link: Strictly match unit canonical key
		const vehKey = getCanonicalUnitKey(vehicle);
		const mappedFlat = data.flats.find(f => getCanonicalUnitKey(f) === vehKey) || { id: vehicle.flat_no, flat_number: vehicle.flat_no, block: vehicle.block || '', status: 'Occupied' };
		const flatBlockDisplay = formatBlockName(mappedFlat.block || vehicle.block);

		let vFlatDisp = mappedFlat.flat_number || mappedFlat.id || vehicle.flat_no;
		const vBlock = String(mappedFlat.block || vehicle.block || '').trim().replace(/^block\s*/i, '');
		if (vBlock && !String(vFlatDisp).toLowerCase().startsWith(vBlock.toLowerCase())) {
			vFlatDisp = vBlock + ' - ' + vFlatDisp;
		}
		document.getElementById('vehicleModalFlatTitle').textContent = 'Flat ' + vFlatDisp;
		document.getElementById('vehicleModalFlatSub').textContent = flatBlockDisplay + ' • ' + (mappedFlat.status || 'Occupied');

		const viewUnitBtn = document.getElementById('vehicleModalViewUnitBtn');
		viewUnitBtn.onclick = function() {
			switchModal('vehicleDetailsModal', 'unitDetailsModal');
			setTimeout(() => window.nammasocietyOpenUnitModal(vehicle.flat_no, vehicle.block), 190);
		};

		// Registered Owner: Strictly match resident in same unit/block
		let owner = null;
		if (vehicle.owner_name) {
			owner = data.residents.find(r => 
				r.name && r.name.toLowerCase() === vehicle.owner_name.toLowerCase() &&
				getCanonicalUnitKey(r) === vehKey
			) || data.residents.find(r => r.name && r.name.toLowerCase() === vehicle.owner_name.toLowerCase());
		}
		if (!owner) {
			const flatRes = data.residents.filter(r => getCanonicalUnitKey(r) === vehKey);
			owner = flatRes.find(r => String(r.type || '').toLowerCase() === 'owner') || flatRes[0] || null;
		}

		const ownerNameEl = document.getElementById('vehicleModalOwnerName');
		const ownerRoleEl = document.getElementById('vehicleModalOwnerRole');
		const ownerAvatarEl = document.getElementById('vehicleModalOwnerAvatar');
		const ownerPhoneRow = document.getElementById('vehicleModalOwnerPhoneRow');
		const viewOwnerBtn = document.getElementById('vehicleModalViewOwnerBtn');

		if (owner) {
			ownerNameEl.textContent = owner.name || 'Registered Resident';
			ownerRoleEl.textContent = (owner.type ? owner.type.toUpperCase() : 'OWNER') + ' • Unit ' + (owner.flat_no || vehicle.flat_no);
			ownerAvatarEl.textContent = (owner.name || 'O')[0].toUpperCase();
			viewOwnerBtn.style.display = '';
			viewOwnerBtn.onclick = function() {
				switchModal('vehicleDetailsModal', 'residentDetailsModal');
				setTimeout(() => window.nammasocietyOpenResidentModal(owner.id || owner.name), 190);
			};

			if (owner.phone) {
				ownerPhoneRow.style.display = '';
				document.getElementById('vehicleModalOwnerPhoneText').textContent = owner.phone;
				document.getElementById('vehicleModalOwnerPhoneCall').href = 'tel:' + owner.phone;
			} else {
				ownerPhoneRow.style.display = 'none';
			}
		} else {
			ownerNameEl.textContent = vehicle.owner_name || vehicle.resident_name || 'Registered Resident';
			ownerRoleEl.textContent = 'Resident of Unit ' + (vehicle.flat_no || '-');
			ownerAvatarEl.textContent = (vehicle.owner_name || 'R')[0].toUpperCase();
			viewOwnerBtn.style.display = 'none';
			ownerPhoneRow.style.display = 'none';
		}

		const modalEl = document.getElementById('vehicleDetailsModal');
		let bs = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(modalEl) : null;
		if (!bs && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
			bs = new bootstrap.Modal(modalEl);
		}
		if (bs) bs.show();
	};

	// 3. Open Resident Details Modal
	window.nammasocietyOpenResidentModal = function(query) {
		const data = window.nammasocietyDirectoryData;
		const queryStr = String(query || '').toLowerCase().trim();
		const resident = data.residents.find(r => 
			String(r.id).toLowerCase() === queryStr || 
			String(r.name || '').toLowerCase() === queryStr
		);

		if (!resident) return;

		const isOwner = String(resident.type || '').toLowerCase() === 'owner';
		document.getElementById('residentModalName').textContent = resident.name || 'Resident';
		document.getElementById('residentModalAvatarCircle').textContent = (resident.name || 'R')[0].toUpperCase();
		document.getElementById('residentModalAvatarCircle').className = 'rounded-circle ' + (isOwner ? 'bg-primary' : 'bg-info text-dark') + ' fw-bold d-flex align-items-center justify-content-center';
		
		const typeBadge = document.getElementById('residentModalTypeBadge');
		typeBadge.textContent = (resident.type || 'Resident').toUpperCase();
		typeBadge.className = 'badge ' + (isOwner ? 'bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10' : 'bg-info bg-opacity-10 text-info border border-info border-opacity-10') + ' rounded-pill px-2 py-1 small fw-bold';

		let resFlatDisp = resident.flat_no || '-';
		const resBlock = String(resident.block || '').trim().replace(/^block\s*/i, '');
		if (resBlock && !String(resFlatDisp).toLowerCase().startsWith(resBlock.toLowerCase())) {
			resFlatDisp = resBlock + ' - ' + resFlatDisp;
		}
		document.getElementById('residentModalSub').textContent = 'Unit ' + resFlatDisp + ' • ' + (resident.status || 'Active Member');

		document.getElementById('residentModalType').textContent = resident.type ? resident.type.charAt(0).toUpperCase() + resident.type.slice(1) : 'Resident';
		const resBlockDisplay = formatBlockName(resident.block);
		document.getElementById('residentModalBlock').textContent = resBlockDisplay;
		document.getElementById('residentModalBlood').textContent = resident.blood_group || 'Not Specified';
		document.getElementById('residentModalStatus').textContent = resident.status || 'Active';

		// Mapped Unit Link: Strictly match unit canonical key
		const resKey = getCanonicalUnitKey(resident);
		const mappedFlat = data.flats.find(f => getCanonicalUnitKey(f) === resKey) || { id: resident.flat_no, flat_number: resident.flat_no, block: resident.block || '', status: 'Occupied' };
		const flatBlockDisplay = formatBlockName(mappedFlat.block || resident.block);

		let mFlatDisp = mappedFlat.flat_number || mappedFlat.id || resident.flat_no;
		const mBlock = String(mappedFlat.block || resident.block || '').trim().replace(/^block\s*/i, '');
		if (mBlock && !String(mFlatDisp).toLowerCase().startsWith(mBlock.toLowerCase())) {
			mFlatDisp = mBlock + ' - ' + mFlatDisp;
		}
		document.getElementById('residentModalFlatTitle').textContent = 'Unit ' + mFlatDisp;
		document.getElementById('residentModalFlatSub').textContent = flatBlockDisplay + ' • ' + (mappedFlat.status || 'Occupied');

		const viewUnitBtn = document.getElementById('residentModalViewUnitBtn');
		viewUnitBtn.onclick = function() {
			switchModal('residentDetailsModal', 'unitDetailsModal');
			setTimeout(() => window.nammasocietyOpenUnitModal(resident.flat_no, resident.block), 190);
		};

		// Family Members: Strictly matching resident's unit key
		const familyMembers = data.family.filter(f => getCanonicalUnitKey(f, resident.block) === resKey);
		const famListEl = document.getElementById('residentModalFamilyList');
		document.getElementById('residentModalFamilyCount').textContent = familyMembers.length;
		famListEl.innerHTML = '';

		if (familyMembers.length > 0) {
			familyMembers.forEach(fam => {
				const row = document.createElement('div');
				row.className = 'p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2';
				row.innerHTML = `
					<div class="d-flex align-items-center gap-3">
						<div class="rounded-circle bg-purple text-white fw-bold d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; background-color: #8b5cf6;">
							<i class="bi bi-person-heart"></i>
						</div>
						<div>
							<div class="fw-bold text-dark">${fam.name || fam.member_name || 'Family Member'}</div>
							<div class="text-secondary small">
								<span class="badge bg-secondary bg-opacity-10 text-secondary border rounded-pill px-2 py-0.5" style="font-size: 9px;">${fam.relationship || fam.relation || 'Relation'}</span>
								${fam.age ? `• ${fam.age} yrs` : ''}
							</div>
						</div>
					</div>
					${fam.phone ? `<a href="tel:${fam.phone}" class="btn btn-sm btn-outline-primary rounded-3 px-3 py-1 fw-semibold"><i class="bi bi-telephone-fill me-1"></i> Call</a>` : ''}
				`;
				famListEl.appendChild(row);
			});
		} else {
			famListEl.innerHTML = `
				<div class="p-3 rounded-3 bg-light border border-dashed text-center text-secondary small fst-italic">
					No family members registered under this profile.
				</div>
			`;
		}

		// Registered Vehicles for this resident's unit: Strictly matching resident's unit key
		const linkedVehicles = data.vehicles.filter(v => getCanonicalUnitKey(v, resident.block) === resKey);
		const vehListEl = document.getElementById('residentModalVehiclesList');
		document.getElementById('residentModalVehiclesCount').textContent = linkedVehicles.length;
		vehListEl.innerHTML = '';

		if (linkedVehicles.length > 0) {
			linkedVehicles.forEach(v => {
				const isBike = String(v.type || '').toLowerCase().includes('2') || String(v.type || '').toLowerCase() === 'bike';
				const row = document.createElement('div');
				row.className = 'p-3 rounded-3 bg-light border d-flex align-items-center justify-content-between flex-wrap gap-2';
				row.innerHTML = `
					<div class="d-flex align-items-center gap-3">
						<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
							<i class="bi ${isBike ? 'bi-bicycle' : 'bi-car-front'}"></i>
						</div>
						<div>
							<div class="fw-bold text-dark font-monospace">${v.plate_no || v.number || 'Vehicle'}</div>
							<div class="text-secondary small">${[v.brand, v.model].filter(Boolean).join(' ') || (isBike ? 'Two-Wheeler' : 'Four-Wheeler')}</div>
						</div>
					</div>
					<button type="button" class="btn btn-sm btn-outline-info fw-bold rounded-3 px-3 js-link-to-vehicle-from-res" data-vehicle-id="${v.id || v.plate_no || v.number}">
						View Vehicle →
					</button>
				`;
				vehListEl.appendChild(row);
			});
		} else {
			vehListEl.innerHTML = `
				<div class="p-3 rounded-3 bg-light border border-dashed text-center text-secondary small fst-italic">
					No vehicles registered under this resident.
				</div>
			`;
		}

		vehListEl.querySelectorAll('.js-link-to-vehicle-from-res').forEach(btn => {
			btn.addEventListener('click', function() {
				const vid = this.getAttribute('data-vehicle-id');
				switchModal('residentDetailsModal', 'vehicleDetailsModal');
				setTimeout(() => window.nammasocietyOpenVehicleModal(vid), 190);
			});
		});

		// Call & Email buttons
		const callBtn = document.getElementById('residentModalCallBtn');
		const emailBtn = document.getElementById('residentModalEmailBtn');
		if (resident.phone) {
			callBtn.style.display = '';
			callBtn.href = 'tel:' + resident.phone;
			document.getElementById('residentModalCallText').textContent = 'Call ' + resident.phone;
		} else {
			callBtn.style.display = 'none';
		}

		if (resident.email) {
			emailBtn.style.display = '';
			emailBtn.href = 'mailto:' + resident.email;
			document.getElementById('residentModalEmailText').textContent = 'Email ' + resident.email;
		} else {
			emailBtn.style.display = 'none';
		}

		const modalEl = document.getElementById('residentDetailsModal');
		let bs = (typeof bootstrap !== 'undefined' && bootstrap.Modal) ? bootstrap.Modal.getInstance(modalEl) : null;
		if (!bs && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
			bs = new bootstrap.Modal(modalEl);
		}
		if (bs) bs.show();
	};

	// Event Delegation for triggers throughout the table views
	document.addEventListener('click', function(e) {
		const unitTrigger = e.target.closest('.js-view-unit, .js-view-flat');
		if (unitTrigger) {
			e.preventDefault();
			const flatId = unitTrigger.getAttribute('data-unit-id') || unitTrigger.getAttribute('data-flat-id') || unitTrigger.getAttribute('data-id') || unitTrigger.textContent.trim();
			const block = unitTrigger.getAttribute('data-block') || '';
			window.nammasocietyOpenUnitModal(flatId, block);
			return;
		}

		const vehTrigger = e.target.closest('.js-view-vehicle-details, .js-view-vehicle');
		if (vehTrigger) {
			e.preventDefault();
			const vid = vehTrigger.getAttribute('data-vehicle-id') || vehTrigger.getAttribute('data-id') || vehTrigger.textContent.trim();
			window.nammasocietyOpenVehicleModal(vid);
			return;
		}

		const resTrigger = e.target.closest('.js-view-resident-profile, .js-view-resident');
		if (resTrigger) {
			e.preventDefault();
			const rid = resTrigger.getAttribute('data-resident-id') || resTrigger.getAttribute('data-id') || resTrigger.textContent.trim();
			window.nammasocietyOpenResidentModal(rid);
			return;
		}
	});
})();
</script>
