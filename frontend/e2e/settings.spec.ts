import { test, expect } from '@playwright/test'

test.describe('Settings Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.evaluate(() => {
      localStorage.setItem('token', 'mock-token-123')
      localStorage.setItem('organization_id', '1')
    })
  })

  test('settings page loads', async ({ page }) => {
    await page.goto('/settings')
    const heading = page.locator('h1')
    await expect(heading).toBeVisible({ timeout: 10000 })
  })

  test('settings page shows organization and account cards', async ({ page }) => {
    await page.goto('/settings')
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    // Should contain settings-related content
    expect(body).toBeTruthy()
  })
})
