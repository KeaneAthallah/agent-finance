import { expect, test } from '@playwright/test';

// The login route is throttled (spec §35: throttle:5,1,
// keyed by IP). Every request to /login counts, including
// the GET that Inertia issues when it follows the 302
// back from a failed sign-in. The budget below is:
//   sign-in test:  GET /login + POST /login            = 2
//   reject test:   GET /login + POST /login + redirect = 3
// so the tests must run one at a time — parallel
// workers plus any retry would trip the limiter.
test.describe.configure({ mode: 'serial' });

test.describe('authentication', () => {
    test('signs in and lands on the dashboard', async ({ page }) => {
        const response = await page.goto('/login');

        // Spec §35: hardening headers on every response.
        const headers = await response?.allHeaders();

        expect(headers?.['x-content-type-options']).toBe('nosniff');
        expect(headers?.['x-frame-options']).toBe('DENY');

        // Role-based locators: the password field has a
        // "Show password" toggle whose label is a substring
        // match for getByLabel('Password').
        await page
            .getByRole('textbox', { name: 'Email' })
            .fill('admin@agentfinance.test');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        // The sidebar is the authenticated shell.
        await expect(
            page.getByRole('link', { name: 'Dashboard' }),
        ).toBeVisible();
    });

    test('rejects an unknown user', async ({ page }) => {
        await page.goto('/login');

        await page
            .getByRole('textbox', { name: 'Email' })
            .fill('nobody@agentfinance.test');
        await page.getByRole('textbox', { name: 'Password' }).fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        // Laravel attaches auth.failed to the email field.
        await expect(
            page.getByText('These credentials do not match our records.'),
        ).toBeVisible();
    });
});
