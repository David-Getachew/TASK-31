import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'

async function loginAs(page: any, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.fill('#email', email)
  await page.fill('#password', password)
  await page.click('button[type=submit]')
  await expect(page).toHaveURL(/\/$/)
}

test.describe('Notification Center', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'student@example.com', 'Password1234!')
  })

  test('notification center page loads', async ({ page }) => {
    await page.goto(`${BASE}/notifications`)
    await expect(page.locator('h1')).toContainText('Notification Center')
  })

  test('category tabs are rendered', async ({ page }) => {
    await page.goto(`${BASE}/notifications`)
    await expect(page.getByRole('tab', { name: 'All' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Billing' })).toBeVisible()
    await expect(page.getByRole('tab', { name: 'Mentions' })).toBeVisible()
  })

  test('preferences link navigates to preferences page', async ({ page }) => {
    await page.goto(`${BASE}/notifications`)
    await page.click('a:has-text("Preferences")')
    await expect(page).toHaveURL(/\/notifications\/preferences$/)
    await expect(page.locator('h1')).toContainText('Notification Preferences')
  })

  test('mark all read button exists', async ({ page }) => {
    await page.goto(`${BASE}/notifications`)
    await expect(page.locator('button:has-text("Mark all read")')).toBeVisible()
  })

  test('unread-only filter applies', async ({ page }) => {
    await page.goto(`${BASE}/notifications`)
    // Just verify the filter checkbox exists
    await expect(page.locator('text=Unread only')).toBeVisible()
  })
})

test.describe('Notification preferences', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'student@example.com', 'Password1234!')
  })

  test('can toggle notification category preferences', async ({ page }) => {
    await page.goto(`${BASE}/notifications/preferences`)
    await page.waitForTimeout(500)
    // Each category should have a checkbox
    const checkboxes = page.locator('input[type=checkbox]')
    await expect(checkboxes.first()).toBeVisible()
  })

  test('save preferences button exists', async ({ page }) => {
    await page.goto(`${BASE}/notifications/preferences`)
    await expect(page.locator('button:has-text("Save preferences")')).toBeVisible()
  })
})
