import { createFileRoute, redirect, Link, Outlet } from '@tanstack/react-router'
import { Suspense } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { PageSkeleton } from '@/components/PageSkeleton'
import { useProject } from '@/hooks/useProjects'

export const Route = createFileRoute('/projects/$projectId')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ProjectDetailPage,
})

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
          {/* Top bar: tabs on the right */}
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Link to="/projects" className="hover:underline">
                {t('projects.title')}
              </Link>
              <span>/</span>
              <span className="font-medium text-foreground">{projectData.name}</span>
            </div>
            <nav className="flex gap-1">
              <Link
                to="/projects/$projectId"
                params={{ projectId }}
                className="px-2.5 py-1 text-xs font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
                activeOptions={{ exact: true }}
              >
                {t('projects.overview')}
              </Link>
              <Link
                to="/projects/$projectId/domains"
                params={{ projectId }}
                className="px-2.5 py-1 text-xs font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.domainsTab')}
              </Link>
              <Link
                to="/projects/$projectId/keywords"
                params={{ projectId }}
                className="px-2.5 py-1 text-xs font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.keywordsTab')}
              </Link>
              <Link
                to="/projects/$projectId/competitors"
                params={{ projectId }}
                className="px-2.5 py-1 text-xs font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.competitorsTab')}
              </Link>
              <Link
                to="/projects/$projectId/categories"
                params={{ projectId }}
                className="px-2.5 py-1 text-xs font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.categoriesTab')}
              </Link>
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
