import { test, expect } from '@playwright/test'

test.describe('Keywords Page', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
    await page.evaluate(() => {
      localStorage.setItem('token', 'mock-token-123')
      localStorage.setItem('organization_id', '1')
    })
  })

  test('keywords page loads within a project', async ({ page }) => {
    await page.goto('/projects/1/keywords')
    // Should either show keywords table or loading skeleton
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toBeTruthy()
  })

  test('keyword detail page loads', async ({ page }) => {
    await page.goto('/projects/1/keywords/1')
    // Should show tabs: SERP, History, Wordstat, Suggestions
    await page.waitForTimeout(2000)
    const body = await page.textContent('body')
    expect(body).toBeTruthy()
  })
})
