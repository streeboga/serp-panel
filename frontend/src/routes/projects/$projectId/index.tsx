import { createFileRoute } from '@tanstack/react-router'
import { useProject } from '@/hooks/useProjects'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export const Route = createFileRoute('/projects/$projectId/')({
  component: ProjectOverview,
})

function ProjectOverview() {
  const { projectId } = Route.useParams()
  const { data: project } = useProject(projectId)

  const p = project?.data ?? project

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-medium text-muted-foreground">Domains</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-3xl font-bold">{p?.domains_count ?? 0}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-medium text-muted-foreground">Keywords</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-3xl font-bold">{p?.keywords_count ?? 0}</p>
        </CardContent>
      </Card>
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-medium text-muted-foreground">Created</CardTitle>
        </CardHeader>
        <CardContent>
          <p className="text-lg">
            {p?.created_at ? new Date(p.created_at).toLocaleDateString() : '-'}
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
