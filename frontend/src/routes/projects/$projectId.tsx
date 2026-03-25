import { createFileRoute, redirect, Link, Outlet } from '@tanstack/react-router'
import { AppLayout } from '@/components/AppLayout'
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
  const { projectId } = Route.useParams()
  const { data: project, isLoading } = useProject(projectId)

  const projectData = project?.data ?? project

  return (
    <AppLayout>
      <div className="space-y-6">
        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : projectData ? (
          <>
            <div>
              <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                <Link to="/projects" className="hover:underline">Projects</Link>
                <span>/</span>
                <span>{projectData.name}</span>
              </div>
              <h1 className="text-2xl font-bold">{projectData.name}</h1>
              {projectData.description && (
                <p className="text-muted-foreground mt-1">{projectData.description}</p>
              )}
            </div>

            <nav className="flex gap-4 border-b pb-2">
              <Link
                to="/projects/$projectId"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
                activeOptions={{ exact: true }}
              >
                Overview
              </Link>
              <Link
                to="/projects/$projectId/keywords"
                params={{ projectId }}
                className="px-3 py-1.5 text-sm font-medium rounded-md hover:bg-muted [&.active]:bg-muted"
              >
                Keywords
              </Link>
            </nav>

            <Outlet />
          </>
        ) : (
          <p className="text-muted-foreground">Project not found.</p>
        )}
      </div>
    </AppLayout>
  )
}
