/**
 * E2E: Resident Module Lifecycle (Granular)
 */
const { test, expect } = require('@playwright/test');
const { loginAsAdmin, loginAsResident } = require('../../utils/auth');
const { ResidentDashboard } = require('../../pages/Resident/ResidentDashboard');
const { VehicleModule } = require('../../pages/Resident/VehicleModule');
const { FamilyModule } = require('../../pages/Resident/FamilyModule');
const { HelpModule } = require('../../pages/Resident/HelpModule');
const { AdminRequests } = require('../../pages/Admin/AdminRequests');
const vehicleData = require('../../fixtures/vehicles.json');
const familyData = require('../../fixtures/family.json');
const helpData = require('../../fixtures/help.json');
const { execSync } = require('child_process');
const path = require('path');

test.describe('Resident Module Granular Tests', () => {
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

    // --- VEHICLE TESTS ---
    test('vehicle-add: Add & Approve Vehicle', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const vehicles = new VehicleModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        await dashboard.navigateTo('home');
        await vehicles.addVehicle(vehicleData.validVehicle);

        const row = await vehicles.getVehicleRow(vehicleData.validVehicle.number);
        await expect(row.locator('.badge.bg-warning')).toContainText('PENDING');

        await adminReq.navigateTo();
        await adminReq.approveRequest(vehicleData.validVehicle.number);

        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowApproved = await vehicles.getVehicleRow(vehicleData.validVehicle.number);
        await expect(rowApproved.locator('.status-badge')).not.toBeVisible();
        await expect(rowApproved.locator('.dropdown button[data-bs-toggle="dropdown"]')).toBeVisible();
    });

    test('vehicle-edit: Edit & Approve Vehicle', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const vehicles = new VehicleModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        // Setup: Add and Approve
        await dashboard.navigateTo('home');
        await vehicles.addVehicle(vehicleData.validVehicle);
        await adminReq.navigateTo();
        await adminReq.approveRequest(vehicleData.validVehicle.number);

        // Action: Edit
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const updatedBrand = 'Updated ' + vehicleData.validVehicle.brand;
        await vehicles.editVehicle(vehicleData.validVehicle.number, { brand: updatedBrand });

        // Verify Pending
        const rowAfterEdit = await vehicles.getVehicleRow(vehicleData.validVehicle.number);
        await expect(rowAfterEdit.locator('.badge.bg-warning')).toContainText('PENDING');

        // Approve Edit
        await adminReq.navigateTo();
        await adminReq.approveRequest(vehicleData.validVehicle.number, 'vehicles');

        // Final Verify
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowFinal = await vehicles.getVehicleRow(vehicleData.validVehicle.number);
        await expect(rowFinal).toContainText(updatedBrand);
        await expect(rowFinal.locator('.status-badge')).not.toBeVisible();
    });

    test('vehicle-delete: Delete & Approve Vehicle', async () => {
        const dashboard = new ResidentDashboard(residentPage);
        const vehicles = new VehicleModule(residentPage);
        const adminReq = new AdminRequests(adminPage);

        // Setup: Add and Approve
        await dashboard.navigateTo('home');
        await vehicles.addVehicle(vehicleData.validVehicle);
        await adminReq.navigateTo();
        await adminReq.approveRequest(vehicleData.validVehicle.number, 'vehicles');

        // Action: Delete
        await residentPage.reload();
        await dashboard.navigateTo('home');
        await vehicles.deleteVehicle(vehicleData.validVehicle.number);

        // Verify Deletion Pending
        const rowPending = await vehicles.getVehicleRow(vehicleData.validVehicle.number);
        await expect(rowPending.locator('.badge.bg-danger')).toContainText('DEL PENDING');

        // Approve Deletion (fallback will handle missing number in payload)
        await adminReq.navigateTo();
        await adminReq.approveRequest(vehicleData.validVehicle.number, 'vehicles');

        // Final Verify
        await residentPage.reload();
        await dashboard.navigateTo('home');
        const rowFinal = await vehicles.getVehicleRow(vehicleData.validVehicle.number);
        await expect(rowFinal).not.toBeVisible();
    });

    // --- FAMILY TESTS ---
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

    // --- STAFF TESTS ---
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
