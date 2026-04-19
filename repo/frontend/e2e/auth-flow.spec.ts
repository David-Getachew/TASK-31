import { test, expect } from '@playwright/test'

// Prerequisites: app running at BASE_URL, seeded with:
//   - active user: student@example.com / Password1234!

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'
const HOME_URL_RE = /\/($|\?)/

test.describe('Auth flow', () => {
  test('unauthenticated user is redirected to login from home', async ({ page }) => {
    await page.goto(`${BASE}/`)
    await expect(page).toHaveURL(/\/login/)
  })

  test('successful login redirects to home', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('#email', 'student@example.com')
    await page.fill('#password', 'Password1234!')
    await page.click('button[type=submit]')
    await expect(page).toHaveURL(HOME_URL_RE)
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible()
  })

  test('failed login shows error message', async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('#email', 'nobody@example.com')
    await page.fill('#password', 'wrongpassword')
    await page.click('button[type=submit]')
    await expect(page.locator('[role=alert], .form-error, .alert--error')).toBeVisible()
  })

  test('already-authenticated user navigating to /login is redirected to home', async ({ page }) => {
    // Log in first
    await page.goto(`${BASE}/login`)
    await page.fill('#email', 'student@example.com')
    await page.fill('#password', 'Password1234!')
    await page.click('button[type=submit]')
    await expect(page).toHaveURL(HOME_URL_RE)

    // Navigate back to /login — should redirect to home
    await page.goto(`${BASE}/login`)
    await expect(page).toHaveURL(HOME_URL_RE)
  })
})
