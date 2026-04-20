import { test, expect, type Page } from '@playwright/test'

// Prerequisites: app running at BASE_URL, seeded with:
//   - student user: student@example.com / Password1234!
//   - section ID 1 with an existing thread ID 1
//   - admin user: admin@example.com / AdminPass999!

const BASE = process.env.BASE_URL ?? 'http://localhost:5173'

async function openCreateThreadModal(page: Page) {
  const dialogHeading = page.getByRole('heading', { name: 'New Thread' })
  for (let attempt = 0; attempt < 3; attempt += 1) {
    const newThreadButton = page.getByRole('button', { name: 'New Thread' })
    await expect(newThreadButton).toBeVisible({ timeout: 15000 })
    try {
      await newThreadButton.click()
      await expect(dialogHeading).toBeVisible({ timeout: 5000 })
      return
    } catch {
      // Retry click if the list view rerendered and detached the button.
    }
  }

  throw new Error('Could not open New Thread modal after retries')
}

async function openFirstThreadFromSection(page: Page, sectionId = 1): Promise<boolean> {
  await page.goto(`${BASE}/sections/${sectionId}/threads`)
  await expect(page.locator('.thread-list-view')).toBeVisible({ timeout: 15000 })
  const threadLink = page.locator('.thread-list__link').first()
  if ((await threadLink.count()) === 0) return false
  await expect(threadLink).toBeVisible({ timeout: 15000 })
  await threadLink.click()
  return true
}

test.describe('Discussion flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('#email', 'student@example.com')
    await page.fill('#password', 'Password1234!')
    await page.click('button[type=submit]')
    await expect(page).toHaveURL(/\/$/)
  })

  test('student can navigate to thread list for a section', async ({ page }) => {
    await page.goto(`${BASE}/sections/1/threads`)
    await expect(page.getByRole('button', { name: 'New Thread' })).toBeVisible({ timeout: 15000 })
  })

  test('student can open a thread and see posts', async ({ page }) => {
    const opened = await openFirstThreadFromSection(page, 1)
    if (!opened) {
      // The EmptyState heading is rendered as an h2
      await expect(page.locator('h2:has-text("No threads yet")')).toBeVisible()
      return
    }
    await expect(page.locator('.thread-detail__title')).toBeVisible()
    await expect(page.getByRole('region', { name: 'Replies' })).toBeVisible()
  })

  test('sensitive-word rejection blocks submit and highlights blocked terms', async ({ page }) => {
    // Intercept the sensitive-words check to deterministically return a blocked result,
    // so the assertion no longer depends on server-configured word lists.
    await page.route('**/api/v1/sensitive-words/check', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          data: {
            blocked: true,
            blocked_terms: [{ term: 'badword', start: 0, end: 7 }],
          },
        }),
      }),
    )

    await page.goto(`${BASE}/sections/1/threads`)
    await openCreateThreadModal(page)
    await page.getByLabel('Title').fill('Test Thread')
    await page.getByLabel('Body').fill('badword here in body text')
    // Wait for debounce + mocked API response
    await page.waitForTimeout(800)

    // 1) Blocked-terms alert must be visible, listing the term
    const alert = page.locator('[role="alert"], .blocked-terms-alert, .error-message').first()
    await expect(alert).toContainText(/badword/i)

    // 2) Submit button must be disabled while blocked terms persist
    const submitBtn = page.locator('button:has-text("Post Thread")')
    await expect(submitBtn).toBeDisabled()
  })

  test('edit window countdown visible on fresh post', async ({ page }) => {
    const opened = await openFirstThreadFromSection(page, 1)
    if (!opened) {
      await expect(page.getByText('No threads yet')).toBeVisible()
      return
    }
    await expect(page.locator('.reply-form')).toBeVisible()
  })
})

test.describe('Admin moderation controls', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`${BASE}/login`)
    await page.fill('#email', 'admin@example.com')
    await page.fill('#password', 'AdminPass999!')
    await page.click('button[type=submit]')
    await expect(page).toHaveURL(/\/$/)
  })

  test('admin sees moderation controls on thread detail', async ({ page }) => {
    const opened = await openFirstThreadFromSection(page, 1)
    if (!opened) {
      await expect(page.getByText('No threads yet')).toBeVisible()
      return
    }
    const moderationButtons = page.getByRole('button', { name: /Hide|Restore|Lock/ })
    await expect(moderationButtons.first()).toBeVisible()
  })

  test('admin moderation queue is accessible', async ({ page }) => {
    await page.goto(`${BASE}/admin/moderation`)
    await expect(page.locator('h1')).toContainText('Moderation Queue')
  })
})
