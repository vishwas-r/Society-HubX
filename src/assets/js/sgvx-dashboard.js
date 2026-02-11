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
        if (!window.CanvasJS || !window.sgvxDashboardData) {
            // return; // Don't return strictly, we have other logic to run
        }

        // --- 0. Tab Switching Logic (Migrated from frontend.js) ---
        const tabs = ['home', 'notices', 'facilities', 'accounts', 'expenses', 'polls', 'community', 'directory', 'notifications']; // Added directory, notifications

        function activateTab(tabName) {
            const btnId = 'btn-tab-' + tabName;
            const btn = document.getElementById(btnId);
            if (!btn) return;

            const targetId = btn.getAttribute('data-tab-target');

            // Update button states
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('active', 'text-primary', 'border-primary');
                b.classList.add('border-transparent', 'text-muted');
            });
            btn.classList.remove('border-transparent', 'text-muted');
            btn.classList.add('active', 'text-primary', 'border-primary');

            // Update content visibility
            document.querySelectorAll('.tab-content').forEach(c => {
                c.classList.add('d-none');
                c.classList.remove('d-block');
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
                setTimeout(() => expensesChart.render(), 100);
            }
            if ((tabName === 'home' || tabName === 'accounts') && paymentChart) {
                setTimeout(() => paymentChart.render(), 100);
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
        if (expenseContainer && window.sgvxDashboardData.expenseChartData) {
            const expenseData = window.sgvxDashboardData.expenseChartData;
            const dps = [];

            for (const [label, y] of Object.entries(expenseData)) {
                dps.push({ label: label, y: y });
            }

            if (dps.length > 0) {
                expensesChart = new CanvasJS.Chart("expensesChart", {
                    animationEnabled: true,
                    theme: "light2",
                    title: {
                        text: "Monthly Society Expense Trend",
                        fontSize: 16,
                        fontFamily: "Inter, sans-serif"
                    },
                    axisY: {
                        title: "Amount (₹)",
                        includeZero: true,
                        prefix: "₹",
                        valueFormatString: "#,##,##0"
                    },
                    data: [{
                        type: "column",
                        color: "#6366f1",
                        indexLabel: "{y}",
                        yValueFormatString: "₹#,##,##0",
                        dataPoints: dps
                    }]
                });

                // Only render if tab is visible
                const expensesTab = document.getElementById('tab-expenses');
                if (expensesTab && !expensesTab.classList.contains('d-none')) {
                    expensesChart.render();
                }
            }
        }

        // 2. Resident Payment History
        const paymentContainer = document.getElementById('paymentHistoryChart');
        if (paymentContainer && window.sgvxDashboardData.paymentHistory) {
            const paymentData = window.sgvxDashboardData.paymentHistory;
            const dps = [];
            if (Array.isArray(paymentData)) {
                paymentData.forEach(p => {
                    dps.push({ x: new Date(p.x), y: p.y });
                });
            } else {
                for (const [label, y] of Object.entries(paymentData)) {
                    dps.push({ label: label, y: y });
                }
            }

            if (dps.length > 0) {
                paymentChart = new CanvasJS.Chart("paymentHistoryChart", {
                    animationEnabled: true,
                    theme: "light2",
                    title: {
                        text: "My Payment History",
                        fontSize: 16,
                        fontFamily: "Inter, sans-serif"
                    },
                    axisX: {
                        valueFormatString: "MMM YYYY",
                    },
                    axisY: {
                        title: "Amount (₹)",
                        includeZero: true,
                        prefix: "₹",
                        valueFormatString: "#,##,##0"
                    },
                    data: [{
                        type: "spline",
                        color: "#10b981",
                        markerSize: 8,
                        yValueFormatString: "₹#,##,##0",
                        dataPoints: dps
                    }]
                });

                paymentChart.render();
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
        // Init Charts
        initCharts();

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
                if (window.sgvxCreateFuse) dirFuse = window.sgvxCreateFuse('.dir-card');
            });
        }

        // Facility Search (Available Facilities)
        const facilitySearch = document.getElementById('facility-dashboard-search');
        if (facilitySearch) {
            facilitySearch.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();
                if (!facFuse && window.sgvxCreateFuse) {
                    facFuse = window.sgvxCreateFuse('.facility-card');
                }

                const matches = val && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(facFuse, val) : null;

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
                if (window.sgvxCreateFuse) facFuse = window.sgvxCreateFuse('.facility-card');
            });
        }

        // Booking Search (My Bookings)
        const bookingSearch = document.getElementById('booking-dashboard-search');
        if (bookingSearch) {
            bookingSearch.addEventListener('input', function () {
                const val = this.value.trim().toLowerCase();
                if (!bookingFuse && window.sgvxCreateFuse) {
                    bookingFuse = window.sgvxCreateFuse('.booking-dash-row');
                }

                const matches = val && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(bookingFuse, val) : null;

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
                if (window.sgvxCreateFuse) bookingFuse = window.sgvxCreateFuse('.booking-dash-row');
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
                const btn = familyForm.querySelector('button[type="submit"]');
                const originalText = btn.innerText;
                btn.disabled = true;
                btn.innerText = 'Saving...';

                const formData = new FormData(familyForm);
                // Ensure action is set (if not already in form)
                if (!formData.get('action')) formData.append('action', 'sgvx51_add_family');

                // Use global ajaxurl (defined in society-govern-x.php)
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message || 'Saved successfully');
                            location.reload();
                        } else {
                            const msg = (data.data && data.data.message) || (typeof data.data === 'string' ? data.data : 'Unknown error');
                            alert('Error: ' + msg);
                            btn.disabled = false;
                            btn.innerText = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Fetch Error:', err);
                        alert('Network error occurred.');
                        btn.disabled = false;
                        btn.innerText = originalText;
                    });
            });
        }

        // 2. Help Form Submit
        const helpForm = document.querySelector('#helpModal form');
        if (helpForm) {
            helpForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = helpForm.querySelector('button[type="submit"]');
                if (!btn) return;
                const originalText = btn.innerText;
                btn.disabled = true;
                btn.innerText = 'Saving...';

                const formData = new FormData(helpForm);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message || 'Saved successfully');
                            location.reload();
                        } else {
                            const msg = (data.data && data.data.message) || (typeof data.data === 'string' ? data.data : 'Unknown error');
                            alert('Error: ' + msg);
                            btn.disabled = false;
                            btn.innerText = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Fetch Error:', err);
                        alert('Network error occurred.');
                        btn.disabled = false;
                        btn.innerText = originalText;
                    });
            });
        }

        // 3. Vehicle Form Submit
        const vehicleForm = document.querySelector('#vehicleModal form');
        if (vehicleForm) {
            vehicleForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const btn = vehicleForm.querySelector('button[type="submit"]');
                if (!btn) return;
                const originalText = btn.innerText;
                btn.disabled = true;
                btn.innerText = 'Requesting...';

                const formData = new FormData(vehicleForm);

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.data.message || 'Request submitted successfully');
                            location.reload();
                        } else {
                            const msg = (data.data && data.data.message) || (typeof data.data === 'string' ? data.data : 'Unknown error');
                            alert('Error: ' + msg);
                            btn.disabled = false;
                            btn.innerText = originalText;
                        }
                    })
                    .catch(err => {
                        console.error('Fetch Error:', err);
                        alert('Network error occurred.');
                        btn.disabled = false;
                        btn.innerText = originalText;
                    });
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

            // Reset form fields
            form.reset();

            // Reset Photo
            const preview = document.getElementById('preview-frontend_family');
            const placeholder = document.getElementById('icon-frontend_family');
            if (preview) { preview.src = ''; preview.classList.add('d-none'); }
            if (placeholder) placeholder.classList.remove('d-none');

            // Remove hidden ID and type fields (Edit Mode artifacts)
            const idInput = form.querySelector('input[name="member_id"]');
            if (idInput) idInput.remove();

            // Reset Type to Default (Family) or Remove if hidden
            // authentic "add" form relies on resident-form.php which might have a hidden type or select
            // For frontend family, type is usually 'family' 
            const typeInput = form.querySelector('input[name="type"]');
            if (typeInput && typeInput.type === 'hidden') typeInput.remove();

            // Reset action to add
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = 'sgvx51_add_family';

            // RESTORE Nonce for Add Action
            // The form by default has _wpnonce_add_family. We need to make sure the main _wpnonce matches it.
            const addNonceInput = form.querySelector('input[name="_wpnonce_add_family"]');
            const mainNonce = form.querySelector('input[name="_wpnonce"]');

            if (addNonceInput && mainNonce) {
                mainNonce.value = addNonceInput.value;
            }

            // Reset title and button
            const modalTitle = form.querySelector('.modal-title');
            if (modalTitle) modalTitle.innerText = 'Add Family Member';

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerText = 'Add Member';
                submitBtn.disabled = false;
            }
        };

        // --- Edit Handlers ---
        function handleEditFamily(btn) {
            console.log("SGVX: handleEditFamily triggered", btn.dataset);
            const d = btn.dataset;
            const form = document.querySelector('#familyModal form');
            if (!form) { console.error("Family form not found!"); return; }

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

            // Set action for edit
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput) actionInput.value = 'sgvx51_edit_family';
            else console.error("Action input missing in Family Form!");

            // Set Member ID
            const idInput = form.querySelector('input[name="member_id"]');
            if (idInput) idInput.value = d.id;
            else {
                // Fallback: Create if missing (should not happen with recent PHP update)
                const newId = document.createElement('input');
                newId.type = 'hidden';
                newId.name = 'member_id';
                newId.value = d.id;
                form.appendChild(newId);
            }

            // Swap Nonce: Use Edit Nonce
            const editNonceInput = form.querySelector('input[name="_wpnonce_edit_family"]');
            const mainNonce = form.querySelector('input[name="_wpnonce"]');

            if (editNonceInput && mainNonce) {
                mainNonce.value = editNonceInput.value;
            } else {
                console.warn("Nonce inputs missing for edit family swap.");
            }

            // Ensure Type input exists
            let typeInput = form.querySelector('input[name="type"]');
            if (!typeInput) {
                typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'type';
                form.appendChild(typeInput);
            }
            typeInput.value = 'family'; // Force type family

            // Change Title
            const modalTitle = form.querySelector('.modal-title');
            if (modalTitle) modalTitle.innerText = 'Edit Family Member';

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) submitBtn.innerText = 'Update';

            // Open modal using Bootstrap API
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
                form.querySelector('[name="action"]').value = 'sgvx51_edit_help_frontend';

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
            form.querySelector('[name="action"]').value = 'sgvx51_add_daily_help';
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
            if (actionField) actionField.value = 'sgvx51_edit_vehicle_frontend';

            const idField = form.querySelector('[name="vehicle_id"]');
            if (idField) idField.value = payload.id;

            // Swap Nonce
            const editNonce = form.querySelector('[name="sgvx51_edit_vehicle_token"]');
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
            if (btn) { e.preventDefault(); handleDeleteGeneric(btn, 'sgvx51_delete_daily_help_frontend'); return; }

            btn = e.target.closest('.js-delete-vehicle-frontend');
            if (btn) { e.preventDefault(); handleDeleteGeneric(btn, 'sgvx51_delete_vehicle_frontend'); return; }
        });

        // --- Generic Delete Handler ---
        function handleDeleteGeneric(btn, action) {
            if (!confirm('Are you sure you want to delete this?')) return;

            const id = btn.dataset.id;
            const nonce = btn.dataset.nonce;
            const originalText = btn.innerText;
            btn.innerText = 'Deleting...';
            btn.disabled = true;

            $.post(ajaxurl, {
                action: action,
                id: id,
                _wpnonce: nonce
            }, function (res) {
                if (res.success) {
                    window.location.reload();
                } else {
                    alert(res.data || 'Deletion failed');
                    btn.innerText = originalText;
                    btn.disabled = false;
                }
            });
        }

        // --- Specific Delete Handlers (can wrap generic if needed) ---
        function handleDeleteFamily(btn) {
            handleDeleteGeneric(btn, 'sgvx51_delete_family_frontend');
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
            console.log(card)
            try {
                const data = JSON.parse(card.dataset.json);
                const modal = document.getElementById('communityDetailModal');
                if (!modal) return;

                // Populate modal with data
                document.getElementById('cdm-flat').textContent = data.flat_no || '';
                document.getElementById('cdm-owner').textContent = data.owner || '';
                document.getElementById('cdm-members').textContent = data.members || '0';

                // Populate vehicles
                const vehiclesDiv = document.getElementById('cdm-vehicles');
                if (vehiclesDiv) {
                    vehiclesDiv.innerHTML = '';
                    if (data.vehicles && data.vehicles.length > 0) {
                        data.vehicles.forEach(function (v) {
                            const badge = document.createElement('div');
                            badge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle fw-normal d-flex align-items-center gap-1';
                            badge.innerHTML = '<i class="bi bi-car-front" style="font-size:12px;"></i> ' + (v.number || 'N/A');
                            vehiclesDiv.appendChild(badge);
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
                            const badge = document.createElement('div');
                            badge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle fw-normal d-flex align-items-center gap-1';
                            badge.innerHTML = '<i class="bi bi-person-badge" style="font-size:12px;"></i> ' + (h.name || 'N/A') + ' (' + (h.role || 'Staff') + ')';
                            helpDiv.appendChild(badge);
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

            if (!dirFuse && window.sgvxCreateFuse) {
                if (typeof Fuse === 'undefined') {
                    console.error('Fuse.js is not loaded! Search will fail.');
                    return;
                }
                // Use stricter threshold (0.2) and only search metadata to avoid noise from labels
                dirFuse = window.sgvxCreateFuse('.dir-card', {
                    threshold: 0.3,
                    searchOnlyMeta: true
                });
            }

            const fuzzyMatches = searchTerm && window.sgvxGetFuzzyMatches ? window.sgvxGetFuzzyMatches(dirFuse, searchTerm) : null;

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
                alert('Receipt not found!');
                return;
            }

            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';

            if (typeof html2canvas === 'undefined') {
                alert('Library not loaded. Please try again.');
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
                alert('Error generating receipt image. Please try again.');
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

            const nonce = (window.sgvxDashboardData && window.sgvxDashboardData.nonce) ? window.sgvxDashboardData.nonce : (typeof sgvx51_nonce !== 'undefined' ? sgvx51_nonce : '');

            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=sgvx51_get_receipt&invoice_id=' + encodeURIComponent(invoiceId) + '&nonce=' + nonce
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateReceiptModal(data.data);
                        let modalEl = document.getElementById('receiptModal');
                        if (!modalEl) modalEl = document.getElementById('sgvx-resident-receipt-modal');

                        if (modalEl) {
                            const modal = new bootstrap.Modal(modalEl);
                            modal.show();
                        }
                    } else {
                        alert('Error loading receipt: ' + (data.data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Receipt fetch error:', error);
                    alert('Error loading receipt. Please try again.');
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
                    <p class="mb-0">Society GoVernX - Empowering Communities</p>
                </div>
            `;
        }

        // --- Payment Confirmation Handler ---
        function submitPaymentConfirmation(btn) {
            const form = document.getElementById('payment-confirmation-form');
            if (!form) return;

            // Validation
            const amount = form.querySelector('[name="amount"]').value;
            const ref = form.querySelector('[name="reference"]').value;
            if (!amount || !ref) {
                alert('Please fill in the Amount and Reference Number.');
                return;
            }

            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

            const formData = new FormData(form);
            formData.append('action', 'sgvx51_submit_payment_request');
            const nonce = (window.sgvxDashboardData && window.sgvxDashboardData.nonce) ? window.sgvxDashboardData.nonce : '';
            formData.append('_ajax_nonce', nonce);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.data.message || 'Payment confirmation sent!');
                        const modalEl = document.getElementById('sgvx51PaymentModal');
                        if (modalEl) {
                            const modal = bootstrap.Modal.getInstance(modalEl);
                            if (modal) modal.hide();
                        }
                        location.reload();
                    } else {
                        alert('Error: ' + (data.data.message || 'Failed to submit request'));
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Submission Error:', error);
                    alert('An error occurred. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = originalText;
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
        // console.log("SGVX Debug: Profile Modal Reset Script Loaded", profileModal); 

        if (profileModal && typeof sgvxDashboardData !== 'undefined' && sgvxDashboardData.resident) {
            profileModal.addEventListener('show.bs.modal', function () {
                var r = sgvxDashboardData.resident;
                // console.log("SGVX Debug: Populating Profile Modal", r);

                var form = profileModal.querySelector('form');
                if (!form) {
                    // console.error("SGVX Debug: Profile Form not found in modal");
                    return;
                }

                // Helper to set value safely
                var setVal = function (name, val) {
                    var el = form.querySelector('[name="' + name + '"]');
                    if (el) {
                        el.value = val || '';
                        // console.log("SGVX Debug: Set " + name + " to " + val);
                    } else {
                        // console.warn("SGVX Debug: Input not found for " + name);
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
            console.warn("SGVX Debug: Missing Data or Modal", {
               modal: !!profileModal,
               data: typeof sgvxDashboardData,
               resident: (sgvxDashboardData || {}).resident
            });
            */
        }

    });
})(jQuery);
