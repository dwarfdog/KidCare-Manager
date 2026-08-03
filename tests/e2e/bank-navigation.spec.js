const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');

test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Identifiant').fill('e2e-parent');
    await page.getByLabel('Mot de passe').fill('e2e-password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL('/');
});

test('filters without reload, paginates and focuses a created operation', async ({ page }) => {
    const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, '..', '..', 'var', 'e2e-fixture.json'), 'utf8'));
    await page.goto(`/bank/accounts/${fixture.account}`);
    await expect(page.locator('#real-transactions .bank-transaction-row')).toHaveCount(25);
    await expect(page.locator('#real-transactions')).toContainText('55 operations');
    await expect(page.locator('#real-transactions')).toContainText('Page 1 / 3');

    const responsePromise = page.waitForResponse((response) => response.url().includes('/transactions/real') && response.ok());
    await page.locator('select[name="real_type"]').selectOption('income');
    await responsePromise;
    await expect(page).toHaveURL(/real_type=income/);
    await expect(page.locator('#real-transactions .bank-transaction-row')).toHaveCount(25);
    await expect(page.locator('#real-transactions')).toContainText('27 operations');
    await expect(page.locator('#real-transactions')).toContainText('Page 1 / 2');

    await page.goto(`/bank/transactions/new?account=${fixture.account}`);
    await page.locator('select[name="bank_transaction[type]"]').selectOption('expense');
    await page.locator('input[name="bank_transaction[date]"]').fill(new Date().toISOString().slice(0, 10));
    await page.locator('input[name="bank_transaction[amount]"]').fill('12.34');
    await page.locator('input[name="bank_transaction[label]"]').fill('Operation ciblee E2E');
    await page.locator('select[name="bank_transaction[status]"]').selectOption('real');
    await page.getByRole('button', { name: 'Enregistrer' }).click();

    await expect(page).toHaveURL(new RegExp(`/bank/accounts/${fixture.account}\\?focus=`));
    const focused = page.locator('.bank-transaction-row.is-focused');
    await expect(focused).toContainText('Operation ciblee E2E');
});
