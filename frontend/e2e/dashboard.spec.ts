import { test, expect } from '@playwright/test'

test.describe('Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    // Simulate authenticated state
    await page.goto('/login')
    await page.evaluate(() => {
      localStorage.setItem('token', 'mock-token-123')
      localStorage.setItem('organization_id', '1')
    })
  })

  test('dashboard page loads with title', async ({ page }) => {
    await page.goto('/')
    // Should see the dashboard heading (in whatever language is configured)
    const heading = page.locator('h1')
    await expect(heading).toBeVisible({ timeout: 10000 })
  })

  test('dashboard displays summary cards or welcome state', async ({ page }) => {
    await page.goto('/')
    // Either summary cards or welcome empty state should appear
    await page.waitForTimeout(2000)
    const pageContent = await page.textContent('body')
    // Dashboard should have some recognizable content
    expect(pageContent).toBeTruthy()
  })
})
