import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end smoke tests (spec §53). Run against a seeded
 * local environment: `php artisan serve` (or the compose
 * stack) with the AgentFinanceSeeder applied — the sign-in
 * test uses admin@agentfinance.test / password.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    reporter: 'html',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: 'php artisan serve --port=8000',
        url: 'http://localhost:8000',
        reuseExistingServer: true,
        timeout: 120_000,
    },
});
