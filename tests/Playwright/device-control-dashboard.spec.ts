import { test, expect } from '@playwright/test';

test.describe('Device control dashboard', () => {
  test('renders at least one control widget card', async ({ page }) => {
    test.skip(!process.env.PLAYWRIGHT_BASE_URL, 'PLAYWRIGHT_BASE_URL is required');

    await page.goto(`${process.env.PLAYWRIGHT_BASE_URL}/admin`);

    await page.getByLabel('Email address').fill(process.env.PLAYWRIGHT_EMAIL ?? '');
    await page.getByLabel('Password').fill(process.env.PLAYWRIGHT_PASSWORD ?? '');
    await page.getByRole('button', { name: /sign in|log in/i }).click();

    await page.goto(`${process.env.PLAYWRIGHT_BASE_URL}/admin/devices/1/control-dashboard`);

    await expect(page.locator('.dc-control-card').first()).toBeVisible();
  });
});
