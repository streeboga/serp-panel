import { test, expect } from '@playwright/test'

const BASE = 'http://localhost:5174'

async function login(page: any) {
  await page.goto(`${BASE}/login`)
  await page.waitForTimeout(500)
  const loginPromise = page.waitForResponse((resp: any) =>
    resp.url().includes('/auth/login') && resp.status() === 200
  )
  await page.fill('input[type="email"]', 'admin@serp.test')
  await page.fill('input[type="password"]', 'password')
  await page.click('button[type="submit"]')
  await loginPromise
  await page.waitForURL('**/', { timeout: 10000 })
  await page.waitForTimeout(500)
}

test('Smoke test - all pages load without errors', async ({ page }) => {
  const jsErrors: string[] = []
  page.on('pageerror', (err) => jsErrors.push(err.message))

  await login(page)

  const routes = [
    '/',
    '/projects',
    '/classification',
    '/alerts',
    '/scrapers',
    '/schedules',
    '/wordstat-schedules',
    '/settings',
  ]

  for (const route of routes) {
    await page.goto(`${BASE}${route}`)
    await page.waitForTimeout(1500)
    // Should not redirect to login
    expect(page.url(), `${route} redirected to login`).not.toContain('login')
  }

  // Verify no JS errors
  expect(jsErrors, 'JavaScript errors found').toEqual([])
})

test('Settings has all sections', async ({ page }) => {
  await login(page)
  await page.goto(`${BASE}/settings`)
  await page.waitForTimeout(2000)

  await expect(page.locator('text=Organization')).toBeVisible()
  await expect(page.locator('text=Your Account')).toBeVisible()
  await expect(page.locator('text=Appearance')).toBeVisible()
  await expect(page.locator('text=Billing')).toBeVisible()
  await expect(page.locator('text=Members')).toBeVisible()
  // Role badge is visible (admin user)
  await expect(page.getByRole('cell', { name: 'admin@serp.test' })).toBeVisible()
})

test('Navigation has all items', async ({ page }) => {
  await login(page)
  await page.goto(`${BASE}/`)
  await page.waitForTimeout(1000)

  await expect(page.getByRole('link', { name: 'Dashboard' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Projects' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Classification' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Alerts' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Scrapers' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Schedules', exact: true })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Wordstat Schedules' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Settings' })).toBeVisible()
})
