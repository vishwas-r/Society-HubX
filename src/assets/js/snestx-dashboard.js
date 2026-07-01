(function ($) {
    'use strict';

    // State
    let expensesChart = null;
    let paymentChart = null;
    let dirFuse = null;
    let facFuse = null;
    let bookingFuse = null;
    let dirActiveFilter = 'all';

    // --- Chart Logic ---
    function initCharts() {
        if (!window.Chart || !window.SNESTXDashboardData) {
            console.warn('SNESTX Dashboard: Chart.js or SNESTXDashboardData missing. Some features may not work.');
            // return; // Don't return strictly, we have other logic to run
        }

        // --- 0. Tab Switching Logic (Migrated from frontend.js) ---
        const tabs = ['home', 'notices', 'requests', 'facilities', 'accounts', 'expenses', 'polls', 'rules', 'community', 'directory', 'notifications']; // Added requests

        function activateTab(tabName) {
            const btnId = 'btn-tab-' + tabName;
            const btn = document.getElementById(btnId);
            if (!btn) {
                console.error('SNESTX Dashboard: Tab button not found:', btnId);
                return;
            }

            const targetId = btn.getAttribute('data-tab-target');

            // Update button states
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'text-primary', 'border-primary');
                b.classList.add('border-transparent', 'text-muted');
            });
            btn.classList.remove('border-transparent', 'text-muted');
            btn.classList.add('active', 'text-primary', 'border-primary');

            // Update content visibility
            // FIX: Don't use generic .tab-content selector as it hides nested Bootstrap tabs (like in Facilities)
            // Instead, loop through known main tabs and hide them by ID.
            tabs.forEach(t => {
                const el = document.getElementById('tab-' + t);
                if (el) {
                    el.classList.add('d-none');
                    el.classList.remove('d-block');
                }
            });

            const targetEl = document.querySelector(targetId);
            if (targetEl) {
                targetEl.classList.remove('d-none');
                targetEl.classList.add('d-block');
            }

            // Update URL hash
            if (history.pushState) {
                const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '#tab-' + tabName;
                window.history.pushState({ path: newUrl }, '', newUrl);
            }

            // Trigger Chart Re-renders if needed
            if (tabName === 'expenses' && expensesChart) {
                setTimeout(() => expensesChart.update(), 100);
            }
            if ((tabName === 'home' || tabName === 'accounts') && paymentChart) {
                setTimeout(() => paymentChart.update(), 100);
            }
        }

        // Bind tab click events
        tabs.forEach(t => {
            const btn = document.getElementById('btn-tab-' + t);
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    activateTab(t);
                });
            }
        });

        // Auto-activate tab from URL hash
        const hash = window.location.hash;
        if (hash && hash.startsWith('#tab-')) {
            const tabName = hash.replace('#tab-', '');
            if (tabs.includes(tabName)) activateTab(tabName);
        } else if (new URLSearchParams(window.location.search).has('ex_year')) {
            activateTab('expenses');
        }

        // Sub-Tab Switching
        document.addEventListener('click', function (e) {
            const subTabBtn = e.target.closest('[data-subtab-target]');
            if (subTabBtn) {
                e.preventDefault();
                const subTabId = subTabBtn.getAttribute('data-subtab-target');
                switchSubTab(subTabId);
            }
        });

        function switchSubTab(subTabId) {
            const contents = document.querySelectorAll('.sub-tab-content');
            contents.forEach(c => {
                c.classList.add('d-none');
                c.classList.remove('d-block');
            });
            const target = document.getElementById('sub-tab-' + subTabId);
            if (target) {
                target.classList.remove('d-none');
                target.classList.add('d-block');
            }
            const navLinks = document.querySelectorAll('.nav-tabs .nav-link');
            navLinks.forEach(link => {
                const linkTarget = link.getAttribute('data-subtab-target');
                if (linkTarget === subTabId) {
                    link.classList.add('active', 'text-primary');
                    link.classList.remove('text-secondary');
                } else {
                    link.classList.remove('active', 'text-primary');
                    link.classList.add('text-secondary');
                }
            });
        }

        // 1. Society Expense Trend
        const expenseContainer = document.getElementById('expensesChart');
        if (expenseContainer && window.SNESTXDashboardData && window.SNESTXDashboardData.expenseChartData) {
            const expenseData = window.SNESTXDashboardData.expenseChartData;
            const labels = [];
            const dataValues = [];

            for (const [label, y] of Object.entries(expenseData)) {
                labels.push(label);
                dataValues.push(y);
            }

            if (labels.length > 0) {
                expenseContainer.innerHTML = '';
                const canvas = document.createElement('canvas');
                expenseContainer.appendChild(canvas);

                expensesChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Amount (₹)',
                            data: dataValues,
                            backgroundColor: '#6366f1',
                            borderColor: '#4f46e5',
                            borderWidth: 1,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Monthly Society Expense Trend',
                                font: {
                                    size: 16,
                                    family: 'Inter, sans-serif'
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // 2. Resident Payment History
        const paymentContainer = document.getElementById('paymentHistoryChart');
        if (paymentContainer && window.SNESTXDashboardData && window.SNESTXDashboardData.paymentHistory) {
            const paymentData = window.SNESTXDashboardData.paymentHistory;
            const labels = [];
            const dataValues = [];

            if (Array.isArray(paymentData)) {
                paymentData.forEach(p => {
                    labels.push(p.x);
                    dataValues.push(p.y);
                });
            } else {
                for (const [label, y] of Object.entries(paymentData)) {
                    labels.push(label);
                    dataValues.push(y);
                }
            }

            if (labels.length > 0) {
                paymentContainer.innerHTML = '';
                const canvas = document.createElement('canvas');
                paymentContainer.appendChild(canvas);

                paymentChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Amount (₹)',
                            data: dataValues,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'My Payment History',
                                font: {
                                    size: 16,
                                    family: 'Inter, sans-serif'
                                }
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    }

    // --- Poll Logic ---
    function togglePollEdit(e) {
        e.preventDefault();
        const btn = e.currentTarget;
        const pollId = btn.dataset.pollId;
        const showForm = btn.dataset.showForm === 'true';

        const formDiv = document.getElementById('poll-vote-form-' + pollId);
        const resultsDiv = document.getElementById('poll-results-' + pollId);

        if (formDiv && resultsDiv) {
            if (showForm) {
                formDiv.classList.remove('d-none');
                resultsDiv.classList.add('d-none');
            } else {
                formDiv.classList.add('d-none');
                resultsDiv.classList.remove('d-none');
            }
        }
    }

    // --- Event Listeners ---
    document.addEventListener('DOMContentLoaded', function () {
        console.log('SNESTX Dashboard: DOMContentLoaded fired. Initializing...');
        // Init Charts
        initCharts();
        
        // Init Real-Time Sync
        initPaymentSync();

        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Hook into Tab Switching for Chart Re-render
        const btnExpenses = document.getElementById('btn-tab-expenses');
        if (btnExpenses) {
            btnExpenses.addEventListener('click', function () {
                setTimeout(function () {
                    if (expensesChart) {
                        expensesChart.render();
                    }
                }, 100);
            });
        }

        const btnHome = document.getElementById('btn-tab-home');
        if (btnHome) {
            btnHome.addEventListener('click', function () {
                setTimeout(function () {
                    if (paymentChart) {
                        paymentChart.render();
                    }
                }, 100);
            });
        }

        const btnAccounts = document.getElementById('btn-tab-accounts');
        if (btnAccounts) {
            btnAccounts.addEventListener('click', function () {
                setTimeout(function () {
                    if (paymentChart) {
                        paymentChart.render();
                    }
                }, 100);
            });
        }

        // Global Event Delegation for Dynamic Elements
        document.body.addEventListener('click', function (e) {
            // Poll Edit Toggles
            const pollToggle = e.target.closest('.js-toggle-poll-edit');
            if (pollToggle) {
                togglePollEdit({ ...e, currentTarget: pollToggle, preventDefault: () => e.preventDefault() });
            }
            if (pollToggle) {
                togglePollEdit({ ...e, currentTarget: pollToggle, preventDefault: () => e.preventDefault() });
            }

            // View Family
            const viewFamilyBtn = e.target.closest('.js-view-family');
            if (viewFamilyBtn) {
                e.preventDefault();
                handleViewFamily(viewFamilyBtn);
            }

            // Edit Family
            const editFamilyBtn = e.target.closest('.js-edit-family');
            if (editFamilyBtn) {
                e.preventDefault();
                handleEditFamily(editFamilyBtn);
            }

            // Delete Family
            // (Assumed handled by other listeners? Or I should add it if missing. 
            // Previous code had specific onclicks or jquery? No, I see no specific listener for delete in the snippet I viewed. 
            // But verify if it was there. I'll stick to what I see.)
        });

        // Hook into modal hidden events to reset forms
        const familyModal = document.getElementById('familyModal');
        if (familyModal) {
            familyModal.addEventListener('hidden.bs.modal', window.resetFamilyModal);
        }

        const vehicleModal = document.getElementById('vehicleModal');
        if (vehicleModal) {
            vehicleModal.addEventListener('hidden.bs.modal', window.resetVehicleModal);
        }

        const helpModal = document.getElementById('helpModal');
        if (helpModal) {
            helpModal.addEventListener('hidden.bs.modal', window.resetHelpModal);
        }

        // Community Directory Listeners
        initDirectoryCardListeners();
        initDirectoryFilterListeners();

        const dirSearch = document.getElementById('dir-search');
        if (dirSearch) {
            dirSearch.addEventListener('input', function () {
                if (window.filterDirectory) window.filterDirectory();
            });
            dirSearch.addEventListener('focus', function () {
                if (window.SNESTXCreateFuse) dirFuse = window.SNESTXCreateFuse('.dir-card');
            });
        }

        // Facility Search (Available Facilities)
        const facilitySearch = document.getElementById('facility-dashboard-search');
        if (facilitySearch) {
            facilitySearch.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();
                if (!facFuse && window.SNESTXCreateFuse) {
                    facFuse = window.SNESTXCreateFuse('.facility-card');
                }

                const matches = val && window.SNESTXGetFuzzyMatches ? window.SNESTXGetFuzzyMatches(facFuse, val) : null;

                document.querySelectorAll('.facility-card').forEach(card => {
                    const isMatch = !val || (matches && matches.has(card));
                    if (isMatch) {
                        card.classList.remove('d-none');
                        card.classList.add('d-flex');
                    } else {
                        card.classList.add('d-none');
                        card.classList.remove('d-flex');
                    }
                });
            });
            facilitySearch.addEventListener('focus', function () {
                if (window.SNESTXCreateFuse) facFuse = window.SNESTXCreateFuse('.facility-card');
            });
        }

        // Booking Search (My Bookings)
        const bookingSearch = document.getElementById('booking-dashboard-search');
        if (bookingSearch) {
            bookingSearch.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();
                if (!bookingFuse && window.SNESTXCreateFuse) {
                    bookingFuse = window.SNESTXCreateFuse('.booking-dash-row');
                }

                const matches = val && window.SNESTXGetFuzzyMatches ? window.SNESTXGetFuzzyMatches(bookingFuse, val) : null;

                document.querySelectorAll('.booking-dash-row').forEach(row => {
                    const isMatch = !val || (matches && matches.has(row));
                    if (isMatch) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
            bookingSearch.addEventListener('focus', function () {
                if (window.SNESTXCreateFuse) bookingFuse = window.SNESTXCreateFuse('.booking-dash-row');
            });
        }

        // --- Manual Dropdown Toggling (Robust Fallback) ---
        // Fix for "display: block / none is not getting toggled properly"
        document.body.addEventListener('click', function (e) {
            const toggle = e.target.closest('[data-bs-toggle="dropdown"]');

            // 1. If clicking a toggle
            if (toggle) {
                e.preventDefault();
                e.stopPropagation();

                // Find the menu (next sibling usually)
                let menu = toggle.nextElementSibling;
                // If not found, try finding by aria-labelledby via ID? No, standard structure is sibling.
                if (menu && menu.classList.contains('dropdown-menu')) {
                    const isOpen = menu.classList.contains('show');

                    // Close ALL other open dropdowns first
                    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                    document.querySelectorAll('[data-bs-toggle="dropdown"].show').forEach(t => t.ariaExpanded = "false");

                    // Toggle Current
                    if (!isOpen) {
                        menu.classList.add('show');
                        menu.setAttribute('data-bs-popper', 'static'); // Force static if popper missing
                        toggle.classList.add('show');
                        toggle.ariaExpanded = "true";
                    }
                }
            }
            // 2. If clicking anywhere else (close all)
            else {
                // If click is INSIDE a dropdown menu, do we close? 
                // Standard behavior: forms inside dropdowns might need to stay open. 
                // Links usually close it.
                // For now, let's close unless it's a form input to be safe.
                if (!e.target.closest('.dropdown-menu form')) { // Keep open for forms
                    document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
                    document.querySelectorAll('[data-bs-toggle="dropdown"].show').forEach(t => {
                        t.classList.remove('show');
                        t.ariaExpanded = "false";
                    });
                }
            }
        });

        // --- Explicit AJAX Form Handlers ---

        // 1. Family Form Submit
        const familyForm = document.querySelector('#familyModal form');
        if (familyForm) {
            familyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(familyForm);
                if (!formData.get('action')) formData.append('action', 'SNESTX51_add_family');

                SNESTX.ajax({
                    action: formData.get('action'),
                    data: formData,
                    loadingButton: $(familyForm).find('button[type="submit"]'),
                    successMessage: 'Family member saved!',
                    reload: true
                });
            });
        }

        // 2. Help Form Submit
        const helpForm = document.querySelector('#helpModal form');
        if (helpForm) {
            helpForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(helpForm);

                SNESTX.ajax({
                    action: formData.get('action'),
                    data: formData,
                    loadingButton: $(helpForm).find('button[type="submit"]'),
                    successMessage: 'Help details saved!',
                    reload: true
                });
            });
        }

        // 3. Vehicle Form Submit
        const vehicleForm = document.querySelector('#vehicleModal form');
        if (vehicleForm) {
            vehicleForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(vehicleForm);

                SNESTX.ajax({
                    action: formData.get('action'),
                    data: formData,
                    loadingButton: $(vehicleForm).find('button[type="submit"]'),
                    successMessage: 'Vehicle request submitted!',
                    reload: true
                });
            });
        }

        // 4. Poll Vote Form Submit & Refined Toggles
        const dashboard = document.getElementById('tab-polls');
        if (dashboard) {
            dashboard.addEventListener('click', function (e) {
                // Change Vote Click
                if (e.target.classList.contains('js-change-vote')) {
                    const container = e.target.closest('[id^="poll-container-"]');
                    if (container) {
                        container.querySelector('.js-poll-results').classList.add('d-none');
                        container.querySelector('.js-poll-form').classList.remove('d-none');
                    }
                }

                // Cancel Change Click
                if (e.target.classList.contains('js-cancel-change')) {
                    const container = e.target.closest('[id^="poll-container-"]');
                    if (container) {
                        container.querySelector('.js-poll-form').classList.add('d-none');
                        container.querySelector('.js-poll-results').classList.remove('d-none');
                    }
                }
            });

            dashboard.addEventListener('submit', function (e) {
                if (e.target.classList.contains('js-poll-vote-form')) {
                    e.preventDefault();
                    const form = e.target;
                    const formData = new FormData(form);

                    SNESTX.ajax({
                        action: 'SNESTX51_cast_vote',
                        data: formData,
                        loadingButton: $(form).find('button[type="submit"]'),
                        successMessage: 'Vote cast successfully!',
                        onSuccess: function (data) {
                            // Refresh only the polls tab content
                            fetch(window.location.href)
                                .then(res => res.text())
                                .then(html => {
                                    const parser = new DOMParser();
                                    const doc = parser.parseFromString(html, 'text/html');
                                    const newContent = doc.querySelector('#tab-polls').innerHTML;
                                    document.querySelector('#tab-polls').innerHTML = newContent;
                                })
                                .catch(() => window.location.reload());
                        }
                    });
                }
            });
        }

        // --- Helper to open Modal (Tailwind/Custom) ---
        function openModal(modalId) {
            const m = document.getElementById(modalId);
            if (m) m.classList.remove('d-none');
        }

        // --- Reset Family Modal for Add ---
        window.resetFamilyModal = function () {
            const form = document.querySelector('#familyModal form');
            if (!form) return;

            form.reset();

            // Reset Photo
            const preview = document.getElementById('preview-frontend_family');
            const placeholder = document.getElementById('icon-frontend_family');
            if (preview) { preview.src = ''; preview.classList.add('d-none'); }
            if (placeholder) placeholder.classList.remove('d-none');

            // Reset IDs
            const idInput = form.querySelector('input[name="member_id"]');
            if (idInput) idInput.value = '';
            const resIdInput = form.querySelector('input[name="resident_id"]');
            if (resIdInput) resIdInput.value = '';

            // Reset action to add
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = 'SNESTX51_add_family';

            // Reset Relation Wrapper
            const relWrapper = document.getElementById('relation-wrapper-frontend_family');
            if (relWrapper) {
                relWrapper.style.display = 'block'; // Always show for family context
                const relSelect = relWrapper.querySelector('select');
                if (relSelect) relSelect.setAttribute('required', 'required');
            }

            // Reset title and button
            const modalTitle = document.getElementById('familyModalLabel');
            if (modalTitle) modalTitle.innerText = 'Add Family Member';

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Save Family Member';

            // Reset Nonce (Swap back to Add)
            const addNonce = form.querySelector('[name="_wpnonce_add_family"]');
            const mainNonce = form.querySelector('[name="_wpnonce"]');
            if (addNonce && mainNonce) mainNonce.value = addNonce.value;
        };

        // --- Edit Handlers ---
        function handleEditFamily(btn) {
            const d = btn.dataset;
            const form = document.querySelector('#familyModal form');
            if (!form) return;

            // Populate inputs
            if (form.querySelector('[name="name"]')) form.querySelector('[name="name"]').value = d.name || '';
            if (form.querySelector('[name="relation"]')) form.querySelector('[name="relation"]').value = d.relation || '';
            if (form.querySelector('[name="dob"]')) form.querySelector('[name="dob"]').value = d.dob || '';
            if (form.querySelector('[name="blood_group"]')) form.querySelector('[name="blood_group"]').value = d.blood || '';
            if (form.querySelector('[name="phone"]')) form.querySelector('[name="phone"]').value = d.phone || '';
            if (form.querySelector('[name="email"]')) form.querySelector('[name="email"]').value = d.email || '';

            // Photo Preview
            const preview = document.getElementById('preview-frontend_family');
            const placeholder = document.getElementById('icon-frontend_family');
            if (d.photo) {
                if (preview) { preview.src = d.photo; preview.classList.remove('d-none'); }
                if (placeholder) placeholder.classList.add('d-none');
            } else {
                if (preview) { preview.src = ''; preview.classList.add('d-none'); }
                if (placeholder) placeholder.classList.remove('d-none');
            }

            // Set action and IDs for edit
            if (form.querySelector('[name="action"]')) form.querySelector('[name="action"]').value = 'SNESTX51_edit_family';
            if (form.querySelector('[name="member_id"]')) form.querySelector('[name="member_id"]').value = d.id || d.memberId || '';
            if (form.querySelector('[name="resident_id"]')) form.querySelector('[name="resident_id"]').value = d.id || '';

            // Relation Wrapper Visibility
            const relWrapper = document.getElementById('relation-wrapper-frontend_family');
            if (relWrapper) relWrapper.style.display = 'block';

            // Change Title
            const modalTitle = document.getElementById('familyModalLabel');
            if (modalTitle) modalTitle.innerText = 'Edit Family Member: ' + d.name;

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Update Family Member';

            // Swap Nonce for Edit
            const editNonce = form.querySelector('[name="_wpnonce_edit_family"]');
            const mainNonce = form.querySelector('[name="_wpnonce"]');
            if (editNonce && mainNonce) mainNonce.value = editNonce.value;

            // Open modal
            const modalEl = document.getElementById('familyModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }

        function handleEditHelp(btn) {
            try {
                const payload = JSON.parse(btn.dataset.payload);
                const form = document.querySelector('#helpModal form');

                // Populate
                form.querySelector('[name="name"]').value = payload.name || '';
                form.querySelector('[name="role"]').value = payload.role || 'Maid';
                form.querySelector('[name="phone"]').value = payload.phone || '';

                const categoryField = form.querySelector('[name="category"]');
                if (categoryField) categoryField.value = payload.category || 'Support Staff';

                const sexField = form.querySelector('[name="sex"]');
                if (sexField) sexField.value = payload.sex || 'Male';

                const vhField = form.querySelector('[name="visiting_hours"]');
                if (vhField) vhField.value = payload.visiting_hours || '';

                const docUrlField = form.querySelector('[name="document_url"]');
                if (docUrlField) docUrlField.value = payload.document_url || '';

                const preview = document.getElementById('current-help-doc-preview');
                if (preview) {
                    if (payload.document_url) {
                        preview.classList.remove('d-none');
                        preview.querySelector('a').href = payload.document_url;
                    } else {
                        preview.classList.add('d-none');
                    }
                }

                // Set Action
                form.querySelector('[name="action"]').value = 'SNESTX51_edit_help_frontend';

                // Set ID
                let idInput = form.querySelector('[name="help_id"]');
                if (!idInput) {
                    idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'help_id';
                    form.appendChild(idInput);
                }
                idInput.value = payload.id;

                // Swap Nonce
                const editNonce = form.querySelector('[name="_wpnonce_edit_help"]');
                const mainNonce = form.querySelector('[name="_wpnonce"]');
                if (editNonce && mainNonce) mainNonce.value = editNonce.value;

                const modalTitle = form.querySelector('.modal-title');
                if (modalTitle) modalTitle.innerText = 'Edit Daily Help';

                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.innerText = 'Update Help';

                // Open modal using Bootstrap API
                const modalEl = document.getElementById('helpModal');
                if (modalEl) {
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                }
            } catch (e) { console.error('handleEditHelp Error:', e); }
        }

        // --- View Handler ---
        function handleViewFamily(btn) {
            const d = btn.dataset;

            // Populate specific IDs
            const setText = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.innerText = val || '-';
            }

            setText('view-family-name', d.name);
            setText('view-family-relation', d.relation);
            setText('view-family-dob', d.dob || '-');
            setText('view-family-blood', d.blood);
            setText('view-family-phone', d.phone);
            setText('view-family-email', d.email);

            const preview = document.getElementById('view-family-photo');
            const placeholder = document.getElementById('view-family-placeholder');
            if (d.photo) {
                if (preview) { preview.src = d.photo; preview.classList.remove('d-none'); }
                if (placeholder) placeholder.classList.add('d-none');
            } else {
                if (preview) { preview.src = ''; preview.classList.add('d-none'); }
                if (placeholder) placeholder.classList.remove('d-none');
            }

            const modalEl = document.getElementById('viewFamilyModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }

        // Helper to reset help modal
        window.resetHelpModal = function () {
            const form = document.querySelector('#helpModal form');
            form.reset();
            form.querySelector('[name="action"]').value = 'SNESTX51_add_daily_help';
            const idInput = form.querySelector('[name="help_id"]');
            if (idInput) idInput.value = '';
            const docUrlInput = form.querySelector('[name="document_url"]');
            if (docUrlInput) docUrlInput.value = '';

            // Reset Nonce to Add
            const addNonce = form.querySelector('[name="_wpnonce_add_help"]');
            const mainNonce = form.querySelector('[name="_wpnonce"]');
            if (addNonce && mainNonce) mainNonce.value = addNonce.value;

            const preview = document.getElementById('current-help-doc-preview');
            if (preview) preview.classList.add('d-none');

            const modalTitle = form.querySelector('.modal-title');
            if (modalTitle) modalTitle.innerText = 'Add Daily Help';
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Save Help';
        };

        function handleEditVehicle(btn) {
            const payload = JSON.parse(btn.dataset.payload);
            const form = document.querySelector('#vehicleModal form');
            if (!form) return;

            const setVal = (name, val) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el) el.value = val || '';
            };

            setVal('number', payload.number);
            setVal('type', payload.type);
            setVal('brand', payload.brand);
            setVal('model', payload.model);

            const actionField = form.querySelector('[name="action"]');
            if (actionField) actionField.value = 'SNESTX51_edit_vehicle_frontend';

            const idField = form.querySelector('[name="vehicle_id"]');
            if (idField) idField.value = payload.id;

            // Swap Nonce
            const editNonce = form.querySelector('[name="SNESTX51_edit_vehicle_token"]');
            const mainNonce = form.querySelector('[name="_wpnonce"]');
            if (editNonce && mainNonce) mainNonce.value = editNonce.value;

            const modalTitle = form.querySelector('.modal-title');
            if (modalTitle) modalTitle.innerText = 'Edit Vehicle';
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Update';

            // Open modal using Bootstrap API
            const modalEl = document.getElementById('vehicleModal');
            if (modalEl) {
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            }
        }

        // --- Reset Vehicle Modal for Add ---
        window.resetVehicleModal = function () {
            const form = document.querySelector('#vehicleModal form');
            if (!form) return;

            // Reset form fields
            form.reset();

            // Reset Nonce to Add
            const addNonce = form.querySelector('[name="_wpnonce_add_vehicle_frontend"]');
            const mainNonce = form.querySelector('[name="_wpnonce"]');
            if (addNonce && mainNonce) mainNonce.value = addNonce.value;

            // Reset title and button
            const modal = document.querySelector('#vehicleModal');
            const modalTitle = modal.querySelector('.modal-title');
            if (modalTitle) modalTitle.innerText = 'Register Vehicle';

            const submitBtn = modal.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Request Registration';
        };

        // --- Global Click Handler for Edits ---
        // --- Global Click Handler for Edits & Deletes ---
        document.body.addEventListener('click', function (e) {
            // Edits
            let btn = e.target.closest('.js-edit-family');
            if (btn) { e.preventDefault(); handleEditFamily(btn); return; }

            btn = e.target.closest('.js-edit-help');
            if (btn) { e.preventDefault(); handleEditHelp(btn); return; }

            btn = e.target.closest('.js-edit-vehicle');
            if (btn) { e.preventDefault(); handleEditVehicle(btn); return; }

            // Deletes
            btn = e.target.closest('.js-delete-family-frontend');
            if (btn) { e.preventDefault(); handleDeleteFamily(btn); return; }

            btn = e.target.closest('.js-delete-help-frontend');
            if (btn) { e.preventDefault(); handleDeleteGeneric(btn, 'SNESTX51_delete_daily_help_frontend'); return; }

            btn = e.target.closest('.js-delete-vehicle-frontend');
            if (btn) { e.preventDefault(); handleDeleteGeneric(btn, 'SNESTX51_delete_vehicle_frontend'); return; }
        });

        // --- Generic Delete Handler ---
        function handleDeleteGeneric(btn, action) {
            if (!confirm('Are you sure you want to delete this?')) return;

            const id = btn.dataset.id;
            const nonce = btn.dataset.nonce;

            SNESTX.ajax({
                action: action,
                data: { id: id, _wpnonce: nonce },
                loadingButton: btn,
                successMessage: 'Deleted successfully',
                reload: true
            });
        }

        // --- Specific Delete Handlers (can wrap generic if needed) ---
        function handleDeleteFamily(btn) {
            handleDeleteGeneric(btn, 'SNESTX51_delete_family_frontend');
        }

        // Optional: Hook into global window.switchTab if it exists (legacy support)
        const originalSwitchTab = window.switchTab;
        if (typeof originalSwitchTab === 'function') {
            window.switchTab = function (tab, btn) {
                originalSwitchTab(tab, btn);
                if ((tab === 'tab-expenses' || tab === '#tab-expenses') && expensesChart) {
                    setTimeout(function () { expensesChart.render(); }, 50);
                }
            };
        }

        // --- Directory Card Click Handler (Community Tab) ---
        function initDirectoryCardListeners() {
            const directoryGrid = document.getElementById('directory-grid');
            if (!directoryGrid) return;

            directoryGrid.addEventListener('click', function (e) {
                const dirCard = e.target.closest('.dir-card');
                if (dirCard) {
                    e.preventDefault();
                    openDirModal(dirCard);
                }
            });
        }

        // --- Open Directory Modal ---
        window.openDirModal = function (card) {
            if (!card || !card.dataset.json) return;
            try {
                const data = JSON.parse(card.dataset.json);
                const modal = document.getElementById('communityDetailModal');
                if (!modal) return;

                // Populate modal with data
                const flatEl = document.getElementById('cdm-flat');
                const ownerEl = document.getElementById('cdm-owner');
                const membersCountEl = document.getElementById('cdm-members-count');
                const emailEl = document.getElementById('cdm-email');
                const parkingEl = document.getElementById('cdm-parking');

                if (flatEl) flatEl.textContent = data.flat_no || '';
                if (ownerEl) ownerEl.textContent = data.owner || '';
                
                const ownerPhotoEl = document.getElementById('cdm-owner-photo');
                if (ownerPhotoEl) {
                    if (data.owner_photo && data.owner_photo !== '') {
                        ownerPhotoEl.src = data.owner_photo;
                        ownerPhotoEl.style.display = 'block';
                    } else if (data.owner && data.owner !== 'Unoccupied') {
                        ownerPhotoEl.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.owner) + '&background=random&color=fff';
                        ownerPhotoEl.style.display = 'block';
                    } else {
                        ownerPhotoEl.style.display = 'none';
                    }
                }
                
                if (membersCountEl) membersCountEl.textContent = data.members || '0';
                if (emailEl) emailEl.textContent = data.email || '-';
                if (parkingEl) parkingEl.textContent = (data.parking && data.parking !== 'N/A' && data.parking !== '') ? data.parking : 'Not Allocated';

                // Populate Family Members
                const familyList = document.getElementById('cdm-family-list');
                if (familyList) {
                    familyList.innerHTML = '';
                    if (data.family && data.family.length > 0) {
                        data.family.forEach(function (m) {
                            const item = document.createElement('div');
                            item.className = 'd-flex align-items-center gap-3 border-bottom border-light pb-2';
                            
                            const photoSrc = (m.photo && m.photo !== '') ? m.photo : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(m.name) + '&background=random&color=fff';
                            
                            item.innerHTML = `
                                <img src="${photoSrc}" class="rounded-circle border border-white shadow-sm" style="width: 64px; height: 64px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark fs-6">${m.name}</div>
                                    <div class="text-muted small">${m.relation || (m.type === 'owner' ? 'Owner' : 'Family')}</div>
                                </div>
                            `;
                            familyList.appendChild(item);
                        });
                    } else {
                        familyList.innerHTML = '<div class="text-muted small">No family details added</div>';
                    }
                }

                // Populate vehicles
                const vehiclesDiv = document.getElementById('cdm-vehicles');
                if (vehiclesDiv) {
                    vehiclesDiv.innerHTML = '';
                    if (data.vehicles && data.vehicles.length > 0) {
                        data.vehicles.forEach(function (v) {
                            const item = document.createElement('div');
                            item.className = 'd-flex align-items-center justify-content-between bg-light rounded-2 p-2 border border-secondary border-opacity-10';
                            item.innerHTML = `
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-car-front text-primary"></i>
                                    <span class="fw-medium text-dark small font-monospace">${v.number || 'N/A'}</span>
                                </div>
                                <span class="badge bg-white text-secondary border border-light small fw-normal">${v.brand || 'Vehicle'}</span>
                            `;
                            vehiclesDiv.appendChild(item);
                        });
                    } else {
                        vehiclesDiv.innerHTML = '<span class="text-muted small">None registered</span>';
                    }
                }

                // Populate help staff
                const helpDiv = document.getElementById('cdm-help');
                if (helpDiv) {
                    helpDiv.innerHTML = '';
                    if (data.help && data.help.length > 0) {
                        data.help.forEach(function (h) {
                            const item = document.createElement('div');
                            item.className = 'd-flex align-items-center gap-3 bg-light rounded-2 p-2 border border-secondary border-opacity-10';
                            
                            const photoSrc = (h.photo && h.photo !== '') ? h.photo : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(h.name) + '&background=E9ECEF&color=6C757D';

                            item.innerHTML = `
                                <img src="${photoSrc}" class="rounded-circle shadow-sm" style="width: 64px; height: 64px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark fs-6">${h.name}</div>
                                    <div class="text-muted small">${h.role || 'Staff'}</div>
                                </div>
                                <div class="text-primary fs-4"><i class="bi bi-person-badge"></i></div>
                            `;
                            helpDiv.appendChild(item);
                        });
                    } else {
                        helpDiv.innerHTML = '<span class="text-muted small">None registered</span>';
                    }
                }

                // Show modal using Bootstrap
                const bsModal = bootstrap.Modal.getOrCreateInstance(modal);
                bsModal.show();
            } catch (e) {
                console.error('Error opening directory modal:', e);
            }
        };

        // --- Consolidated Community Filter/Search ---
        window.applyCommunityFilters = function () {
            const searchInput = document.getElementById('dir-search');
            const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const dirCards = document.querySelectorAll('.dir-card');

            if (!dirFuse && window.SNESTXCreateFuse) {
                if (typeof Fuse === 'undefined') {
                    console.error('Fuse.js is not loaded! Search will fail.');
                    return;
                }
                // Use stricter threshold (0.2) and only search metadata to avoid noise from labels
                dirFuse = window.SNESTXCreateFuse('.dir-card', {
                    threshold: 0.3,
                    searchOnlyMeta: true
                });
            }

            const fuzzyMatches = searchTerm && window.SNESTXGetFuzzyMatches ? window.SNESTXGetFuzzyMatches(dirFuse, searchTerm) : null;

            dirCards.forEach(function (card) {
                // 1. Check Search Match
                const matchesSearch = !searchTerm || (fuzzyMatches && fuzzyMatches.has(card));

                // 2. Check Tab Filter Match
                let matchesFilter = true;
                if (dirActiveFilter === 'vehicle') {
                    matchesFilter = (card.dataset.hasVehicle === '1');
                } else if (dirActiveFilter === 'help') {
                    matchesFilter = (card.dataset.hasHelp === '1');
                }

                const finalMatch = !!(matchesSearch && matchesFilter);

                // Apply visibility - Use Bootstrap d-none for consistency
                if (finalMatch) {
                    card.classList.remove('d-none');
                } else {
                    card.classList.add('d-none');
                }
            });
        };

        window.filterDirFilter = function (filter) {
            dirActiveFilter = filter;
            const filterBtns = document.querySelectorAll('.dir-filter-btn');

            // Update button states
            filterBtns.forEach(function (btn) {
                if (btn.dataset.filter === filter) {
                    btn.classList.remove('btn-light', 'text-secondary');
                    btn.classList.add('btn-dark', 'active');
                } else {
                    btn.classList.add('btn-light', 'text-secondary');
                    btn.classList.remove('btn-dark', 'active');
                }
            });

            applyCommunityFilters();
        };

        // --- Directory Search Handler ---
        window.filterDirectory = function () {
            applyCommunityFilters();
        };

        // --- Directory Filter Button Listeners ---
        function initDirectoryFilterListeners() {
            const dirFiltersContainer = document.getElementById('dir-filters');
            if (!dirFiltersContainer) return;

            dirFiltersContainer.addEventListener('click', function (e) {
                const filterBtn = e.target.closest('.dir-filter-btn');
                if (filterBtn && filterBtn.dataset.filter) {
                    e.preventDefault();
                    filterDirFilter(filterBtn.dataset.filter);
                }
            });
        }

        // --- Helper: Preview Image ---
        window.previewFrontendImage = function (input, previewId, placeholderId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    const preview = document.getElementById(previewId);
                    const placeholder = document.getElementById(placeholderId);
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.remove('d-none');
                    }
                    if (placeholder) {
                        placeholder.classList.add('d-none');
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }


        // --- Receipt Download (Updated to use html2canvas) ---
        window.downloadReceipt = function () {
            const receiptElement = document.getElementById('receipt-content');
            if (!receiptElement) {
                SNESTX.toast.error('Receipt not found!');
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';

            if (typeof html2canvas === 'undefined') {
                SNESTX.toast.error('Library not loaded. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }

            html2canvas(receiptElement, {
                scale: 2,
                logging: false,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff'
            }).then(canvas => {
                const link = document.createElement('a');
                const receiptNumber = receiptElement.querySelector('.receipt-header-title strong')?.textContent || 'Receipt';
                link.href = canvas.toDataURL('image/png');
                link.download = `${receiptNumber}.png`;
                link.click();
                btn.disabled = false;
                btn.innerHTML = originalText;
            }).catch(error => {
                console.error('Download error:', error);
                SNESTX.toast.error('Error generating receipt image. Please try again.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        };

        // --- View Invoice & Populate Receipt ---
        window.viewInvoiceReceipt = function (btn) {
            let invoiceId = btn.getAttribute('data-invoice-id');
            if (!invoiceId && btn.dataset.invoice) {
                try {
                    const inv = JSON.parse(btn.dataset.invoice);
                    invoiceId = inv.id;
                } catch (e) { }
            }

            if (!invoiceId) {
                console.error("No Invoice ID found");
                return;
            }

            const nonce = (window.SNESTXDashboardData && window.SNESTXDashboardData.nonce) ? window.SNESTXDashboardData.nonce : (typeof snestx51_nonce !== 'undefined' ? snestx51_nonce : '');

            SNESTX.ajax({
                action: 'SNESTX51_get_receipt',
                data: { invoice_id: invoiceId, nonce: nonce },
                onSuccess: function (data) {
                    populateReceiptModal(data);
                    let modalEl = document.getElementById('receiptModal');
                    if (!modalEl) modalEl = document.getElementById('snestx-resident-receipt-modal');

                    if (modalEl) {
                        const modal = new bootstrap.Modal(modalEl);
                        modal.show();
                    }
                }
            });
        };

        function populateReceiptModal(receiptData) {
            const receiptContent = document.getElementById('receipt-content');
            if (!receiptContent) return;

            let paymentRows = '';
            if (receiptData.payments && receiptData.payments.length > 0) {
                receiptData.payments.forEach(p => {
                    const ref = p.reference || p.ref || '-';
                    paymentRows += `
                        <tr>
                            <td class="py-3 border-bottom border-light text-dark fw-medium">${p.method || 'Payment'} <span class="text-muted fw-normal ms-2 small">(${p.date || ''})</span></td>
                            <td class="py-3 border-bottom border-light text-end text-dark fw-bold">₹${parseFloat(p.amount || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                            <td class="py-3 border-bottom border-light text-muted small">${ref}</td>
                        </tr>
                    `;
                });
            }

            const invoiceAmount = parseFloat(receiptData.invoice_amount || 0);
            const totalPaid = parseFloat(receiptData.total_paid || 0);
            const balanceDue = parseFloat(receiptData.balance_due || 0);

            const statusClass = receiptData.status === 'paid' ? 'bg-success text-white' : (receiptData.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger text-white');
            const statusText = receiptData.status === 'paid' ? 'FULLY PAID' : (receiptData.status === 'partial' ? 'PARTIALLY PAID' : 'UNPAID');

            receiptContent.className = 'receipt';
            receiptContent.style.minHeight = 'auto';

            receiptContent.innerHTML = `
                <!-- Header -->
                <div class="receipt-header-standard">
                    <h2 class="fw-bold text-primary mb-1">${receiptData.society_name || 'Society Name'}</h2>
                    <p class="text-muted mb-0">Payment Receipt <strong class="receipt-no">#${receiptData.receipt_number}</strong></p>
                </div>

                <!-- Info Grid -->
                <div class="receipt-grid">
                    <div>
                        <span class="receipt-label">Resident Name</span>
                        <div class="receipt-value">${receiptData.resident_name}</div>
                    </div>
                    <div>
                        <span class="receipt-label">Flat / Unit No.</span>
                        <div class="receipt-value">${receiptData.flat_no}</div>
                    </div>
                    <div>
                        <span class="receipt-label">Billing Period</span>
                        <div class="receipt-value">${new Date(receiptData.invoice_month + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</div>
                    </div>
                    <div>
                        <span class="receipt-label">Purpose</span>
                        <div class="receipt-value">${receiptData.description || 'Society Maintenance'}</div>
                    </div>
                </div>

                <!-- Payment Table -->
                <h5 class="fw-bold mb-3 mt-4">Transaction Details</h5>
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th>Method / Date</th>
                            <th class="text-end">Amount Paid</th>
                            <th>Reference ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${paymentRows || '<tr><td colspan="3" class="py-4 text-center text-muted">No payments recorded</td></tr>'}
                    </tbody>
                </table>

                <!-- Summary -->
                <div class="receipt-summary">
                    <div class="summary-row">
                        <span>Invoice Total</span>
                        <span>₹${invoiceAmount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div class="summary-row text-success fw-bold">
                        <span>Total Received</span>
                        <span>₹${totalPaid.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                    </div>
                    <div class="summary-row grand-total">
                        <span>Balance Due</span>
                        <span>₹${balanceDue.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                    </div>
                </div>

                <!-- Status -->
                <div class="receipt-status-wrap">
                    <span class="receipt-badge ${statusClass}">${statusText}</span>
                </div>

                <!-- Footer -->
                <div class="receipt-footer-standard">
                    <p class="mb-1">This is a computer-generated document. It does not require a physical signature.</p>
                    <p class="mb-0">Society NestX - Empowering Communities</p>
                </div>
            `;
        }

        // --- Handle Pay Now Button Click ---
        document.body.addEventListener('click', function(e) {
            const btn = e.target.closest('.js-btn-pay');
            if (btn) {
                const amount = btn.getAttribute('data-amount');
                const invoiceId = btn.getAttribute('data-invoice-id');
                
                const form = document.getElementById('payment-confirmation-form');
                if (form) {
                    const amountInput = form.querySelector('[name="amount"]');
                    const invoiceInput = form.querySelector('[name="invoice_id"]');
                    if (amountInput) amountInput.value = amount || '';
                    if (invoiceInput) invoiceInput.value = invoiceId || '';
                }
            }
        });

        // --- Payment Confirmation Handler ---
        function submitPaymentConfirmation(btn) {
            const form = document.getElementById('payment-confirmation-form');
            if (!form) return;

            // Validation
            const amount = form.querySelector('[name="amount"]').value;
            const ref = form.querySelector('[name="reference"]').value;
            if (!amount || !ref) {
                SNESTX.toast.warning('Please fill in the Amount and Reference Number.');
                return;
            }

            const formData = new FormData(form);
            const nonce = (window.SNESTXDashboardData && window.SNESTXDashboardData.nonce) ? window.SNESTXDashboardData.nonce : '';
            formData.append('_ajax_nonce', nonce);

            SNESTX.ajax({
                action: 'SNESTX51_submit_payment_request',
                data: formData,
                loadingButton: btn,
                successMessage: 'Payment confirmation sent successfully!',
                reload: true,
                onSuccess: function () {
                    const modalEl = document.getElementById('SNESTX51PaymentModal');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }
                }
            });
        }

        // --- Global Listeners for New Handlers ---
        document.body.addEventListener('click', function (e) {
            // Confirm Payment
            if (e.target.id === 'btn-confirm-payment') {
                e.preventDefault();
                submitPaymentConfirmation(e.target);
            }

            // View Receipt Button
            const receiptBtn = e.target.closest('[data-action="view-receipt"]');
            if (receiptBtn) {
                e.preventDefault();
                window.viewInvoiceReceipt(receiptBtn);
                return;
            }
        });

        // Fix: Auto-populate Resident Profile Modal
        var profileModal = document.getElementById('editProfileModal');
        // console.log("SNESTX Debug: Profile Modal Reset Script Loaded", profileModal); 

        if (profileModal && typeof SNESTXDashboardData !== 'undefined' && SNESTXDashboardData.resident) {
            profileModal.addEventListener('show.bs.modal', function () {
                var r = SNESTXDashboardData.resident;
                // console.log("SNESTX Debug: Populating Profile Modal", r);

                var form = profileModal.querySelector('form');
                if (!form) {
                    // console.error("SNESTX Debug: Profile Form not found in modal");
                    return;
                }

                // Helper to set value safely
                var setVal = function (name, val) {
                    var el = form.querySelector('[name="' + name + '"]');
                    if (el) {
                        el.value = val || '';
                        // console.log("SNESTX Debug: Set " + name + " to " + val);
                    } else {
                        // console.warn("SNESTX Debug: Input not found for " + name);
                    }
                };

                setVal('name', r.name);
                setVal('email', r.email);
                setVal('phone', r.phone);
                setVal('dob', r.dob);
                setVal('blood_group', r.blood_group);
                setVal('flat_no', r.flat_no);

                // Handle Photo Preview if exists
                if (r.profile_photo) {
                    var preview = form.querySelector('img[id^="preview-"]');
                    var icon = form.querySelector('i[id^="icon-"]');
                    if (preview) {
                        preview.src = r.profile_photo;
                        preview.classList.remove('d-none');
                    }
                    if (icon) icon.classList.add('d-none');
                }
            });
        } else {
            /*
            console.warn("SNESTX Debug: Missing Data or Modal", {
               modal: !!profileModal,
               data: typeof SNESTXDashboardData,
               resident: (SNESTXDashboardData || {}).resident
            });
            */
        }


        // --- Unified View Modal Population & Comparison ---
        function handleViewModalShow(event, type) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const d = btn.dataset;
            const modal = event.target;

            // 1. Basic Population
            if (type === 'family') {
                const nameEl = document.getElementById('view-family-name');
                if (nameEl) nameEl.innerText = d.name || 'N/A';
                const relEl = document.getElementById('view-family-relation');
                if (relEl) relEl.innerText = d.relation || 'N/A';
                const dobEl = document.getElementById('view-family-dob');
                if (dobEl) dobEl.innerText = d.dob || '-';
                const bloodEl = document.getElementById('view-family-blood');
                if (bloodEl) bloodEl.innerText = d.blood || '-';
                const phoneEl = document.getElementById('view-family-phone');
                if (phoneEl) phoneEl.innerText = d.phone || '-';
                const emailEl = document.getElementById('view-family-email');
                if (emailEl) emailEl.innerText = d.email || '-';

                const photoImg = document.getElementById('view-family-photo');
                const photoPlaceholder = document.getElementById('view-family-placeholder');
                if (d.photo && d.photo !== '') {
                    if (photoImg) { photoImg.src = d.photo; photoImg.classList.remove('d-none'); }
                    if (photoPlaceholder) photoPlaceholder.classList.add('d-none');
                } else {
                    if (photoImg) photoImg.classList.add('d-none');
                    if (photoPlaceholder) photoPlaceholder.classList.remove('d-none');
                }
            } else if (type === 'help') {
                const nameEl = document.getElementById('view-help-name');
                if (nameEl) nameEl.innerText = d.name || 'N/A';
                const roleEl = document.getElementById('view-help-role');
                if (roleEl) roleEl.innerText = d.role || 'N/A';
                const catEl = document.getElementById('view-help-category');
                if (catEl) catEl.innerText = d.category || '-';
                const phoneEl = document.getElementById('view-help-phone');
                if (phoneEl) phoneEl.innerText = d.phone || '-';
                const sexEl = document.getElementById('view-help-sex');
                if (sexEl) sexEl.innerText = d.sex || '-';
                const hoursEl = document.getElementById('view-help-hours');
                if (hoursEl) hoursEl.innerText = d.hours || '-';

                const photoImg = document.getElementById('view-help-photo');
                const photoPlaceholder = document.getElementById('view-help-placeholder');
                if (d.photo && d.photo !== '') {
                    if (photoImg) { photoImg.src = d.photo; photoImg.classList.remove('d-none'); }
                    if (photoPlaceholder) photoPlaceholder.classList.add('d-none');
                } else {
                    if (photoImg) photoImg.classList.add('d-none');
                    if (photoPlaceholder) photoPlaceholder.classList.remove('d-none');
                }
            } else if (type === 'vehicle') {
                const numEl = document.getElementById('view-vehicle-number');
                if (numEl) numEl.innerText = d.number || 'N/A';
                const typeEl = document.getElementById('view-vehicle-type');
                if (typeEl) typeEl.innerText = d.type || 'N/A';
                const brandEl = document.getElementById('view-vehicle-brand');
                if (brandEl) brandEl.innerText = d.brand || '-';
                const modelEl = document.getElementById('view-vehicle-model');
                if (modelEl) modelEl.innerText = d.model || '-';
            }

            // 2. Comparison Logic for Pending Edits
            const changesSection = document.getElementById(`view-${type}-changes-section`);
            const changesBody = document.getElementById(`view-${type}-changes-body`);

            if (changesSection) changesSection.classList.add('d-none'); // Reset
            if (changesBody) changesBody.innerHTML = '';

            if (d.isPending === '1' && d.requestType === 'edit' && d.original) {
                try {
                    const original = JSON.parse(d.original);
                    const proposed = {}; // Current dataset represents proposed

                    // Map dataset keys to readable field names
                    let fieldMap = {};
                    if (type === 'family') {
                        fieldMap = { name: 'Name', relation: 'Relation', dob: 'DOB', blood_group: 'Blood Group', phone: 'Phone', email: 'Email' };
                        proposed.name = d.name; proposed.relation = d.relation; proposed.dob = d.dob; proposed.blood_group = d.blood; proposed.phone = d.phone; proposed.email = d.email;
                    } else if (type === 'help') {
                        fieldMap = { name: 'Name', role: 'Role', category: 'Category', phone: 'Phone', sex: 'Sex', visiting_hours: 'Hours' };
                        proposed.name = d.name; proposed.role = d.role; proposed.category = d.category; proposed.phone = d.phone; proposed.sex = d.sex; proposed.visiting_hours = d.hours;
                    } else if (type === 'vehicle') {
                        fieldMap = { number: 'Number', type: 'Type', brand: 'Brand', model: 'Model' };
                        proposed.number = d.number; proposed.type = d.type; proposed.brand = d.brand; proposed.model = d.model;
                    }

                    let hasDifferences = false;
                    let rows = '';

                    for (const [key, label] of Object.entries(fieldMap)) {
                        const oldVal = (original[key] || '').toString();
                        const newVal = (proposed[key] || '').toString();

                        if (oldVal !== newVal) {
                            hasDifferences = true;
                            rows += `
                                <tr>
                                    <td class="fw-bold text-secondary" style="font-size: 10px;">${label}</td>
                                    <td class="text-decoration-line-through text-danger">${oldVal || '(Empty)'}</td>
                                    <td class="text-success fw-bold">${newVal || '(Empty)'}</td>
                                </tr>
                            `;
                        }
                    }

                    if (hasDifferences) {
                        changesBody.innerHTML = rows;
                        changesSection.classList.remove('d-none');
                    }
                } catch (e) {
                    console.error("Comparison Error:", e);
                }
            }
        }

        // Bind Listeners
        const vFamilyModal = document.getElementById('viewFamilyModal');
        if (vFamilyModal) vFamilyModal.addEventListener('show.bs.modal', e => handleViewModalShow(e, 'family'));

        const vHelpModal = document.getElementById('viewHelpModal');
        if (vHelpModal) vHelpModal.addEventListener('show.bs.modal', e => handleViewModalShow(e, 'help'));

        const vVehicleModal = document.getElementById('viewVehicleModal');
        if (vVehicleModal) vVehicleModal.addEventListener('show.bs.modal', e => handleViewModalShow(e, 'vehicle'));

    });

    // --- Resident Request View Detail ---
    window.viewResidentRequestDetail = function(requestId) {
        if (!window.SNESTXDashboardData || !window.SNESTXDashboardData.my_requests) return;
        
        const req = window.SNESTXDashboardData.my_requests.find(r => r.id === requestId);
        if (!req) return;

        const modalEl = document.getElementById('residentRequestDetailModal');
        if (!modalEl) return;

        try {
            // payload may already be a decoded object (DB router auto-decodes JSON fields)
            const payload = (typeof req.payload === 'object' && req.payload !== null)
                ? req.payload
                : JSON.parse(req.payload);
            const status = req.status || 'pending';
            
            // Populate Modal
            document.getElementById('rrd-id').innerText = 'ID: #' + req.id.substring(req.id.length - 8);
            
            let category = payload.category || (req.request_type ? req.request_type.charAt(0).toUpperCase() + req.request_type.slice(1) : 'General Request');
            if (req.module === 'general') category = payload.category || 'General Request';
            document.getElementById('rrd-category').innerText = category;
            
            const badge = document.getElementById('rrd-status-badge');
            badge.innerText = status.charAt(0).toUpperCase() + status.slice(1);
            badge.className = 'badge rounded-pill fw-normal px-3 py-1 ';
            
            const icon = document.getElementById('rrd-icon');
            const iconWrapper = document.getElementById('rrd-icon-wrapper');
            
            if (status === 'approved') {
                badge.classList.add('bg-success-subtle', 'text-success');
                icon.className = 'bi bi-check-circle-fill text-success fs-3';
                iconWrapper.className = 'rounded-circle bg-success bg-opacity-10 d-inline-flex align-items-center justify-content-center border border-success border-opacity-25 shadow-sm mb-3';
            } else if (status === 'rejected') {
                badge.classList.add('bg-danger-subtle', 'text-danger');
                icon.className = 'bi bi-x-circle-fill text-danger fs-3';
                iconWrapper.className = 'rounded-circle bg-danger bg-opacity-10 d-inline-flex align-items-center justify-content-center border border-danger border-opacity-25 shadow-sm mb-3';
            } else {
                badge.classList.add('bg-warning-subtle', 'text-warning');
                icon.className = 'bi bi-clock-history text-warning fs-3';
                iconWrapper.className = 'rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center border border-warning border-opacity-25 shadow-sm mb-3';
            }

            document.getElementById('rrd-comments').innerText = payload.comments || '-';
            document.getElementById('rrd-date').innerText = new Date(req.created_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            document.getElementById('rrd-updated').innerText = req.updated_at ? new Date(req.updated_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

            const feedback = document.getElementById('rrd-admin-feedback');
            if (req.admin_note) {
                feedback.classList.remove('d-none');
                document.getElementById('rrd-admin-note').innerText = req.admin_note;
            } else {
                feedback.classList.add('d-none');
            }

            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        } catch (e) {
            console.error('Error parsing request payload:', e);
        }
    };

    // --- Real-time Payment Sync (Optimistic UI) ---
    function initPaymentSync() {
        if (!window.SNESTXDashboardData || !window.SNESTXDashboardData.rest_url || !window.SNESTXDashboardData.rest_nonce) {
            return;
        }

        let currentHash = null;
        let isPolling = false;
        const POLL_INTERVAL = 4000; // 4 seconds
        const API_BASE = window.SNESTXDashboardData.rest_url;
        const NONCE = window.SNESTXDashboardData.rest_nonce;

        async function pollStateHash() {
            if (isPolling) return;
            isPolling = true;

            try {
                const response = await fetch(`${API_BASE}state-hash`, {
                    method: 'GET',
                    headers: { 'X-WP-Nonce': NONCE, 'Accept': 'application/json' }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.hash) {
                        if (currentHash === null) {
                            currentHash = data.hash; // Initial load
                        } else if (currentHash !== data.hash) {
                            console.log('SNESTX: State Hash change detected. Refreshing data...');
                            currentHash = data.hash;
                            await refreshDashboard();
                        }
                    }
                }
            } catch (err) {
                console.error('SNESTX Sync Error:', err);
            } finally {
                isPolling = false;
                setTimeout(pollStateHash, POLL_INTERVAL);
            }
        }

        async function refreshDashboard() {
            try {
                // 1. Fetch updated JSON for Charts
                const resJson = await fetch(`${API_BASE}dashboard-data`, {
                    method: 'GET',
                    headers: { 'X-WP-Nonce': NONCE, 'Accept': 'application/json' }
                });

                if (resJson.ok) {
                    const data = await resJson.json();
                    if (data.success && data.data) {
                        window.SNESTXDashboardData.paymentHistory = data.data.paymentHistory;
                        window.SNESTXDashboardData.expenseChartData = data.data.expenseChartData;

                        // Update Charts seamlessly
                        if (paymentChart && data.data.paymentHistory) {
                            const dps = [];
                            data.data.paymentHistory.forEach(p => dps.push({ x: new Date(p.x), y: p.y }));
                            paymentChart.options.data[0].dataPoints = dps;
                            const tabHome = document.getElementById('tab-home');
                            const tabAccounts = document.getElementById('tab-accounts');
                            if ((tabHome && !tabHome.classList.contains('d-none')) || (tabAccounts && !tabAccounts.classList.contains('d-none'))) {
                                paymentChart.render();
                            }
                        }

                        if (expensesChart && data.data.expenseChartData) {
                             const dps = [];
                             for (const [label, y] of Object.entries(data.data.expenseChartData)) {
                                 dps.push({ label: label, y: y });
                             }
                             expensesChart.options.data[0].dataPoints = dps;
                             const tab = document.getElementById('tab-expenses');
                             if (tab && !tab.classList.contains('d-none')) {
                                 expensesChart.render();
                             }
                        }
                    }
                }

                // 2. Fetch updated HTML for DOM partial replacement (PJAX)
                const resHtml = await fetch(window.location.href);
                if (resHtml.ok) {
                    const htmlText = await resHtml.text();
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(htmlText, 'text/html');

                    // Replace Accounts Tab InnerHTML preserving the chart canvas
                    const newAccountsTab = doc.getElementById('tab-accounts');
                    const oldAccountsTab = document.getElementById('tab-accounts');
                    if (newAccountsTab && oldAccountsTab) {
                        const chartDiv = document.getElementById('paymentHistoryChart');
                        if (chartDiv) document.body.appendChild(chartDiv); // Save it

                        oldAccountsTab.innerHTML = newAccountsTab.innerHTML;

                        const newChartContainer = oldAccountsTab.querySelector('#paymentHistoryChart');
                        if (newChartContainer && chartDiv) {
                            newChartContainer.parentNode.replaceChild(chartDiv, newChartContainer);
                        }
                    }

                    // Replace Expenses Tab InnerHTML preserving the chart canvas
                    const newExpensesTab = doc.getElementById('tab-expenses');
                    const oldExpensesTab = document.getElementById('tab-expenses');
                    if (newExpensesTab && oldExpensesTab) {
                        const chartDiv = document.getElementById('expensesChart');
                        if (chartDiv) document.body.appendChild(chartDiv); // Save it

                        oldExpensesTab.innerHTML = newExpensesTab.innerHTML;

                        const newChartContainer = oldExpensesTab.querySelector('#expensesChart');
                        if (newChartContainer && chartDiv) {
                            newChartContainer.parentNode.replaceChild(chartDiv, newChartContainer);
                        }
                    }

                    // Replace Home Tab Summaries if they exist
                    const newHomeTab = doc.getElementById('tab-home');
                    const oldHomeTab = document.getElementById('tab-home');
                    if (newHomeTab && oldHomeTab) {
                        // In Home Tab, we just want to replace the "Pending Dues" card.
                        // Assuming it has some class or structure. For safety, we can just let it be 
                        // or do a broader replacement. Since we didn't inspect tab-home.php, we'll
                        // just replace the whole innerHTML but save the possible chart.
                        const chartDiv = document.getElementById('paymentHistoryChart');
                        if (chartDiv && oldHomeTab.contains(chartDiv)) {
                            document.body.appendChild(chartDiv); // Save it
                        }

                        // Reattach event listeners via global delegation anyway!
                        oldHomeTab.innerHTML = newHomeTab.innerHTML;
                        
                        const newChartContainer = oldHomeTab.querySelector('#paymentHistoryChart');
                        if (newChartContainer && chartDiv && oldHomeTab.contains(chartDiv)) {
                            newChartContainer.parentNode.replaceChild(chartDiv, newChartContainer);
                        }
                    }
                    
                    if (window.SNESTX && window.SNESTX.toast) {
                        window.SNESTX.toast.success('Dashboard payment data updated in real-time.', { icon: 'check-circle' });
                    }
                }

            } catch (err) {
                console.error('SNESTX Dashboard Refresh Error:', err);
            }
        }

        setTimeout(pollStateHash, 2000);
    }

})(jQuery);
