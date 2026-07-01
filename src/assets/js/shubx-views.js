/**
 * Society HubX – Admin Views JS
 *
 * Consolidated JS for all admin views that previously had inline <script> blocks.
 * Handles: Roles, Requests filter, Polls, Activity Hub, Expenses, Documents, Assets.
 *
 * phpcs:disable
 */

/* =====================================================================
   ROLES VIEW
   ===================================================================== */
function openRoleModal() {
	document.getElementById('role-form').reset();
	document.getElementById('role_id').value = '';
	document.getElementById('roleModalTitle').innerText = 'Create Custom Role';
	document.querySelectorAll('.cap-checkbox').forEach(cb => cb.checked = false);
	new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function editRole(role) {
	document.getElementById('role_id').value = role.id;
	document.getElementById('role_name').value = role.name;
	document.getElementById('roleModalTitle').innerText = 'Edit Role: ' + role.name;

	document.querySelectorAll('.cap-checkbox').forEach(cb => cb.checked = false);
	const caps = JSON.parse(role.capabilities || '[]');
	caps.forEach(cap => {
		const cb = document.getElementById('cap_' + cap);
		if (cb) cb.checked = true;
	});

	new bootstrap.Modal(document.getElementById('roleModal')).show();
}

function deleteRole(roleId) {
	if (!confirm('Are you sure you want to delete this role? This cannot be undone.')) return;

	const form = document.createElement('form');
	form.method = 'POST';
	// shubxViewsConfig.adminPostUrl is set via wp_add_inline_script in society-hubx.php
	form.action = (typeof shubxViewsConfig !== 'undefined') ? shubxViewsConfig.adminPostUrl : '';

	const fields = {
		action: 'shubx51_delete_role',
		role_id: roleId,
		_wpnonce: (typeof shubxViewsConfig !== 'undefined') ? shubxViewsConfig.roleNonce : ''
	};

	for (const key in fields) {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = key;
		input.value = fields[key];
		form.appendChild(input);
	}

	document.body.appendChild(form);
	form.submit();
}

/* =====================================================================
   REQUESTS VIEW – Search / Filter
   ===================================================================== */
(function () {
	document.addEventListener('DOMContentLoaded', function () {
		const search = document.getElementById('req-search');
		const moduleFilter = document.getElementById('req-filter-module');
		if (!search || !moduleFilter) return;

		const rows = document.querySelectorAll('.request-row');
		const filter = () => {
			const query = search.value.toLowerCase();
			const mod = moduleFilter.value;
			rows.forEach(row => {
				const text = row.textContent.toLowerCase();
				const moduleMatch = mod === 'all' || row.dataset.module === mod;
				const textMatch = text.includes(query);
				row.style.display = (moduleMatch && textMatch) ? '' : 'none';
			});
		};

		search.addEventListener('keyup', filter);
		moduleFilter.addEventListener('change', filter);
	});
}());

/* =====================================================================
   POLLS VIEW
   ===================================================================== */
function addOptionField() {
	const container = document.getElementById('optionsContainer');
	const input = document.createElement('input');
	input.type = 'text';
	input.name = 'options[]';
	input.placeholder = 'Next Option';
	input.className = 'form-control shadow-none rounded-lg mt-1';
	container.appendChild(input);
}

let pollModal = null;
function openPollModal() {
	if (!pollModal) pollModal = new bootstrap.Modal(document.getElementById('createPollModal'));
	pollModal.show();
}

(function () {
	document.addEventListener('DOMContentLoaded', () => {
		const search = document.getElementById('shubx-poll-search');
		if (search) {
			search.addEventListener('keyup', (e) => {
				const val = e.target.value.toLowerCase();
				document.querySelectorAll('.shubx-poll-card').forEach(el => {
					const text = el.dataset.search || '';
					el.style.display = text.includes(val) ? '' : 'none';
				});
			});
		}
	});
}());

/* =====================================================================
   ACTIVITY HUB VIEW
   ===================================================================== */
(function () {
	document.querySelectorAll('#activityTabs .nav-link').forEach(tab => {
		tab.addEventListener('shown.bs.tab', (e) => {
			document.querySelectorAll('#activityTabs .nav-link').forEach(t => {
				t.classList.remove('fw-bold', 'text-primary', 'border-primary');
				t.classList.add('fw-semibold', 'text-muted', 'border-transparent');
			});
			e.target.classList.remove('fw-semibold', 'text-muted', 'border-transparent');
			e.target.classList.add('fw-bold', 'text-primary', 'border-primary');
		});
	});
}());

/* =====================================================================
   EXPENSES VIEW
   ===================================================================== */
let expenseModal = null;
let expenseFuse = null;
currentTab = 'verified';

function openExpenseModal() {
	if (!expenseModal) expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
	resetExpenseForm();
	expenseModal.show();
}

function editExpense(btn) {
	if (!expenseModal) expenseModal = new bootstrap.Modal(document.getElementById('expenseModal'));
	const data = JSON.parse(btn.dataset.expense);
	const form = document.getElementById('expense-form');

	form.querySelector('[name="date"]').value = data.date;
	form.querySelector('[name="category"]').value = data.category;
	form.querySelector('[name="amount"]').value = data.amount;
	form.querySelector('[name="payee"]').value = data.payee || '';
	form.querySelector('[name="description"]').value = data.description || '';
	form.querySelector('[name="existing_receipt_url"]').value = data.receipt_url || '';
	if (form.querySelector('[name="account_type"]')) form.querySelector('[name="account_type"]').value = data.account_type || 'bank';

	form.querySelector('[name="action"]').value = 'shubx51_edit_expense';
	form.querySelector('[name="expense_id"]').value = data.id || '';
	const activeNonceField = document.getElementById('active_nonce_field');
	const rawEditNonce = document.getElementById('raw_edit_nonce');
	if (activeNonceField && rawEditNonce) activeNonceField.value = rawEditNonce.value;

	document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
	expenseModal.show();
}

function resetExpenseForm() {
	const form = document.getElementById('expense-form');
	if (form) form.reset();
	const actionField = form.querySelector('[name="action"]');
	if (actionField) actionField.value = 'shubx51_add_expense';
	const idField = form.querySelector('[name="expense_id"]');
	if (idField) idField.value = '';
	const activeNonceField = document.getElementById('active_nonce_field');
	const rawAddNonce = document.getElementById('raw_add_nonce');
	if (activeNonceField && rawAddNonce) activeNonceField.value = rawAddNonce.value;
	const modalTitle = document.getElementById('expenseModalTitle');
	if (modalTitle) modalTitle.textContent = 'Record New Expense';
}

window.applyExpenseSearch = function () {
	const input = document.getElementById('expenseSearch');
	const query = input ? input.value.trim() : '';

	if (!expenseFuse && window.SHUBXCreateFuse) {
		expenseFuse = window.SHUBXCreateFuse('.expense-row');
	}

	const matches = query && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(expenseFuse, query) : null;

	document.querySelectorAll('.expense-row').forEach(row => {
		const isPending = row.closest('#view-pending') !== null;
		const belongsToActiveTab = (currentTab === 'pending' && isPending) || (currentTab === 'verified' && !isPending);

		const matchesSearch = !query || (matches && matches.has(row));
		row.style.display = (belongsToActiveTab && matchesSearch) ? '' : 'none';
	});
};

function switchExpenseTab(tab) {
	currentTab = tab;
	const v = document.getElementById('view-verified');
	const p = document.getElementById('view-pending');
	const btV = document.getElementById('tab-btn-verified');
	const btP = document.getElementById('tab-btn-pending');

	if (tab === 'verified') {
		v.classList.remove('d-none');
		p.classList.add('d-none');
		btV.classList.add('active', 'border-primary', 'text-primary');
		btV.classList.remove('border-transparent', 'text-muted');
		btP.classList.remove('active', 'border-primary', 'text-primary');
		btP.classList.add('border-transparent', 'text-muted');
	} else {
		v.classList.add('d-none');
		p.classList.remove('d-none');
		btP.classList.add('active', 'border-primary', 'text-primary');
		btP.classList.remove('border-transparent', 'text-muted');
		btV.classList.remove('active', 'border-primary', 'text-primary');
		btV.classList.add('border-transparent', 'text-muted');
	}
	window.applyExpenseSearch();
}

(function () {
	document.addEventListener('DOMContentLoaded', function () {
		const searchInput = document.getElementById('expenseSearch');
		if (searchInput) {
			searchInput.addEventListener('input', window.applyExpenseSearch);
			searchInput.addEventListener('focus', function () {
				if (window.SHUBXCreateFuse) expenseFuse = window.SHUBXCreateFuse('.expense-row');
			});
		}
	});
}());

/* =====================================================================
   DOCUMENTS VIEW
   ===================================================================== */
(function () {
	document.addEventListener('DOMContentLoaded', () => {
		const search = document.getElementById('residentSearch');
		if (search) {
			search.addEventListener('keyup', (e) => {
				const val = e.target.value.toLowerCase();
				document.querySelectorAll('#residentList .resident-item').forEach(el => {
					const flatEl = el.querySelector('.resident-flat');
					const nameEl = el.querySelector('.resident-name');
					const flat = flatEl ? flatEl.textContent.toLowerCase() : '';
					const name = nameEl ? nameEl.textContent.toLowerCase() : '';
					el.style.display = (flat.includes(val) || name.includes(val)) ? '' : 'none';
				});
			});
		}
	});
}());

let uploadModal = null;
function openUploadModal() {
	if (!uploadModal) uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
	uploadModal.show();
}

/* =====================================================================
   ASSETS VIEW
   ===================================================================== */
let assetModalInstance = null;
let currentTab = 'all';
let assetFuse = null;

function switchTab(tab) {
	currentTab = tab;
	document.querySelectorAll('#assetTabs .nav-link').forEach(btn => {
		if (btn.dataset.tab === tab) {
			btn.classList.add('active', 'border-primary', 'text-primary');
			btn.classList.remove('border-transparent', 'text-muted');
		} else {
			btn.classList.remove('active', 'border-primary', 'text-primary');
			btn.classList.add('border-transparent', 'text-muted');
		}
	});
	applyFilters();
}

function applyFilters() {
	const searchInput = document.getElementById('assetSearch');
	const searchVal = searchInput ? searchInput.value.trim().toLowerCase() : '';

	if (!assetFuse && window.SHUBXCreateFuse) {
		assetFuse = window.SHUBXCreateFuse('.asset-row');
	}

	const fuzzyMatches = searchVal && window.SHUBXGetFuzzyMatches ? window.SHUBXGetFuzzyMatches(assetFuse, searchVal) : null;

	document.querySelectorAll('.asset-row').forEach(row => {
		const status = row.dataset.status;
		let matchTab = false;
		if (currentTab === 'archived') {
			matchTab = (status === 'archived');
		} else if (currentTab === 'active') {
			matchTab = (status === 'active' || status === 'operational');
		} else if (currentTab === 'all') {
			matchTab = (status !== 'archived');
		} else {
			matchTab = (status === currentTab);
		}

		const matchSearch = !searchVal || (fuzzyMatches && fuzzyMatches.has(row));

		if (matchTab && matchSearch) {
			row.classList.remove('d-none');
			row.style.display = '';
		} else {
			row.classList.add('d-none');
		}
	});
}

(function () {
	document.addEventListener('DOMContentLoaded', function () {
		const assetSearch = document.getElementById('assetSearch');
		if (assetSearch) {
			assetSearch.addEventListener('input', applyFilters);
			assetSearch.addEventListener('focus', function () {
				if (window.SHUBXCreateFuse) assetFuse = window.SHUBXCreateFuse('.asset-row');
			});
		}
	});
}());

function openAddAssetModal() {
	const form = document.getElementById('asset-form');
	if (form) form.reset();
	document.getElementById('asset-form-action').value = 'shubx51_add_asset';
	document.getElementById('asset-id').value = '';
	document.getElementById('assetModalTitle').textContent = 'Register New Asset';
	document.getElementById('asset-submit-btn').textContent = 'Register Asset';

	if (!assetModalInstance) assetModalInstance = new bootstrap.Modal(document.getElementById('assetModal'));
	assetModalInstance.show();
}

function openEditAssetModal(asset) {
	document.getElementById('asset-form-action').value = 'shubx51_edit_asset';
	document.getElementById('asset-id').value = asset.id;
	document.getElementById('asset-name').value = asset.name;
	document.getElementById('asset-purchase-date').value = asset.purchase_date;
	document.getElementById('asset-warranty-expiry').value = asset.warranty_expiry;
	document.getElementById('asset-amc-provider').value = asset.amc_provider;
	document.getElementById('asset-amc-phone').value = asset.amc_phone || '';
	document.getElementById('asset-category').value = asset.category || 'Machinery';
	document.getElementById('asset-value').value = asset.value || '';
	document.getElementById('asset-description').value = asset.description || '';
	document.getElementById('asset-status').value = asset.status;

	document.getElementById('assetModalTitle').textContent = 'Modify Asset Details';
	document.getElementById('asset-submit-btn').textContent = 'Save Changes';

	if (!assetModalInstance) assetModalInstance = new bootstrap.Modal(document.getElementById('assetModal'));
	assetModalInstance.show();
}
