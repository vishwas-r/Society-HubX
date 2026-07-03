/**
 * E2E: Payment Lifecycle (Invoicing, UPI confirmation, and Treasurer Approval)
 */
const { test, expect } = require('@playwright/test');
const { loginAsAdmin, loginAsResident } = require('../../utils/auth');
const { ResidentDashboard } = require('../../pages/Resident/ResidentDashboard');
const { AdminRequests } = require('../../pages/Admin/AdminRequests');

test.describe('Payment Lifecycle Tests', () => {
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
                await dialog.accept('Approved by E2E test');
            } else {
                await dialog.accept();
            }
        });
    });

    test.afterEach(async () => {
        await adminContext.close();
        await residentContext.close();
    });

    test('payment-flow: Invoice Generation, Resident Payment, Admin Approval', async () => {
        // Step 1: Admin generates maintenance invoices
        await loginAsAdmin(adminPage);
        await adminPage.goto('/wp-admin/admin.php?page=shubx51-accounts');

        // Click Generate Maintenance button
        await adminPage.click('button:has-text("Generate Maintenance")');
        await adminPage.waitForSelector('#generateModal.show');

        // Fill invoice generation form
        const currentMonth = new Date().toISOString().slice(0, 7); // e.g., "2026-07"
        await adminPage.fill('#generateModal input[name="month"]', currentMonth);
        await adminPage.fill('#generateModal input[name="description"]', `Monthly Maintenance - E2E Test`);
        await adminPage.fill('#generateModal input[name="amount"]', '2000');
        
        // Submit generation form
        await adminPage.click('#generateModal button:has-text("Generate")');
        await adminPage.waitForURL(/page=shubx51-accounts/);
        await expect(adminPage.locator('.alert-success')).toContainText('Invoices generated successfully');

        // Close Admin context for now (logins are isolated)
        await adminContext.close();

        // Step 2: Resident logs in and submits payment confirmation
        await loginAsResident(residentPage);
        const dashboard = new ResidentDashboard(residentPage);
        await dashboard.navigateTo('accounts');

        // Verify outstanding dues exist
        await expect(residentPage.locator('#tab-accounts')).toContainText('₹2,000');

        // Click Pay Now
        await residentPage.click('button:has-text("Pay Now")');
        await residentPage.waitForSelector('#SHUBX51PaymentModal.show');

        // Fill payment confirmation form
        const testRef = 'TXN' + Date.now();
        await residentPage.fill('#confirm-amount', '2000');
        await residentPage.selectOption('#payment-confirmation-form select[name="method"]', 'UPI');
        await residentPage.fill('#payment-confirmation-form input[name="reference"]', testRef);

        // Submit Payment Confirmation
        await residentPage.click('#btn-confirm-payment');
        
        // Verify payment is in pending verification state
        await residentPage.waitForSelector('#tab-accounts');
        await expect(residentPage.locator('#tab-accounts')).toContainText('Awaiting Verification');
        await expect(residentPage.locator('.badge:has-text("PENDING")')).toBeVisible();

        // Step 3: Admin logs in, views requests, and approves payment
        adminContext = await adminPage.context(); // Re-use/re-open admin page
        adminPage = await adminContext.newPage();
        await loginAsAdmin(adminPage);

        const adminReq = new AdminRequests(adminPage);
        await adminReq.navigateTo();

        // Approve the payment request matching our reference ID
        await adminReq.approveRequest(testRef, 'accounts');

        // Step 4: Resident reloads and verifies invoice is paid and receipt is downloadable
        await residentPage.reload();
        await dashboard.navigateTo('accounts');

        // Outstanding dues should be 0 now
        await expect(residentPage.locator('#tab-accounts')).toContainText('₹0');

        // The transaction entry in payment history table should be marked paid/receipt should be available
        const receiptBtn = residentPage.locator('button[title="Download Receipt"]', { hasText: 'Receipt' }).first();
        await expect(receiptBtn).toBeVisible();

        // Click receipt and verify the modal displays the receipt
        await receiptBtn.click();
        await residentPage.waitForSelector('#receiptModal.show');
        await expect(residentPage.locator('#receipt-content')).toContainText('Payment Receipt');
        await expect(residentPage.locator('#receipt-content')).toContainText(testRef);

        // Close receipt modal
        await residentPage.click('#receiptModal .btn-close');
    });
});
