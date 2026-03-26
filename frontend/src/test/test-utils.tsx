import { type ReactNode } from 'react'
import { render, type RenderOptions } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider } from '@/contexts/AuthContext'
import { ThemeProvider } from '@/contexts/ThemeContext'
import '@/i18n'

function createTestQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        gcTime: 0,
        staleTime: 0,
      },
      mutations: {
        retry: false,
      },
    },
  })
}

interface WrapperOptions {
  /** Set a token in localStorage before rendering (simulates logged-in state) */
  authenticated?: boolean
}

function createWrapper(options: WrapperOptions = {}) {
  const queryClient = createTestQueryClient()

  if (options.authenticated) {
    localStorage.setItem('token', 'mock-token-123')
    localStorage.setItem('organization_id', '1')
  }

  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={queryClient}>
        <ThemeProvider>
          <AuthProvider>
            {children}
          </AuthProvider>
        </ThemeProvider>
      </QueryClientProvider>
    )
  }
}

export function renderWithProviders(
  ui: React.ReactElement,
  options: WrapperOptions & Omit<RenderOptions, 'wrapper'> = {},
) {
  const { authenticated, ...renderOptions } = options
  const Wrapper = createWrapper({ authenticated })
  return {
    ...render(ui, { wrapper: Wrapper, ...renderOptions }),
    queryClient: new QueryClient(),
  }
}

export { createTestQueryClient }
