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
import {
  Trophy,
  Target,
  TrendingUp,
  Hash,
  Search,
} from 'lucide-react'
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
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight">{t('dashboard.title')}</h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Обзор позиций и ключевых слов</p>
          </div>
          {projectList.length > 0 && (
            <Select
              value={projectId ? String(projectId) : undefined}
              onValueChange={handleProjectChange}
            >
              <SelectTrigger className="w-44 h-8 text-xs">
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
          <div className="glow-bg rounded-lg p-0.5">
            <div className="relative z-10 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
              <SummaryCard
                title="TOP-3"
                value={summary.top3 ?? 0}
                icon={<Trophy className="size-3.5" />}
                accent="green"
              />
              <SummaryCard
                title="TOP-10"
                value={summary.top10 ?? 0}
                icon={<Target className="size-3.5" />}
                accent="cyan"
              />
              <SummaryCard
                title="TOP-20"
                value={summary.top20 ?? 0}
                icon={<TrendingUp className="size-3.5" />}
                accent="blue"
              />
              <SummaryCard
                title="TOP-100"
                value={summary.top100 ?? 0}
                icon={<Hash className="size-3.5" />}
              />
              <SummaryCard
                title={t('dashboard.totalKeywords')}
                value={summary.total_keywords ?? 0}
              />
              <SummaryCard
                title="Google"
                value={summary.google_keywords ?? 0}
                icon={<Search className="size-3.5" />}
              />
              <SummaryCard
                title="Яндекс"
                value={summary.yandex_keywords ?? 0}
                icon={<Search className="size-3.5" />}
              />
            </div>
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
