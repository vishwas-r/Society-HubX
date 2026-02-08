/**
 * Page Object: Resident Vehicle Module
 */
class VehicleModule {
    constructor(page) {
        this.page = page;
        this.buttonAdd = page.locator('#addVehicle');
        this.menuFilter = page.locator('#vehicle-filter');
        this.inputNumber = page.locator('#vehicleModal input[name="number"]');
        this.inputType = page.locator('#vehicleModal select[name="type"]');
        this.inputBrand = page.locator('#vehicleModal input[name="brand"]');
        this.inputModel = page.locator('#vehicleModal input[name="model"]');
        this.buttonSubmit = page.locator('#vehicleModal button[type="submit"]');

        this.vehicleList = page.locator('#vehicleContainer');
    }

    async addVehicle(data) {
        await this.buttonAdd.click();
        await this.inputNumber.fill(data.number);
        await this.inputType.selectOption({ label: data.type });

        if (data.brand) await this.inputBrand.fill(data.brand);
        if (data.model) await this.inputModel.fill(data.model);

        await this.buttonSubmit.click();
        await this.page.waitForLoadState('networkidle');
    }

    async editVehicle(vehicleNumber, newData) {
        // Find the row for this vehicle
        const row = this.page.locator(`li:has-text("${vehicleNumber}")`, { hasText: vehicleNumber });

        // Click the dropdown menu trigger
        await row.locator('[data-bs-toggle="dropdown"]').click();

        // Click Edit
        await row.locator('.js-edit-vehicle').click();

        // Wait for modal transition
        await this.page.waitForSelector('#vehicleModal.show');

        if (newData.brand) await this.inputBrand.fill(newData.brand);
        if (newData.model) await this.inputModel.fill(newData.model);

        await this.buttonSubmit.click();
        await this.page.waitForLoadState('networkidle');
    }

    async getVehicleRow(vehicleNumber) {
        return this.page.locator(`li:has-text("${vehicleNumber}")`);
    }

    async deleteVehicle(vehicleNumber) {
        const row = await this.getVehicleRow(vehicleNumber);

        // Click the three-dots dropdown
        await row.locator('button[data-bs-toggle="dropdown"]').click();
        // Click "Deregister"
        await row.locator('button:has-text("Deregister")').click();

        await this.page.waitForLoadState('networkidle');
    }
}

module.exports = { VehicleModule };
