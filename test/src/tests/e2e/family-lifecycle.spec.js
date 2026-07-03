/**
 * E2E: Resident Family Module Lifecycle
 */
const { test, expect } = require('@playwright/test');
const { loginAsAdmin, loginAsResident } = require('../../utils/auth');
const { ResidentDashboard } = require('../../pages/Resident/ResidentDashboard');
const { FamilyModule } = require('../../pages/Resident/FamilyModule');
const { AdminRequests } = require('../../pages/Admin/AdminRequests');
const familyData = require('../../fixtures/family.json');

test.describe('Resident Family Module Lifecycle Tests', () => {
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

    test('family-add: Add & Approve Family Member', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const family = new FamilyModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        await dashboard.navigateTo('home');
        await family.addMember(familyData.validMember);

        const row = await family.getMemberRow(familyData.validMember.name);
        await expect(row.locator('.badge.bg-warning')).toContainText('PENDING');

        await adminReq.navigateTo();
        await adminReq.approveRequest(familyData.validMember.name, 'residents');

        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowApproved = await family.getMemberRow(familyData.validMember.name);
        await expect(rowApproved.locator('.status-badge')).not.toBeVisible();
        await expect(rowApproved.locator('.dropdown button[data-bs-toggle="dropdown"]')).toBeVisible();
    });

    test('family-edit: Edit & Approve Family Member', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const family = new FamilyModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        // Setup
        await dashboard.navigateTo('home');
        await family.addMember(familyData.validMember);
        await adminReq.navigateTo();
        await adminReq.approveRequest(familyData.validMember.name, 'residents');

        // Action: Edit
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const updatedName = 'Updated ' + familyData.validMember.name;
        await family.editMember(familyData.validMember.name, { name: updatedName });

        // Verify Pending
        const rowAfterEdit = await family.getMemberRow(updatedName);
        await expect(rowAfterEdit.locator('.badge.bg-warning')).toContainText('PENDING');

        // Approve
        await adminReq.navigateTo();
        await adminReq.approveRequest(updatedName, 'residents');

        // Final Verify
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowFinal = await family.getMemberRow(updatedName);
        await expect(rowFinal).toBeVisible();
        await expect(rowFinal.locator('.status-badge')).not.toBeVisible();
    });

    test('family-delete: Delete & Approve Family Member', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const family = new FamilyModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        // Setup
        await dashboard.navigateTo('home');
        await family.addMember(familyData.validMember);
        await adminReq.navigateTo();
        await adminReq.approveRequest(familyData.validMember.name, 'residents');

        // Action: Delete
        await residentPage.reload();
        await dashboard.navigateTo('home');
        await family.deleteMember(familyData.validMember.name);

        // Verify Pending
        const rowPending = await family.getMemberRow(familyData.validMember.name);
        await expect(rowPending.locator('.badge.bg-danger')).toContainText('DEL PENDING');

        // Approve
        await adminReq.navigateTo();
        await adminReq.approveRequest(familyData.validMember.name, 'residents');

        // Final Verify
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowFinal = await family.getMemberRow(familyData.validMember.name);
        await expect(rowFinal).not.toBeVisible();
    });
});
