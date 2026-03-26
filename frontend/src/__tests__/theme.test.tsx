import { describe, it, expect, beforeEach } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { renderWithProviders } from '@/test/test-utils'
import { useTheme } from '@/contexts/ThemeContext'

function ThemeTestHarness() {
  const { theme, resolvedTheme, setTheme } = useTheme()
  return (
    <div>
      <p data-testid="theme">{theme}</p>
      <p data-testid="resolved">{resolvedTheme}</p>
      <button data-testid="set-dark" onClick={() => setTheme('dark')}>Dark</button>
      <button data-testid="set-light" onClick={() => setTheme('light')}>Light</button>
      <button data-testid="set-system" onClick={() => setTheme('system')}>System</button>
    </div>
  )
}

describe('ThemeContext', () => {
  beforeEach(() => {
    document.documentElement.classList.remove('dark')
  })

  it('defaults to system theme', () => {
    renderWithProviders(<ThemeTestHarness />)
    expect(screen.getByTestId('theme')).toHaveTextContent('system')
  })

  it('sets dark theme and adds dark class to html', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ThemeTestHarness />)

    await user.click(screen.getByTestId('set-dark'))

    await waitFor(() => {
      expect(screen.getByTestId('theme')).toHaveTextContent('dark')
      expect(screen.getByTestId('resolved')).toHaveTextContent('dark')
    })
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('sets light theme and removes dark class', async () => {
    const user = userEvent.setup()
    document.documentElement.classList.add('dark')
    renderWithProviders(<ThemeTestHarness />)

    await user.click(screen.getByTestId('set-light'))

    await waitFor(() => {
      expect(screen.getByTestId('theme')).toHaveTextContent('light')
      expect(screen.getByTestId('resolved')).toHaveTextContent('light')
    })
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })

  it('persists theme in localStorage', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ThemeTestHarness />)

    await user.click(screen.getByTestId('set-dark'))

    expect(localStorage.getItem('theme')).toBe('dark')
  })

  it('reads persisted theme from localStorage', () => {
    localStorage.setItem('theme', 'dark')
    renderWithProviders(<ThemeTestHarness />)
    expect(screen.getByTestId('theme')).toHaveTextContent('dark')
    expect(screen.getByTestId('resolved')).toHaveTextContent('dark')
  })

  it('manual override takes priority over system', async () => {
    const user = userEvent.setup()
    renderWithProviders(<ThemeTestHarness />)

    // Start at system
    expect(screen.getByTestId('theme')).toHaveTextContent('system')

    // Override to dark
    await user.click(screen.getByTestId('set-dark'))
    expect(screen.getByTestId('theme')).toHaveTextContent('dark')
    expect(screen.getByTestId('resolved')).toHaveTextContent('dark')

    // Back to system
    await user.click(screen.getByTestId('set-system'))
    expect(screen.getByTestId('theme')).toHaveTextContent('system')
  })
})
