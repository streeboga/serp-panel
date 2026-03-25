import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import { useDashboardSummary } from '@/hooks/useDashboard'
import { useProjects } from '@/hooks/useProjects'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export const Route = createFileRoute('/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: DashboardPage,
})

function DashboardPage() {
  const [projectId, setProjectId] = useState<number | undefined>(undefined)
  const { data: projects } = useProjects()
  const { data: summary, isLoading } = useDashboardSummary(projectId)

  const projectList = Array.isArray(projects) ? projects : projects?.data ?? []

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Dashboard</h1>
          {projectList.length > 0 && (
            <Select
              value={projectId ? String(projectId) : undefined}
              onValueChange={(v: string | null) => setProjectId(v ? Number(v) : undefined)}
            >
              <SelectTrigger>
                <SelectValue placeholder="All projects" />
              </SelectTrigger>
              <SelectContent>
                {projectList.map((p: { id: number; name: string }) => (
                  <SelectItem key={p.id} value={String(p.id)}>
                    {p.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </div>

        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : summary ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <SummaryCard title="TOP-3" value={summary.top3 ?? 0} />
            <SummaryCard title="TOP-10" value={summary.top10 ?? 0} />
            <SummaryCard title="TOP-20" value={summary.top20 ?? 0} />
            <SummaryCard title="TOP-100" value={summary.top100 ?? 0} />
            <SummaryCard title="Total Keywords" value={summary.total_keywords ?? 0} />
            <SummaryCard title="Google Keywords" value={summary.google_keywords ?? 0} />
            <SummaryCard title="Yandex Keywords" value={summary.yandex_keywords ?? 0} />
          </div>
        ) : (
          <p className="text-muted-foreground">
            Welcome to SEO Monitor. Create a project to get started.
          </p>
        )}
      </div>
    </AppLayout>
  )
}

function SummaryCard({ title, value }: { title: string; value: number }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-3xl font-bold">{value}</p>
      </CardContent>
    </Card>
  )
}
