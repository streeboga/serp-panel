import { useCallback, useState, useMemo } from 'react'
import { Link, useNavigate, useRouterState } from '@tanstack/react-router'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/contexts/AuthContext'
import { useTheme } from '@/contexts/ThemeContext'
import { Button } from '@/components/ui/button'
import { queryKeys } from '@/lib/query-keys'
import api from '@/lib/api'
import { OrgSwitcher } from '@/components/OrgSwitcher'
import {
  LayoutDashboard,
  FolderKanban,
  Bell,
  Settings,
  Bot,
  CalendarClock,
  BarChart3,
  Tag,
  Sun,
  Moon,
  LogOut,
  ChevronDown,
  ChevronRight,
  PanelLeftClose,
  PanelLeft,
} from 'lucide-react'

interface NavItem {
  labelKey: string
  to: string
  icon: React.ReactNode
  prefetchKey?: readonly string[]
}

const mainNavItems: NavItem[] = [
  { labelKey: 'nav.dashboard', to: '/', icon: <LayoutDashboard className="size-4" /> },
  { labelKey: 'nav.projects', to: '/projects', icon: <FolderKanban className="size-4" />, prefetchKey: queryKeys.projects.list() },
  { labelKey: 'nav.alerts', to: '/alerts', icon: <Bell className="size-4" />, prefetchKey: queryKeys.alerts.all },
]

const configNavItems: NavItem[] = [
  { labelKey: 'nav.scrapers', to: '/scrapers', icon: <Bot className="size-4" />, prefetchKey: queryKeys.scrapers.all },
  { labelKey: 'nav.schedules', to: '/schedules', icon: <CalendarClock className="size-4" />, prefetchKey: queryKeys.schedules.all },
  { labelKey: 'nav.wordstatSchedules', to: '/wordstat-schedules', icon: <BarChart3 className="size-4" />, prefetchKey: queryKeys.wordstatSchedules.all },
  { labelKey: 'nav.classification', to: '/classification', icon: <Tag className="size-4" />, prefetchKey: queryKeys.classification.rules },
  { labelKey: 'nav.settings', to: '/settings', icon: <Settings className="size-4" />, prefetchKey: queryKeys.organization.members },
]

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
  const [collapsed, setCollapsed] = useState(false)
  const routerState = useRouterState()
  const currentPath = routerState.location.pathname

  const isConfigRoute = useMemo(
    () => configNavItems.some((item) => currentPath === item.to || currentPath.startsWith(item.to + '/')),
    [currentPath],
  )
  const [configOpen, setConfigOpen] = useState(isConfigRoute)

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

  const renderNavItem = (item: NavItem) => (
    <Link
      key={item.to}
      to={item.to as '/'}
      className={`flex items-center gap-2.5 rounded-lg text-[13px] transition-all duration-150
        hover:bg-sidebar-accent hover:text-sidebar-accent-foreground
        [&.active]:bg-accent-blue/10 [&.active]:text-accent-blue [&.active]:font-medium
        ${collapsed ? 'justify-center px-2 py-2' : 'px-2.5 py-1.5'}`}
      onMouseEnter={() => handlePrefetch(item.prefetchKey)}
      title={collapsed ? t(item.labelKey) : undefined}
    >
      {item.icon}
      {!collapsed && <span>{t(item.labelKey)}</span>}
    </Link>
  )

  return (
    <div className="min-h-screen flex">
      <aside className={`${collapsed ? 'w-14' : 'w-56'} bg-sidebar text-sidebar-foreground flex flex-col border-r border-sidebar-border transition-all duration-200 shrink-0`}>
        {/* Header */}
        <div className={`flex items-center border-b border-sidebar-border ${collapsed ? 'justify-center p-2' : 'justify-between p-3'}`}>
          {!collapsed && (
            <div className="min-w-0">
              <h1 className="text-sm font-bold tracking-tight truncate">{t('app.name')}</h1>
              <p className="text-[11px] text-muted-foreground truncate">{user?.name}</p>
            </div>
          )}
          <Button
            variant="ghost"
            size="icon-xs"
            onClick={() => setCollapsed(!collapsed)}
            className="text-muted-foreground hover:text-foreground shrink-0"
          >
            {collapsed ? <PanelLeft className="size-3.5" /> : <PanelLeftClose className="size-3.5" />}
          </Button>
        </div>

        {/* Org switcher */}
        {!collapsed && (
          <div className="px-2 pt-2">
            <OrgSwitcher />
          </div>
        )}

        {/* Main nav */}
        <nav className={`flex-1 flex flex-col gap-0.5 ${collapsed ? 'px-1.5 py-2' : 'px-2 py-2'}`}>
          {mainNavItems.map(renderNavItem)}

          {/* Config section */}
          <div className="mt-3">
            {!collapsed && (
              <button
                onClick={() => setConfigOpen(!configOpen)}
                className="flex items-center gap-1.5 w-full px-2.5 py-1 text-[11px] font-medium text-muted-foreground uppercase tracking-wider hover:text-foreground transition-colors"
              >
                {configOpen ? <ChevronDown className="size-3" /> : <ChevronRight className="size-3" />}
                Настройки
              </button>
            )}
            {(collapsed || configOpen || isConfigRoute) && (
              <div className="flex flex-col gap-0.5 mt-0.5">
                {configNavItems.map(renderNavItem)}
              </div>
            )}
          </div>
        </nav>

        {/* Footer */}
        <div className={`border-t border-sidebar-border flex ${collapsed ? 'flex-col items-center gap-1 p-1.5' : 'items-center gap-1 p-2'}`}>
          <Button
            variant="ghost"
            size="icon-xs"
            onClick={toggleTheme}
            className="text-muted-foreground hover:text-foreground"
            title={resolvedTheme === 'dark' ? t('settings.themeLight') : t('settings.themeDark')}
          >
            {resolvedTheme === 'dark' ? <Sun className="size-3.5" /> : <Moon className="size-3.5" />}
          </Button>
          <Button
            variant="ghost"
            size="icon-xs"
            onClick={handleLogout}
            className="text-muted-foreground hover:text-foreground"
            title={t('auth.logout')}
          >
            <LogOut className="size-3.5" />
          </Button>
        </div>
      </aside>
      <main className="flex-1 bg-background overflow-auto">
        <div className="p-5 max-w-[1400px]">
          {children}
        </div>
      </main>
    </div>
  )
}
