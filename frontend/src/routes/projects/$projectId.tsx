import { createFileRoute, redirect, Link, Outlet, useMatches } from '@tanstack/react-router'
import { Suspense, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { PageSkeleton } from '@/components/PageSkeleton'
import { useProject } from '@/hooks/useProjects'
import {
  LayoutDashboard,
  Globe,
  KeyRound,
  FolderTree,
  FileText,
  ChevronRight,
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

function ProjectDetailPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data: project, isLoading } = useProject(projectId)
  const matches = useMatches()

  const projectData = project?.data ?? project

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
        </div>
      ) : (
        <p className="text-muted-foreground">{t('projects.notFound')}</p>
      )}
    </AppLayout>
  )
}
