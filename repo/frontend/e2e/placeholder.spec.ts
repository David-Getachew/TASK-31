import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'

test.describe('CampusLearn application', () => {
  test('home page loads with correct title', async ({ page }) => {
    await page.goto(`${BASE}/`)
    await expect(page).toHaveTitle(/CampusLearn/)
  })

  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto(`${BASE}/`)
    await expect(page).toHaveURL(/\/login/)
  })
})
