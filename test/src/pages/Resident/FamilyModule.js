/**
 * Page Object: Resident Family Module
 */
class FamilyModule {
    constructor(page) {
        this.page = page;
        this.buttonAdd = page.locator('#addFamily');
        this.modal = page.locator('#familyModal');
        this.inputName = this.modal.locator('input[name="name"]');
        this.inputRelation = this.modal.locator('select[name="relation"]');
        this.inputAge = this.modal.locator('input[name="age"]');
        this.inputBloodGroup = this.modal.locator('select[name="blood_group"]');
        this.inputPhone = this.modal.locator('input[name="phone"]');
        this.buttonSubmit = this.modal.locator('button[type="submit"]');

        this.familyList = page.locator('#familyContainer');
    }

    async addMember(data) {
        const modal = this.page.locator('#familyModal');
        await this.buttonAdd.click();

        await modal.locator('input[name="name"]').fill(data.name);
        await modal.locator('select[name="relation"]').selectOption({ label: data.relation });
        await modal.locator('input[name="age"]').fill(data.age.toString());
        if (data.bloodGroup) await modal.locator('select[name="blood_group"]').selectOption({ label: data.bloodGroup });
        if (data.phone) await modal.locator('input[name="phone"]').fill(data.phone);

        await modal.locator('button[type="submit"]').click();
        await this.page.waitForLoadState('networkidle');
    }

    async getMemberRow(name) {
        return this.page.locator(`#familyContainer li:has-text("${name}")`);
    }

    async editMember(name, newData) {
        const row = await this.getMemberRow(name);
        await row.locator('[data-bs-toggle="dropdown"]').click();
        await row.locator('.js-edit-family').click();

        const modal = this.page.locator('#editFamilyModal');
        await this.page.waitForSelector('#editFamilyModal.show');

        if (newData.name) await modal.locator('input[name="name"]').fill(newData.name);
        if (newData.relation) await modal.locator('select[name="relation"]').selectOption({ label: newData.relation });
        if (newData.age) await modal.locator('input[name="age"]').fill(newData.age.toString());

        await modal.locator('button[type="submit"]').click();
        await this.page.waitForLoadState('networkidle');
    }

    async deleteMember(name) {
        const row = await this.getMemberRow(name);

        await row.locator('[data-bs-toggle="dropdown"]').click();
        await row.locator('.js-delete-family-frontend').click();

        await this.page.waitForLoadState('networkidle');
    }
}

module.exports = { FamilyModule };
