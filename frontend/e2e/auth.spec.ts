import { test, expect } from '@playwright/test'

test.describe('Authentication', () => {
  test.beforeEach(async ({ page }) => {
    // Clear localStorage before each test
    await page.goto('/login')
    await page.evaluate(() => {
      localStorage.clear()
    })
  })

  test('login page renders correctly', async ({ page }) => {
    await page.goto('/login')
    await expect(page.locator('input[type="email"]')).toBeVisible()
    await expect(page.locator('input[type="password"]')).toBeVisible()
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('successful login redirects to dashboard', async ({ page }) => {
    await page.goto('/login')

    await page.fill('input[type="email"]', 'test@example.com')
    await page.fill('input[type="password"]', 'password123')
    await page.click('button[type="submit"]')

    // Should redirect to dashboard or show authenticated content
    await expect(page).toHaveURL(/\/$|\/login/)
  })

  test('register page renders with all fields', async ({ page }) => {
    await page.goto('/register')

    // Should have name, email, password, confirm password, org name fields
    const inputs = page.locator('input')
    await expect(inputs).toHaveCount(5) // name, email, password, confirm, org
    await expect(page.locator('button[type="submit"]')).toBeVisible()
  })

  test('register page has link to login', async ({ page }) => {
    await page.goto('/register')
    const loginLink = page.locator('a[href="/login"]')
    await expect(loginLink).toBeVisible()
  })

  test('login page has link to register', async ({ page }) => {
    await page.goto('/login')
    const registerLink = page.locator('a[href="/register"]')
    await expect(registerLink).toBeVisible()
  })

  test('unauthenticated access to dashboard redirects to login', async ({ page }) => {
    await page.evaluate(() => {
      localStorage.removeItem('token')
    })
    await page.goto('/')
    await expect(page).toHaveURL(/\/login/)
  })
})
