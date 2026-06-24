import { test, expect } from '@playwright/test';

const PRIMARY_ROUTES = [
  { path: '/', title: /Dashboard|WNBA Stat Spot/i },
  { path: '/players', title: /Players|WNBA/i },
  { path: '/teams', title: /Teams|WNBA/i },
  { path: '/games', title: /Games|WNBA/i },
  { path: '/stats', title: /Statistics|Stats|WNBA/i },
  { path: '/reports/predictions', title: /Prediction|WNBA/i },
  { path: '/reports/todays-props', title: /Props|WNBA/i },
  { path: '/advanced/prop-scanner', title: /Scanner|Prop|WNBA/i },
  { path: '/advanced/live-odds', title: /Odds|Live|WNBA/i },
  { path: '/compare/players', title: /Compare|Players|WNBA/i },
  { path: '/methodology', title: /Methodology|WNBA/i },
  { path: '/advanced', title: /Advanced|WNBA/i },
];

async function waitForShell(page: import('@playwright/test').Page) {
  await page.goto('/');
  await page.waitForLoadState('domcontentloaded');
  await expect(page.locator('.ds-topbar')).toBeVisible({ timeout: 30_000 });
}

test.describe('WNBA Stat Spot redesign shell', () => {
  test('dashboard renders redesigned layout with logo', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await waitForShell(page);
    await expect(page.locator('.ds-topbar .logo-dark img.logo-lg')).toBeVisible();
    await expect(page.getByRole('heading', { name: /dashboard/i })).toBeVisible();
  });

  test('primary top navigation links are present', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await waitForShell(page);
    const nav = page.locator('nav.ds-topbar__nav');
    await expect(nav.getByRole('link', { name: 'Home' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Players' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Teams' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Games' })).toBeVisible();
  });

  test('logo navigates home', async ({ page }) => {
    await page.goto('/players');
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.ds-topbar .logo-dark')).toBeVisible({ timeout: 30_000 });
    await page.locator('.ds-topbar .logo-dark').click();
    await expect(page).toHaveURL('/');
  });
});

test.describe('Route parity', () => {
  for (const route of PRIMARY_ROUTES) {
    test(`loads ${route.path}`, async ({ page }) => {
      const response = await page.goto(route.path);
      expect(response?.status()).toBeLessThan(500);
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('.ds-shell')).toBeVisible({ timeout: 30_000 });
      await expect(page).toHaveTitle(route.title);
    });
  }
});

test.describe('Navigation flow', () => {
  test('can navigate from dashboard to players via top nav', async ({ page }) => {
    await page.setViewportSize({ width: 1400, height: 900 });
    await waitForShell(page);
    await page.locator('nav.ds-topbar__nav').getByRole('link', { name: 'Players' }).click();
    await expect(page).toHaveURL(/\/players/);
  });

  test('mobile menu toggle opens sidebar', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await waitForShell(page);
    await page.getByRole('button', { name: 'Open menu' }).click();
    await expect(page.locator('.main-nav')).toBeVisible();
    await page.keyboard.press('Escape');
  });
});
