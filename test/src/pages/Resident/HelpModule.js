/**
 * Page Object: Resident Daily Help Module
 */
class HelpModule {
    constructor(page) {
        this.page = page;
        this.buttonAdd = page.locator('#addDailyHelp');
        this.modal = page.locator('#helpModal');
        this.inputName = this.modal.locator('input[name="name"]');
        this.inputCategory = this.modal.locator('select[name="category"]');
        this.inputRole = this.modal.locator('select[name="role"]');
        this.inputPhone = this.modal.locator('input[name="phone"]');
        this.inputGender = this.modal.locator('select[name="sex"]'); // Corrected name: sex in HTML
        this.buttonSubmit = this.modal.locator('button[type="submit"]');

        this.helpList = page.locator('#dailyHelpContainer');
    }

    async addHelp(data) {
        const modal = this.page.locator('#helpModal');
        await this.buttonAdd.click();

        await modal.locator('input[name="name"]').fill(data.name);
        await modal.locator('select[name="category"]').selectOption({ label: data.category });
        await modal.locator('select[name="role"]').selectOption({ label: data.role });
        await modal.locator('input[name="phone"]').fill(data.phone);
        if (data.gender) await modal.locator('select[name="sex"]').selectOption({ label: data.gender });

        await modal.locator('button[type="submit"]').click();
        await this.page.waitForLoadState('networkidle');
    }

    async getHelpRow(name) {
        return this.page.locator(`#dailyHelpContainer li:has-text("${name}")`);
    }

    async editHelp(name, newData) {
        const row = await this.getHelpRow(name);
        await row.locator('[data-bs-toggle="dropdown"]').click();
        await row.locator('.js-edit-help').click();

        const modal = this.page.locator('#editHelpModal');
        await this.page.waitForSelector('#editHelpModal.show');

        if (newData.name) await modal.locator('input[name="name"]').fill(newData.name);
        if (newData.role) await modal.locator('select[name="role"]').selectOption({ label: newData.role });

        await modal.locator('button[type="submit"]').click();
        await this.page.waitForLoadState('networkidle');
    }

    async deleteHelp(name) {
        const row = await this.getHelpRow(name);

        await row.locator('[data-bs-toggle="dropdown"]').click();
        await row.locator('.js-delete-help-frontend').click();

        await this.page.waitForLoadState('networkidle');
    }
}

module.exports = { HelpModule };
