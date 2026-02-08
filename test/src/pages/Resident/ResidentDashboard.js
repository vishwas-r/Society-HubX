/**
 * Page Object: Resident Dashboard
 */
class ResidentDashboard {
    constructor(page) {
        this.page = page;
        this.tabs = {
            community: page.locator('#tab-community'),
            accounts: page.locator('#tab-accounts'),
            facilities: page.locator('#tab-facilities'),
            documents: page.locator('#tab-documents'),
        };
        this.containers = {
            family: page.locator('#familyContainer'),
            help: page.locator('#dailyHelpContainer'),
            vehicles: page.locator('#vehicleContainer'),
            documents: page.locator('#documentContainer'),
        };
        this.buttons = {
            addFamily: page.locator('#addFamily'),
            addHelp: page.locator('#addDailyHelp'),
            addVehicle: page.locator('#addVehicle'),
            addDocument: page.locator('#addDocument'),
        };
        this.badges = {
            pending: page.locator('.badge.bg-warning'),
            approved: page.locator('.badge.bg-success'),
            deletionPending: page.locator('.badge.bg-danger:has-text("DELETION PENDING")'),
        };
    }

    async navigateTo(tabName) {
        await this.page.click(`#btn-tab-${tabName}`);
        await this.page.waitForSelector(`#tab-${tabName}.d-block`);
    }

    async getBadgeStatus(entityName) {
        // Vehicles and other items are in li elements
        const row = this.page.locator(`li:has-text("${entityName}")`);
        return row.locator('.badge');
    }
}

module.exports = { ResidentDashboard };
