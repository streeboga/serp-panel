import { createFileRoute } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { SummaryCard } from '@/components/SummaryCard'
import { useProject } from '@/hooks/useProjects'
import { Globe, KeyRound, CalendarDays } from 'lucide-react'

export const Route = createFileRoute('/projects/$projectId/')({
  component: ProjectOverview,
})

function ProjectOverview() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data: project } = useProject(projectId)

  const p = project?.data ?? project

  return (
    <div className="glow-bg rounded-lg p-0.5">
      <div className="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-3">
        <SummaryCard
          title={t('projects.domainsTab')}
          value={p?.domains_count ?? 0}
          icon={<Globe className="size-3.5" />}
          accent="cyan"
        />
        <SummaryCard
          title={t('projects.keywordsTab')}
          value={p?.keywords_count ?? 0}
          icon={<KeyRound className="size-3.5" />}
          accent="blue"
        />
        <SummaryCard
          title={t('projects.created')}
          value={
            p?.created_at
              ? new Date(p.created_at).toLocaleDateString('ru-RU')
              : '-'
          }
          icon={<CalendarDays className="size-3.5" />}
        />
      </div>
    </div>
  )
}
