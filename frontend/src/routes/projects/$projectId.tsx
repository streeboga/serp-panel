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
      <div className="space-y-6">
        {isLoading ? (
          <PageSkeleton />
        ) : projectData ? (
          <>
            <div>
              <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                <Link to="/projects" className="hover:underline">
                  {t('projects.title')}
                </Link>
                <span>/</span>
                <span>{projectData.name}</span>
              </div>
              <h1 className="text-2xl font-bold">{projectData.name}</h1>
              {projectData.description && (
                <p className="text-muted-foreground mt-1">
                  {projectData.description}
                </p>
              )}
            </div>

            <nav className="flex gap-4 border-b pb-2">
              <Link
                to="/projects/$projectId"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
                activeOptions={{ exact: true }}
              >
                {t('projects.overview')}
              </Link>
              <Link
                to="/projects/$projectId/domains"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.domainsTab')}
              </Link>
              <Link
                to="/projects/$projectId/keywords"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.keywordsTab')}
              </Link>
              <Link
                to="/projects/$projectId/competitors"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.competitorsTab')}
              </Link>
              <Link
                to="/projects/$projectId/categories"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                {t('projects.categoriesTab')}
              </Link>
            </nav>

            <Suspense fallback={<PageSkeleton />}>
              <Outlet />
            </Suspense>
          </>
        ) : (
          <p className="text-muted-foreground">{t('projects.notFound')}</p>
        )}
      </div>
    </AppLayout>
  )
}
