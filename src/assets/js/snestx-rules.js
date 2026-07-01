/**
 * SNESTX Rules Management JS
 */
(function ($) {
    'use strict';

    const Config = {
        nonce: null,
        initialized: false
    };

    let ruleModal = null;
    let categoryModal = null;
    let violationModal = null;
    let versionHistoryModal = null;

    async function fetchModuleConfig() {
        if (Config.initialized) return;

        try {
            const result = await SNESTX.ajax({
                action: 'SNESTX51_get_module_config',
                data: { module: 'rules' },
                showOverlay: false,
                suppressErrorToast: true
            });

            if (result) {
                Config.nonce = result.nonce || null;
                Config.initialized = true;
            }
        } catch (error) {
            console.error('Error fetching rules module config:', error);
        }
    }

    // --- Tab Switching ---
    window.switchRulesTab = function (tab) {
        ['all-rules', 'categories', 'violations', 'acknowledgments', 'reports'].forEach(t => {
            const view = document.getElementById('view-' + t);
            const btn = document.getElementById('tab-btn-' + t);
            if (view) view.classList.add('d-none');
            if (btn) {
                btn.classList.remove('active', 'border-primary', 'text-primary');
                btn.classList.add('border-transparent', 'text-muted');
            }
        });

        const view = document.getElementById('view-' + tab);
        const btn = document.getElementById('tab-btn-' + tab);
        if (view) view.classList.remove('d-none');
        if (btn) {
            btn.classList.add('active', 'border-primary', 'text-primary');
            btn.classList.remove('border-transparent', 'text-muted');
        }
    };

    // --- Rules Search Filter ---
    window.filterRules = function () {
        const search = (document.getElementById('rulesSearch')?.value || '').toLowerCase();
        const status = document.getElementById('filterStatus')?.value || '';
        const category = document.getElementById('filterCategory')?.value || '';

        document.querySelectorAll('.rule-row').forEach(row => {
            const searchText = row.dataset.search || '';
            const rowStatus = row.dataset.status || '';
            const rowCategory = row.dataset.category || '';

            const matchesSearch = !search || searchText.includes(search);
            const matchesStatus = !status || rowStatus === status;
            const matchesCategory = !category || rowCategory === category;

            row.style.display = (matchesSearch && matchesStatus && matchesCategory) ? '' : 'none';
        });
    };

    // --- Rule Actions ---
    window.openAddRuleModal = function () {
        const form = document.getElementById('ruleForm');
        if (form) form.reset();
        document.getElementById('rule_id').value = '';
        document.getElementById('ruleModalTitle').textContent = 'Add New Rule';
        document.getElementById('ackFieldsContainer').style.display = 'none';
        if (!ruleModal) ruleModal = new bootstrap.Modal(document.getElementById('ruleModal'));
        ruleModal.show();
    };

    window.editRule = function (rule) {
        document.getElementById('rule_id').value = rule.id;
        document.getElementById('rule_title').value = rule.title;
        document.getElementById('rule_category').value = rule.category;
        document.getElementById('rule_priority').value = rule.priority;
        document.getElementById('rule_content').value = rule.content;
        document.getElementById('rule_effective_date').value = rule.effective_date || '';
        document.getElementById('rule_expiry_date').value = rule.expiry_date || '';
        document.getElementById('rule_requires_ack').checked = rule.requires_acknowledgment == 1;
        document.getElementById('rule_ack_deadline').value = rule.acknowledgment_deadline || '';
        document.getElementById('rule_fine_amount').value = rule.fine_amount;
        document.getElementById('rule_tags').value = rule.tags || '';
        document.getElementById('rule_status').value = rule.status;
        document.getElementById('ruleModalTitle').textContent = 'Edit Rule';
        document.getElementById('ackFieldsContainer').style.display = rule.requires_acknowledgment == 1 ? '' : 'none';
        if (!ruleModal) ruleModal = new bootstrap.Modal(document.getElementById('ruleModal'));
        ruleModal.show();
    };

    window.handleRuleSubmit = function (e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        const isEdit = formData.get('rule_id') !== '';

        SNESTX.ajax({
            action: isEdit ? 'SNESTX51_edit_rule' : 'SNESTX51_add_rule',
            data: Object.fromEntries(formData),
            loadingButton: $(e.target).find('button[type="submit"]'),
            successMessage: 'Rule saved successfully!',
            reload: true,
            onSuccess: function () {
                if (ruleModal) ruleModal.hide();
            }
        });
    };

    window.publishRule = async function (ruleId) {
        if (!confirm('Publish this rule? Residents will be notified.')) return;
        await fetchModuleConfig();

        SNESTX.ajax({
            action: 'SNESTX51_publish_rule',
            data: {
                rule_id: ruleId,
                _wpnonce: Config.nonce
            },
            successMessage: 'Rule published successfully!',
            reload: true
        });
    };

    window.deleteRule = async function (ruleId) {
        if (!confirm('Archive this rule? It will be hidden from residents.')) return;
        await fetchModuleConfig();

        SNESTX.ajax({
            action: 'SNESTX51_delete_rule',
            data: {
                rule_id: ruleId,
                _wpnonce: Config.nonce
            },
            successMessage: 'Rule archived successfully!',
            reload: true
        });
    };

    window.viewVersionHistory = async function (ruleId) {
        if (!versionHistoryModal) versionHistoryModal = new bootstrap.Modal(document.getElementById('versionHistoryModal'));
        document.getElementById('versionHistoryContent').innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>';
        versionHistoryModal.show();
        await fetchModuleConfig();

        SNESTX.ajax({
            action: 'SNESTX51_get_version_history',
            data: {
                rule_id: ruleId,
                _wpnonce: Config.nonce
            },
            onSuccess: function (data) {
                if (data.versions) {
                    let html = '<div class="timeline">';
                    data.versions.forEach(v => {
                        html += `
                            <div class="border-bottom pb-3 mb-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <span class="badge bg-primary">v${v.version}</span>
                                        <strong class="ms-2">${v.title}</strong>
                                    </div>
                                    <small class="text-muted">${v.changed_at}</small>
                                </div>
                                <p class="small text-muted mb-0">${v.change_summary}</p>
                            </div>
                        `;
                    });
                    html += '</div>';
                    document.getElementById('versionHistoryContent').innerHTML = html;
                }
            }
        });
    };

    // --- Category Actions ---
    window.openAddCategoryModal = function () {
        const form = document.getElementById('categoryForm');
        if (form) form.reset();
        document.getElementById('category_id').value = '';
        document.getElementById('category_action').value = 'add';
        document.getElementById('categoryModalTitle').textContent = 'Add Category';
        if (!categoryModal) categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
        categoryModal.show();
    };

    window.editCategory = function (cat) {
        document.getElementById('category_id').value = cat.id;
        document.getElementById('category_name').value = cat.name;
        document.getElementById('category_description').value = cat.description;
        document.getElementById('category_icon').value = cat.icon;
        document.getElementById('category_color').value = cat.color;
        document.getElementById('category_action').value = 'edit';
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        if (!categoryModal) categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
        categoryModal.show();
    };

    window.handleCategorySubmit = function (e) {
        e.preventDefault();
        const formData = new FormData(e.target);

        SNESTX.ajax({
            action: 'SNESTX51_manage_category',
            data: Object.fromEntries(formData),
            loadingButton: $(e.target).find('button[type="submit"]'),
            successMessage: 'Category saved successfully!',
            reload: true,
            onSuccess: function () {
                if (categoryModal) categoryModal.hide();
            }
        });
    };

    window.deleteCategory = async function (catId) {
        if (!confirm('Are you sure you want to delete this category? This cannot be undone.')) return;
        await fetchModuleConfig();

        SNESTX.ajax({
            action: 'SNESTX51_manage_category',
            data: {
                category_action: 'delete',
                category_id: catId,
                _wpnonce: Config.nonce
            },
            successMessage: 'Category deleted successfully!',
            reload: true
        });
    };

    // --- Violation Actions ---
    window.viewViolation = function (violation) {
        const html = `
            <div class="row g-3">
                <div class="col-md-6">
                    <strong>Flat Number:</strong><br>${violation.flat_no}
                </div>
                <div class="col-md-6">
                    <strong>Violation Date:</strong><br>${new Date(violation.violation_date).toLocaleDateString()}
                </div>
                <div class="col-12">
                    <strong>Description:</strong><br>${violation.description}
                </div>
                <div class="col-md-6">
                    <strong>Fine Amount:</strong><br>₹${parseFloat(violation.fine_amount).toFixed(2)}
                </div>
                <div class="col-md-6">
                    <strong>Payment Status:</strong><br>
                    <span class="badge bg-${violation.payment_status === 'paid' ? 'success' : 'warning'}">${violation.payment_status}</span>
                </div>
                ${violation.appeal_reason ? `<div class="col-12"><strong>Appeal Reason:</strong><br>${violation.appeal_reason}</div>` : ''}
                ${violation.admin_notes ? `<div class="col-12"><strong>Admin Notes:</strong><br>${violation.admin_notes}</div>` : ''}
            </div>
        `;
        document.getElementById('violationDetails').innerHTML = html;
        if (!violationModal) violationModal = new bootstrap.Modal(document.getElementById('violationModal'));
        violationModal.show();
    };

    window.resolveViolation = async function (violationId) {
        const notes = prompt('Enter resolution notes (optional):');
        if (notes === null) return;
        await fetchModuleConfig();

        SNESTX.ajax({
            action: 'SNESTX51_resolve_violation',
            data: {
                violation_id: violationId,
                status: 'resolved',
                admin_notes: notes,
                _wpnonce: Config.nonce
            },
            successMessage: 'Violation resolved successfully!',
            reload: true
        });
    };

    window.sendReminders = async function () {
        if (!confirm('Send acknowledgment reminders to all residents with pending acknowledgments?')) return;
        await fetchModuleConfig();

        SNESTX.ajax({
            action: 'SNESTX51_send_acknowledgment_reminders',
            data: {
                _wpnonce: Config.nonce
            },
            successMessage: 'Reminders sent successfully!',
            reload: true
        });
    };

    // --- Init ---
    $(function () {
        fetchModuleConfig();

        // Bind events
        const rulesSearch = document.getElementById('rulesSearch');
        const filterStatus = document.getElementById('filterStatus');
        const filterCategory = document.getElementById('filterCategory');

        [rulesSearch, filterStatus, filterCategory].forEach(el => {
            if (el) el.addEventListener('input', window.filterRules);
        });

        const ruleRequiresAck = document.getElementById('rule_requires_ack');
        if (ruleRequiresAck) {
            ruleRequiresAck.addEventListener('change', function () {
                document.getElementById('ackFieldsContainer').style.display = this.checked ? '' : 'none';
            });
        }

        const ruleForm = document.getElementById('ruleForm');
        if (ruleForm) {
            ruleForm.addEventListener('submit', window.handleRuleSubmit);
        }

        const categoryForm = document.getElementById('categoryForm');
        if (categoryForm) {
            categoryForm.addEventListener('submit', window.handleCategorySubmit);
        }
    });

})(jQuery);
