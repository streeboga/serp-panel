import { createFileRoute } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { SummaryCard } from '@/components/SummaryCard'
import { useProject } from '@/hooks/useProjects'

export const Route = createFileRoute('/projects/$projectId/')({
  component: ProjectOverview,
})

function ProjectOverview() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data: project } = useProject(projectId)

  const p = project?.data ?? project

  return (
    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
      <SummaryCard title={t('projects.domainsTab')} value={p?.domains_count ?? 0} />
      <SummaryCard title={t('projects.keywordsTab')} value={p?.keywords_count ?? 0} />
      <SummaryCard
        title={t('projects.created')}
        value={
          p?.created_at
            ? new Date(p.created_at).toLocaleDateString()
            : '-'
        }
      />
    </div>
  )
}
