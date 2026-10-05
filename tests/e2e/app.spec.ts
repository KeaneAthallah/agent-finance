import { expect, test } from '@playwright/test';

test.describe('authentication', () => {
    test('shows the sign-in page with a security policy', async ({ page }) => {
        await page.goto('/login');

        await expect(
            page.getByRole('heading', { name: 'Sign in' }),
        ).toBeVisible();

        // Spec §35: hardening headers on every response.
        const headers = await page.response()?.allHeaders();

        expect(headers?.['x-content-type-options']).toBe('nosniff');
        expect(headers?.['x-frame-options']).toBe('DENY');
    });

    test('signs in and lands on the dashboard', async ({ page }) => {
        await page.goto('/login');

        await page.getByLabel('Email').fill('admin@agentfinance.test');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        // The sidebar is the authenticated shell.
        await expect(
            page.getByRole('link', { name: 'Dashboard' }),
        ).toBeVisible();
    });

    test('rejects an unknown user', async ({ page }) => {
        await page.goto('/login');

        await page.getByLabel('Email').fill('nobody@agentfinance.test');
        await page.getByLabel('Password').fill('password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(
            page.getByText('These credentials do not match our records.'),
        ).toBeVisible();
    });
});
