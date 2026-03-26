import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { SummaryCard } from '@/components/SummaryCard'
import { EmptyState } from '@/components/EmptyState'
import { CardGridSkeleton } from '@/components/PageSkeleton'
import { useDashboardSummary } from '@/hooks/useDashboard'
import { useProjects } from '@/hooks/useProjects'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { Project } from '@/types/api'

export const Route = createFileRoute('/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: DashboardPage,
})

function DashboardPage() {
  const { t } = useTranslation()
  const [projectId, setProjectId] = useState<number | undefined>(undefined)
  const { data: projects } = useProjects()
  const { data: summary, isLoading } = useDashboardSummary(projectId)

  const projectList: Project[] = Array.isArray(projects)
    ? projects
    : (projects?.data ?? [])

  const projectLabels = useMemo(
    () => Object.fromEntries(projectList.map((p) => [String(p.id), p.name])),
    [projectList],
  )

  const handleProjectChange = useCallback((v: string | null) => {
    setProjectId(v ? Number(v) : undefined)
  }, [])

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('dashboard.title')}</h1>
          {projectList.length > 0 && (
            <Select
              value={projectId ? String(projectId) : undefined}
              onValueChange={handleProjectChange}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('dashboard.allProjects')} labels={projectLabels} />
              </SelectTrigger>
              <SelectContent>
                {projectList.map((p) => (
                  <SelectItem key={p.id} value={String(p.id)} label={p.name}>
                    {p.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </div>

        {isLoading ? (
          <CardGridSkeleton count={7} />
        ) : summary ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <SummaryCard title="TOP-3" value={summary.top3 ?? 0} />
            <SummaryCard title="TOP-10" value={summary.top10 ?? 0} />
            <SummaryCard title="TOP-20" value={summary.top20 ?? 0} />
            <SummaryCard title="TOP-100" value={summary.top100 ?? 0} />
            <SummaryCard
              title={t('dashboard.totalKeywords')}
              value={summary.total_keywords ?? 0}
            />
            <SummaryCard
              title={t('dashboard.googleKeywords')}
              value={summary.google_keywords ?? 0}
            />
            <SummaryCard
              title={t('dashboard.yandexKeywords')}
              value={summary.yandex_keywords ?? 0}
            />
          </div>
        ) : (
          <EmptyState
            title={t('dashboard.welcome')}
            description={t('dashboard.welcomeDesc')}
          />
        )}
      </div>
    </AppLayout>
  )
}
