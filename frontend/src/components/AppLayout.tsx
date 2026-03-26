import { useCallback } from 'react'
import { Link, useNavigate } from '@tanstack/react-router'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/contexts/AuthContext'
import { useTheme } from '@/contexts/ThemeContext'
import { Button } from '@/components/ui/button'
import { queryKeys } from '@/lib/query-keys'
import api from '@/lib/api'
import { OrgSwitcher } from '@/components/OrgSwitcher'

const SunIcon = (
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mr-2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
)

const MoonIcon = (
  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mr-2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
)

const navItems = [
  { labelKey: 'nav.dashboard', to: '/' as const, prefetchKey: undefined },
  { labelKey: 'nav.projects', to: '/projects' as const, prefetchKey: queryKeys.projects.list() },
  { labelKey: 'nav.classification', to: '/classification' as const, prefetchKey: queryKeys.classification.rules },
  { labelKey: 'nav.alerts', to: '/alerts' as const, prefetchKey: queryKeys.alerts.all },
  { labelKey: 'nav.scrapers', to: '/scrapers' as const, prefetchKey: queryKeys.scrapers.all },
  { labelKey: 'nav.schedules', to: '/schedules' as const, prefetchKey: queryKeys.schedules.all },
  { labelKey: 'nav.wordstatSchedules', to: '/wordstat-schedules' as const, prefetchKey: queryKeys.wordstatSchedules.all },
  { labelKey: 'nav.settings', to: '/settings' as const, prefetchKey: queryKeys.organization.members },
]

// API paths matching nav prefetch keys
const prefetchApis: Record<string, string> = {
  projects: '/projects',
  'classification-rules': '/classification/rules',
  alerts: '/alerts',
  scrapers: '/scrapers',
  schedules: '/schedules',
  'wordstat-schedules': '/wordstat-schedules',
  organization: '/organization/members',
}

export function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth()
  const { resolvedTheme, setTheme, theme } = useTheme()
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const handleLogout = useCallback(() => {
    logout()
    navigate({ to: '/login' })
  }, [logout, navigate])

  const handlePrefetch = useCallback(
    (prefetchKey: readonly string[] | undefined) => {
      if (!prefetchKey) return
      const rootKey = prefetchKey[0]
      const apiPath = prefetchApis[rootKey]
      if (apiPath) {
        queryClient.prefetchQuery({
          queryKey: prefetchKey,
          queryFn: () => api.get(apiPath).then((r) => r.data),
          staleTime: 30_000,
        })
      }
    },
    [queryClient],
  )

  const toggleTheme = useCallback(() => {
    if (theme === 'system') {
      setTheme(resolvedTheme === 'dark' ? 'light' : 'dark')
    } else if (theme === 'dark') {
      setTheme('light')
    } else {
      setTheme('dark')
    }
  }, [theme, resolvedTheme, setTheme])

  return (
    <div className="min-h-screen flex">
      <aside className="w-64 bg-sidebar text-sidebar-foreground flex flex-col border-r border-sidebar-border">
        <div className="p-4 border-b border-sidebar-border">
          <h1 className="text-xl font-bold">{t('app.name')}</h1>
          <p className="text-sm text-muted-foreground mt-1">{user?.name}</p>
          <OrgSwitcher />
        </div>
        <nav className="flex-1 p-4 space-y-1">
          {navItems.map((item) => (
            <Link
              key={item.to}
              to={item.to}
              className="block px-3 py-2 rounded text-sm hover:bg-sidebar-accent transition-colors [&.active]:bg-sidebar-accent"
              onMouseEnter={() => handlePrefetch(item.prefetchKey)}
            >
              {t(item.labelKey)}
            </Link>
          ))}
        </nav>
        <div className="p-4 border-t border-sidebar-border space-y-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={toggleTheme}
            className="w-full justify-start text-muted-foreground hover:text-foreground"
          >
            {resolvedTheme === 'dark' ? SunIcon : MoonIcon}
            {resolvedTheme === 'dark' ? t('settings.themeLight') : t('settings.themeDark')}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={handleLogout}
            className="w-full text-muted-foreground hover:text-foreground"
          >
            {t('auth.logout')}
          </Button>
        </div>
      </aside>
      <main className="flex-1 bg-background p-6 overflow-auto">{children}</main>
    </div>
  )
}
