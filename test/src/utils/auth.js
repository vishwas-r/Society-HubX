/**
 * Auth utility for SocietyNestX - Test
 */
const { expect } = require('@playwright/test');
const users = require('../fixtures/users.json');

async function loginAsAdmin(page) {
    await page.goto('/wp-admin');
    await page.fill('#user_login', users.admin.username);
    await page.fill('#user_pass', users.admin.password);
    await page.click('#wp-submit');
    await expect(page).toHaveURL(/.*wp-admin.*/);
}

async function loginAsResident(page, username = users.resident.username, password = users.resident.password) {
    await page.goto('/resident-dashboard');
    // Using selectors from resident-login.php
    await page.fill('#floatingInput', username);
    await page.fill('#floatingPassword', password);
    await page.click('#login-btn');

    // Wait for the page to reload or navigate away from the login form
    await page.waitForFunction(() => !document.getElementById('resident-login-form'), { timeout: 10000 });

    // Ensure we are on the dashboard and not wp-admin
    await page.waitForSelector('#btn-tab-home', { timeout: 10000 }); // Present in dashboard if logged in
}

module.exports = { loginAsAdmin, loginAsResident };
