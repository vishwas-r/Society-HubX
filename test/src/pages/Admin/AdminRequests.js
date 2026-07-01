/**
 * Page Object: Admin Requests (Approval Workflow)
 */
const { expect } = require('@playwright/test');

class AdminRequests {
    constructor(page) {
        this.page = page;
        this.searchField = page.locator('#req-search');
        this.moduleFilter = page.locator('#req-filter-module');
        this.rows = page.locator('tr.request-row');
    }

    async navigateTo() {
        await this.page.goto('/wp-admin/admin.php?page=snestx51-requests');
    }

    async approveRequest(identifier, moduleSlug = '') {
        // 1. Try finding by identifier (e.g. vehicle number or member name)
        let selector = `tr.request-row:has-text("${identifier}")`;
        if (moduleSlug) {
            selector = `tr.request-row[data-module="${moduleSlug}"]:has-text("${identifier}")`;
        }

        let row = this.page.locator(selector).first();

        // 2. Fallback: If not found (e.g. deletion request with no name in payload), 
        // try finding ANY request for that module from the test resident.
        const count = await row.count();
        if (count === 0 && moduleSlug) {
            console.log(`   [AdminRequests] Identifier "${identifier}" not found for module "${moduleSlug}". Falling back to requester search.`);
            selector = `tr.request-row[data-module="${moduleSlug}"]:has-text("flat208")`;
            row = this.page.locator(selector).first();
        }

        const approveBtn = row.locator('.js-approve-inline');

        // Approve and wait for reload
        await approveBtn.click();
        await this.page.waitForLoadState('networkidle');

        // Wait for removal from pending list
        await expect(row).not.toBeVisible();
    }

    async rejectRequest(identifier, moduleSlug = '', note = 'Rejected by Automation') {
        let selector = `tr.request-row:has-text("${identifier}")`;
        if (moduleSlug) {
            selector = `tr.request-row[data-module="${moduleSlug}"]:has-text("${identifier}")`;
        }

        let row = this.page.locator(selector).first();
        const count = await row.count();
        if (count === 0 && moduleSlug) {
            selector = `tr.request-row[data-module="${moduleSlug}"]:has-text("flat208")`;
            row = this.page.locator(selector).first();
        }

        const rejectBtn = row.locator('.js-reject-inline');

        // Click and wait for reload (prompt is handled by global listener)
        await rejectBtn.click();
        await this.page.waitForLoadState('networkidle');

        await expect(row).not.toBeVisible();
    }
}

module.exports = { AdminRequests };
