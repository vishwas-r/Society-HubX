/**
 * E2E: Resident Support Staff Module Lifecycle
 */
const { test, expect } = require('@playwright/test');
const { loginAsAdmin, loginAsResident } = require('../../utils/auth');
const { ResidentDashboard } = require('../../pages/Resident/ResidentDashboard');
const { HelpModule } = require('../../pages/Resident/HelpModule');
const { AdminRequests } = require('../../pages/Admin/AdminRequests');
const helpData = require('../../fixtures/help.json');

test.describe('Resident Support Staff Module Lifecycle Tests', () => {
    let adminContext;
    let residentContext;
    let adminPage;
    let residentPage;

    test.beforeEach(async ({ browser }) => {
        test.setTimeout(90000);

        adminContext = await browser.newContext();
        residentContext = await browser.newContext();

        adminPage = await adminContext.newPage();
        residentPage = await residentContext.newPage();

        // Global Dialog Handlers
        residentPage.on('dialog', async dialog => {
            console.log(`RESIDENT DIALOG: [${dialog.type()}] ${dialog.message()}`);
            await dialog.accept();
        });

        adminPage.on('dialog', async dialog => {
            console.log(`ADMIN DIALOG: [${dialog.type()}] ${dialog.message()}`);
            if (dialog.type() === 'prompt') {
                await dialog.accept('Auto rejected/approved');
            } else {
                await dialog.accept();
            }
        });

        await loginAsAdmin(adminPage);
        await loginAsResident(residentPage);
    });

    test.afterEach(async () => {
        await adminContext.close();
        await residentContext.close();
    });

    test('staff-add: Add & Approve Staff', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const staff = new HelpModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        await dashboard.navigateTo('home');
        await staff.addHelp(helpData.validHelp);

        const row = await staff.getHelpRow(helpData.validHelp.name);
        await expect(row.locator('.badge.bg-warning')).toContainText('PENDING');

        await adminReq.navigateTo();
        await adminReq.approveRequest(helpData.validHelp.name, 'daily_help');

        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowApproved = await staff.getHelpRow(helpData.validHelp.name);
        await expect(rowApproved.locator('.status-badge')).not.toBeVisible();
        await expect(rowApproved.locator('a[href^="tel:"]')).toBeVisible();
    });

    test('staff-edit: Edit & Approve Staff', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const staff = new HelpModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        // Setup
        await dashboard.navigateTo('home');
        await staff.addHelp(helpData.validHelp);
        await adminReq.navigateTo();
        await adminReq.approveRequest(helpData.validHelp.name, 'daily_help');

        // Action: Edit
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const updatedName = 'Updated ' + helpData.validHelp.name;
        await staff.editHelp(helpData.validHelp.name, { name: updatedName });

        // Verify Pending
        const rowAfterEdit = await staff.getHelpRow(updatedName);
        await expect(rowAfterEdit.locator('.badge.bg-warning')).toContainText('PENDING');

        // Approve
        await adminReq.navigateTo();
        await adminReq.approveRequest(updatedName, 'daily_help');

        // Final Verify
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowFinal = await staff.getHelpRow(updatedName);
        await expect(rowFinal).toBeVisible();
        await expect(rowFinal.locator('.status-badge')).not.toBeVisible();
    });

    test('staff-delete: Delete & Approve Staff', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const staff = new HelpModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        // Setup
        await dashboard.navigateTo('home');
        await staff.addHelp(helpData.validHelp);
        await adminReq.navigateTo();
        await adminReq.approveRequest(helpData.validHelp.name, 'daily_help');

        // Action: Delete
        await residentPage.reload();
        await dashboard.navigateTo('home');
        await staff.deleteHelp(helpData.validHelp.name);

        // Verify Pending
        const rowPending = await staff.getHelpRow(helpData.validHelp.name);
        await expect(rowPending.locator('.badge.bg-danger')).toContainText('DELETION PENDING');

        // Approve
        await adminReq.navigateTo();
        await adminReq.approveRequest(helpData.validHelp.name, 'daily_help');

        // Final Verify
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowFinal = await staff.getHelpRow(helpData.validHelp.name);
        await expect(rowFinal).not.toBeVisible();
    });
});
