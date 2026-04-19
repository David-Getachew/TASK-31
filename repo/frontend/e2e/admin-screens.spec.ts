import { test, expect } from '@playwright/test'

// Prerequisites: app running at BASE_URL, seeded with:
//   - admin user: admin@example.com / AdminPass999!

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'

test.describe('Admin screens', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('#email', 'admin@example.com')
    await page.fill('#password', 'AdminPass999!')
    await page.click('button[type=submit]')
    await expect(page).toHaveURL(/\/$/)
  })

  test('health view loads and shows status indicators', async ({ page }) => {
    await page.goto(`${BASE}/admin/health`)
    await expect(page.locator('h1')).toContainText('Health')
    await expect(page.locator('[class*="status"], [class*="health"], [data-testid*="status"]').first()).toBeVisible()
  })

  test('diagnostics admin view loads and trigger button is present', async ({ page }) => {
    await page.goto(`${BASE}/admin/diagnostics`)
    await expect(page.locator('h1')).toContainText('Diagnostic Exports')
    await expect(page.locator('button:has-text("Export"), button:has-text("Trigger"), button:has-text("Generate")')).toBeVisible()
  })

  test('backup admin view loads and history table is present', async ({ page }) => {
    await page.goto(`${BASE}/admin/backups`)
    await expect(page.locator('h1')).toContainText('Backup')
    // Either shows a table of backups or an empty state message
    await expect(page.locator('table').or(page.getByText('No backups found.'))).toBeVisible()
  })

  test('DR admin view loads and drill form is present', async ({ page }) => {
    await page.goto(`${BASE}/admin/dr`)
    await expect(page.locator('h1')).toContainText('Disaster Recovery')
    await expect(page.locator('form.drill-form')).toBeVisible()
  })
})
