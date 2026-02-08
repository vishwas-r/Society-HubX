const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './src/tests',
    fullyParallel: false, // Sequential is safer for state-based WP tests
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1, // Single worker to avoid DB collisions on local
    reporter: 'html',
    use: {
        baseURL: 'http://test.local',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'on-first-retry',
        headless: false, // As requested for headed verification
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        }
    ],
});
