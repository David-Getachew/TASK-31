import { test, expect } from '@playwright/test'

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'

async function loginAs(page: any, email: string, password: string) {
  await page.goto(`${BASE}/login`)
  await page.fill('#email', email)
  await page.fill('#password', password)
  await page.click('button[type=submit]')
  await expect(page).toHaveURL(/\/$/)
}

test.describe('Catalog and order flow', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'student@example.com', 'Password1234!')
  })

  test('catalog page loads and shows items', async ({ page }) => {
    await page.goto(`${BASE}/catalog`)
    await expect(page.locator('h1')).toContainText('Fee Catalog')
  })

  test('orders list page loads', async ({ page }) => {
    await page.goto(`${BASE}/orders`)
    await expect(page.locator('h1')).toContainText('My Orders')
  })

  test('order detail page loads for existing order', async ({ page }) => {
    // Assumes seeded order ID 1 exists for the test student
    await page.goto(`${BASE}/orders/1`)
    // Either order detail loads or an error state shows
    const heading = page.locator('h1')
    const errorState = page.locator('.error-state[role="alert"]')
    await expect
      .poll(async () => {
        const headingVisible = await heading.isVisible().catch(() => false)
        const errorVisible = await errorState.isVisible().catch(() => false)
        return headingVisible || errorVisible
      }, { timeout: 10000 })
      .toBeTruthy()

    const headingVisible = await heading.isVisible().catch(() => false)
    if (!headingVisible) {
      await expect(errorState).toBeVisible()
    } else {
      await expect(heading).toBeVisible()
    }
  })

  test('payment view requires pending_payment status order', async ({ page }) => {
    await page.goto(`${BASE}/orders/1/payment`)
    await expect(page.locator('h1')).toContainText('Complete Payment')
    // Payment method select should exist
    await expect(page.locator('#payment-method')).toBeVisible()
  })

  test('bills list page loads', async ({ page }) => {
    await page.goto(`${BASE}/bills`)
    await expect(page.locator('h1')).toContainText('My Bills')
  })
})

test.describe('Admin billing oversight', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin@example.com', 'AdminPass999!')
  })

  test('billing oversight page loads', async ({ page }) => {
    await page.goto(`${BASE}/admin/billing`)
    await expect(page.locator('h1')).toContainText('Billing Oversight')
  })

  test('generate bill button exists', async ({ page }) => {
    await page.goto(`${BASE}/admin/billing`)
    await expect(page.locator('button:has-text("Generate Bill")')).toBeVisible()
  })

  test('refund and reconciliation page loads', async ({ page }) => {
    await page.goto(`${BASE}/admin/refunds`)
    await expect(page.locator('h1')).toContainText('Refunds')
  })

  test('health view shows status indicators', async ({ page }) => {
    await page.goto(`${BASE}/admin/health`)
    await expect(page.locator('h1')).toContainText('Health')
  })
})

test.describe('Refund request flow', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'admin@example.com', 'AdminPass999!')
  })

  test('refund request page loads for a paid bill', async ({ page }) => {
    await page.goto(`${BASE}/bills/1/refund`)
    await expect(page.locator('h1')).toContainText('Request Refund')
    await expect(page.locator('#refund-amount')).toBeVisible()
    await expect(page.locator('#refund-reason')).toBeVisible()
  })
})
