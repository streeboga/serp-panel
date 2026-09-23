import { createFileRoute } from '@tanstack/react-router'
import { useQuery } from '@tanstack/react-query'
import api, { parseApiError } from '@/lib/api'
import type { Project } from '@/types/api'
import type { PositionMatrixResponse } from '@/hooks/usePositionMatrix'
import { EngineBadge } from '@/components/EngineBadge'
import { PositionBadge } from '@/components/PositionBadge'
import { PageSkeleton } from '@/components/PageSkeleton'

export const Route = createFileRoute('/public/$slug')({
  component: PublicProjectPage,
})

const formatDate = (d: string) => new Date(d).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' })

function PublicProjectPage() {
  const { slug } = Route.useParams()

  const project = useQuery<Project>({
    queryKey: ['public', slug],
    queryFn: () => api.get(`/public/${slug}`).then((r) => r.data.data),
    retry: false,
  })
  const matrix = useQuery<PositionMatrixResponse>({
    queryKey: ['public', slug, 'positions'],
    queryFn: () => api.get(`/public/${slug}/positions`, { params: { days: 30 } }).then((r) => r.data.data),
    enabled: project.isSuccess,
  })

  if (project.isLoading) return <PageSkeleton className="p-6" />
  if (project.isError) {
    return (
      <div className="p-6 text-muted-foreground">
        {project.error && 'response' in project.error && (project.error as { response?: { status?: number } }).response?.status === 404
          ? 'Проект не найден или доступ по ссылке закрыт.'
          : parseApiError(project.error)}
      </div>
    )
  }

  // Ключи, по которым сбора не было ни разу, публике не интересны.
  // Сверху — ключи, которые хоть раз были в ТОП-100.
  const rows = (matrix.data?.data ?? [])
    .filter((r) => Object.values(r.positions).some((c) => c.monitored || c.position !== null))
    .map((r) => ({ r, found: Object.values(r.positions).some((c) => c.position !== null) }))
    .sort((a, b) => Number(b.found) - Number(a.found))
    .map(({ r }) => r)
  const dates = matrix.data?.dates ?? []

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-xl font-semibold">{project.data?.name}</h1>
        {project.data?.description && <p className="text-sm text-muted-foreground mt-1">{project.data.description}</p>}
      </div>

      {matrix.isLoading ? (
        <PageSkeleton />
      ) : matrix.isError ? (
        <p className="text-sm text-destructive">{parseApiError(matrix.error)}</p>
      ) : rows.length === 0 ? (
        <p className="text-sm text-muted-foreground">Данных о позициях пока нет.</p>
      ) : (
        <div className="overflow-x-auto border rounded-md">
          <table className="text-sm w-full">
            <thead className="bg-muted/50">
              <tr>
                <th className="text-left font-medium px-3 py-2 sticky left-0 bg-muted">Запрос</th>
                <th className="px-2 py-2" />
                {dates.map((d) => (
                  <th key={d} className="px-2 py-2 font-medium text-xs tabular-nums whitespace-nowrap">{formatDate(d)}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={`${r.keyword_id}-${r.engine}-${r.device}`} className="border-t">
                  <td className="px-3 py-1.5 sticky left-0 bg-background">{r.keyword}</td>
                  <td className="px-2 py-1.5"><EngineBadge engine={r.engine} /></td>
                  {dates.map((d) => (
                    <td key={d} className="px-2 py-1.5 text-center">
                      {r.positions[d]?.position == null && r.positions[d]?.monitored ? (
                        <span className="text-muted-foreground text-xs">&gt;100</span>
                      ) : (
                        <PositionBadge position={r.positions[d]?.position ?? null} change={r.positions[d]?.delta ?? null} />
                      )}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
