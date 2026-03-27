import { createFileRoute, redirect, Link, Outlet } from '@tanstack/react-router'
import { Suspense } from 'react'
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
  { labelKey: 'projects.overview', to: '/projects/$projectId' as const, icon: <LayoutDashboard className="size-3.5" />, exact: true },
  { labelKey: 'projects.domainsTab', to: '/projects/$projectId/domains' as const, icon: <Globe className="size-3.5" /> },
  { labelKey: 'projects.keywordsTab', to: '/projects/$projectId/keywords' as const, icon: <KeyRound className="size-3.5" /> },
  { labelKey: 'projects.categoriesTab', to: '/projects/$projectId/categories' as const, icon: <FolderTree className="size-3.5" /> },
  { labelKey: 'projects.pagesTab', to: '/projects/$projectId/pages' as const, icon: <FileText className="size-3.5" /> },
]

function ProjectDetailPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data: project, isLoading } = useProject(projectId)

  const projectData = project?.data ?? project

  return (
    <AppLayout>
      {isLoading ? (
        <PageSkeleton />
      ) : projectData ? (
        <div className="h-full flex flex-col">
          {/* Top bar */}
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
              <Link to="/projects" className="hover:text-foreground transition-colors">
                {t('projects.title')}
              </Link>
              <ChevronRight className="size-3" />
              <span className="font-medium text-foreground">{projectData.name}</span>
            </div>
          </div>

          {/* Tabs */}
          <nav className="flex gap-0.5 mb-4 glass-card rounded-lg p-1 w-fit">
            {tabs.map((tab) => (
              <Link
                key={tab.to}
                to={tab.to}
                params={{ projectId }}
                className="flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-medium rounded-md transition-all
                  hover:bg-muted text-muted-foreground
                  [&.active]:bg-accent-blue/10 [&.active]:text-accent-blue"
                activeOptions={tab.exact ? { exact: true } : undefined}
              >
                {tab.icon}
                {t(tab.labelKey)}
              </Link>
            ))}
          </nav>

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
