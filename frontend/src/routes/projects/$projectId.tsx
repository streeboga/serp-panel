import { createFileRoute, redirect, Link, Outlet, useMatches } from '@tanstack/react-router'
import { Suspense, useCallback, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { PageSkeleton } from '@/components/PageSkeleton'
import { useProject, useTogglePublic } from '@/hooks/useProjects'
import { useAuth } from '@/contexts/AuthContext'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  LayoutDashboard,
  Globe,
  KeyRound,
  FolderTree,
  FileText,
  ChevronRight,
  Settings,
  Copy,
  Check,
} from 'lucide-react'

export const Route = createFileRoute('/projects/$projectId')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ProjectDetailPage,
})

const tabs = [
  { labelKey: 'projects.overview', to: '/projects/$projectId' as const, icon: <LayoutDashboard className="size-3.5" />, exact: true, segment: '' },
  { labelKey: 'projects.domainsTab', to: '/projects/$projectId/domains' as const, icon: <Globe className="size-3.5" />, segment: 'domains' },
  { labelKey: 'projects.keywordsTab', to: '/projects/$projectId/keywords' as const, icon: <KeyRound className="size-3.5" />, segment: 'keywords' },
  { labelKey: 'projects.categoriesTab', to: '/projects/$projectId/categories' as const, icon: <FolderTree className="size-3.5" />, segment: 'categories' },
  { labelKey: 'projects.pagesTab', to: '/projects/$projectId/pages' as const, icon: <FileText className="size-3.5" />, segment: 'pages' },
]

const MANAGER_ROLES = ['admin', 'manager', 'owner']

function ProjectDetailPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data: project, isLoading } = useProject(projectId)
  const { user, organizationId } = useAuth()
  const togglePublic = useTogglePublic()
  const matches = useMatches()
  const [settingsOpen, setSettingsOpen] = useState(false)
  const [copied, setCopied] = useState(false)

  const projectData = project?.data ?? project

  const currentOrg = useMemo(
    () => user?.organizations?.find((o) => o.id === organizationId),
    [user, organizationId],
  )
  const canManage = MANAGER_ROLES.includes(currentOrg?.role ?? '')

  const handleCopy = useCallback(() => {
    if (projectData?.public_url) {
      navigator.clipboard.writeText(projectData.public_url)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }, [projectData?.public_url])

  // Build breadcrumb from current route
  const breadcrumbItems = useMemo(() => {
    const items: Array<{ label: string; to?: string; params?: Record<string, string> }> = [
      { label: t('projects.title'), to: '/projects' },
      { label: projectData?.name ?? '...', to: '/projects/$projectId', params: { projectId } },
    ]

    // Find active tab from URL
    const currentPath = matches[matches.length - 1]?.pathname ?? ''
    const activeTab = tabs.find((tab) =>
      tab.segment && currentPath.includes(`/${tab.segment}`)
    )
    if (activeTab) {
      items.push({ label: t(activeTab.labelKey), to: activeTab.to as string, params: { projectId } })
    }

    return items
  }, [projectData, matches, projectId, t])

  return (
    <AppLayout>
      {isLoading ? (
        <PageSkeleton />
      ) : projectData ? (
        <div className="h-full flex flex-col">
          {/* Top bar: breadcrumbs left, tabs right — same line */}
          <div className="flex items-center justify-between mb-3">
            {/* Breadcrumbs */}
            <div className="flex items-center gap-1 text-[12px] text-muted-foreground min-w-0">
              {breadcrumbItems.map((item, i) => (
                <span key={i} className="flex items-center gap-1">
                  {i > 0 ? <ChevronRight className="size-3 shrink-0" /> : null}
                  {item.to && i < breadcrumbItems.length - 1 ? (
                    <Link
                      to={item.to}
                      params={item.params}
                      className="hover:text-foreground transition-colors whitespace-nowrap"
                    >
                      {item.label}
                    </Link>
                  ) : (
                    <span className="font-medium text-foreground whitespace-nowrap">{item.label}</span>
                  )}
                </span>
              ))}
              {canManage && (
                <Button
                  variant="ghost"
                  size="icon-xs"
                  className="ml-1"
                  onClick={() => setSettingsOpen(true)}
                >
                  <Settings className="size-3" />
                </Button>
              )}
            </div>

            {/* Tabs — right side */}
            <nav className="flex gap-0.5 bg-muted/50 border border-border/50 rounded-lg p-1 shrink-0">
              {tabs.map((tab) => (
                <Link
                  key={tab.to}
                  to={tab.to}
                  params={{ projectId }}
                  className="flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-medium rounded-md transition-all
                    hover:bg-muted text-muted-foreground
                    [&.active]:bg-accent-blue/10 [&.active]:text-accent-blue"
                  activeOptions={tab.exact ? { exact: true } : undefined}
                >
                  {tab.icon}
                  {t(tab.labelKey)}
                </Link>
              ))}
            </nav>
          </div>

          <Suspense fallback={<PageSkeleton />}>
            <Outlet />
          </Suspense>

          {/* Settings dialog */}
          <Dialog open={settingsOpen} onOpenChange={setSettingsOpen}>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Настройки проекта</DialogTitle>
              </DialogHeader>
              <div className="flex flex-col gap-3">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-[12px] font-medium">Публичный доступ</p>
                    <p className="text-[11px] text-muted-foreground">Позволяет просматривать данные без авторизации</p>
                  </div>
                  <Switch
                    checked={projectData?.is_public ?? false}
                    onCheckedChange={(checked) =>
                      togglePublic.mutate({ projectId: Number(projectId), isPublic: checked })
                    }
                  />
                </div>
                {projectData?.is_public && projectData?.public_url && (
                  <div className="flex flex-col gap-1.5">
                    <div className="flex items-center gap-2 p-2 bg-muted rounded-md">
                      <Input
                        value={projectData.public_url}
                        readOnly
                        className="h-7 text-[11px] font-mono"
                      />
                      <Button variant="outline" size="xs" onClick={handleCopy}>
                        {copied ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
                      </Button>
                    </div>
                    <p className="text-[10px] text-muted-foreground">
                      Любой с этой ссылкой сможет просматривать позиции проекта
                    </p>
                  </div>
                )}
              </div>
            </DialogContent>
          </Dialog>
        </div>
      ) : (
        <p className="text-muted-foreground">{t('projects.notFound')}</p>
      )}
    </AppLayout>
  )
}
