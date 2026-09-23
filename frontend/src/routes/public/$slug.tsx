import { createFileRoute } from '@tanstack/react-router'
import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { ArrowDown, ArrowUp } from 'lucide-react'
import api, { parseApiError } from '@/lib/api'
import type { Project } from '@/types/api'
import type { KeywordRow, PositionMatrixResponse } from '@/hooks/usePositionMatrix'
import type { SiteAudit } from '@/hooks/useAudits'
import { AuditReport } from '@/components/AuditReport'
import { EngineBadge } from '@/components/EngineBadge'
import { PositionBadge } from '@/components/PositionBadge'
import { PageSkeleton } from '@/components/PageSkeleton'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export const Route = createFileRoute('/public/$slug')({
  component: PublicProjectPage,
})

const formatDate = (d: string) =>
  new Date(d).toLocaleDateString('ru-RU', {
    day: '2-digit',
    month: '2-digit',
  })

function fmtFreq(n: number | null): string {
  if (n == null) return '—'
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(n % 1_000_000 === 0 ? 0 : 1)}кк`
  if (n >= 1_000) return `${(n / 1_000).toFixed(n % 1_000 === 0 ? 0 : 1)}к`
  return String(n)
}

function PublicProjectPage() {
  const { slug } = Route.useParams()

  const project = useQuery<Project>({
    queryKey: ['public', slug],
    queryFn: () => api.get(`/public/${slug}`).then((r) => r.data.data),
    retry: false,
  })

  if (project.isLoading) return <PageSkeleton className="p-6" />
  if (project.isError) {
    return (
      <div className="p-6 text-muted-foreground">
        {(project.error as { response?: { status?: number } }).response?.status === 404
          ? 'Проект не найден или доступ по ссылке закрыт.'
          : parseApiError(project.error)}
      </div>
    )
  }

  return (
    <div className="p-6 space-y-4">
      <div>
        <h1 className="text-xl font-semibold">{project.data?.name}</h1>
        {project.data?.description && <p className="text-sm text-muted-foreground mt-1">{project.data.description}</p>}
      </div>
      <Tabs defaultValue="positions">
        <TabsList>
          <TabsTrigger value="positions">Позиции</TabsTrigger>
          <TabsTrigger value="audit">Аудит сайта</TabsTrigger>
        </TabsList>
        <TabsContent value="positions" className="pt-4">
          <PositionsTab slug={slug} />
        </TabsContent>
        <TabsContent value="audit" className="pt-4">
          <AuditTab slug={slug} />
        </TabsContent>
      </Tabs>
    </div>
  )
}

const GROUPS = {
  none: 'Без группировки',
  category: 'По категориям',
  cluster: 'По кластерам',
} as const
type GroupBy = keyof typeof GROUPS

/** 'found' — порядок по умолчанию; остальное — колонка, дата тоже колонка. */
type Sort = { key: string; dir: 1 | -1 }

function sortValue(r: KeywordRow, key: string): string | number | null {
  if (key === 'keyword') return r.keyword
  if (key === 'engine') return r.engine
  if (key === 'frequency') return r.frequency
  return r.positions[key]?.position ?? null
}

function PositionsTab({ slug }: { slug: string }) {
  const [sort, setSort] = useState<Sort>({ key: 'found', dir: 1 })
  const [groupBy, setGroupBy] = useState<GroupBy>('none')
  const matrix = useQuery<PositionMatrixResponse>({
    queryKey: ['public', slug, 'positions'],
    queryFn: () => api.get(`/public/${slug}/positions`, { params: { days: 30 } }).then((r) => r.data.data),
  })

  const dates = useMemo(() => matrix.data?.dates ?? [], [matrix.data])

  // Ключи, по которым сбора не было ни разу, публике не интересны.
  const monitored = useMemo(
    () =>
      (matrix.data?.data ?? []).filter((r) =>
        Object.values(r.positions).some((c) => c.monitored || c.position !== null),
      ),
    [matrix.data],
  )

  // Видимость — доля проверенных на дату ключей, попавших в ТОП-10, отдельно по поисковикам.
  const visibility = useMemo(() => {
    const engines = [...new Set(monitored.map((r) => r.engine))].sort().reverse()
    return engines.map((engine) => ({
      engine,
      byDate: Object.fromEntries(
        dates.map((d) => {
          const checked = monitored.filter(
            (r) => r.engine === engine && (r.positions[d]?.monitored || r.positions[d]?.position != null),
          )
          const top10 = checked.filter((r) => (r.positions[d]?.position ?? 101) <= 10).length
          return [d, checked.length ? Math.round((top10 / checked.length) * 100) : null]
        }),
      ),
    }))
  }, [monitored, dates])

  const rows = useMemo(() => {
    // По умолчанию сверху — ключи, которые хоть раз были в ТОП-100.
    if (sort.key === 'found') {
      const found = (r: KeywordRow) => Object.values(r.positions).some((c) => c.position !== null)
      return [...monitored].sort((a, b) => Number(found(b)) - Number(found(a)))
    }
    // Пустые значения всегда внизу, в какую сторону ни сортируй.
    return [...monitored].sort((a, b) => {
      const va = sortValue(a, sort.key)
      const vb = sortValue(b, sort.key)
      if (va === null) return vb === null ? 0 : 1
      if (vb === null) return -1
      const diff = typeof va === 'string' ? va.localeCompare(String(vb), 'ru') : va - (vb as number)
      return diff * sort.dir
    })
  }, [monitored, sort])

  // Группы идут в порядке первого появления, так сортировка решает и порядок групп.
  const groups = useMemo(() => {
    if (groupBy === 'none') return [{ label: '', rows }]
    const map = new Map<string, KeywordRow[]>()
    for (const r of rows) {
      const label = r[groupBy] ?? 'Без группы'
      map.set(label, [...(map.get(label) ?? []), r])
    }
    return [...map].map(([label, rows]) => ({ label, rows }))
  }, [rows, groupBy])

  // Частоту смотрят от большей, позиции и текст — от меньшей.
  const toggleSort = (key: string) =>
    setSort((s) => (s.key === key ? { key, dir: s.dir === 1 ? -1 : 1 } : { key, dir: key === 'frequency' ? -1 : 1 }))

  const head = (key: string, label: React.ReactNode, className = '') => (
    <th
      key={key}
      className={`px-2 py-2 font-medium cursor-pointer select-none hover:text-foreground whitespace-nowrap ${className}`}
      onClick={() => toggleSort(key)}
    >
      <span className="inline-flex items-center gap-0.5">
        {label}
        {sort.key === key && (sort.dir === 1 ? <ArrowUp className="size-3" /> : <ArrowDown className="size-3" />)}
      </span>
    </th>
  )

  if (matrix.isLoading) return <PageSkeleton />
  if (matrix.isError) return <p className="text-sm text-destructive">{parseApiError(matrix.error)}</p>
  if (rows.length === 0) return <p className="text-sm text-muted-foreground">Данных о позициях пока нет.</p>

  return (
    <div className="space-y-3">
      <Select items={GROUPS} value={groupBy} onValueChange={(v) => setGroupBy(v as GroupBy)}>
        <SelectTrigger className="w-52">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {(Object.keys(GROUPS) as GroupBy[]).map((g) => (
            <SelectItem key={g} value={g} label={GROUPS[g]}>
              {GROUPS[g]}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <div className="overflow-x-auto border rounded-md">
        <table className="text-sm w-full">
          <thead className="bg-muted/50 text-muted-foreground">
            <tr>
              {head('keyword', 'Запрос', 'text-left px-3 sticky left-0 bg-muted')}
              {head('engine', 'ПС')}
              {head('frequency', 'Частота', 'text-right')}
              {dates.map((d) => head(d, formatDate(d), 'text-xs tabular-nums'))}
            </tr>
            {visibility.map((v) => (
              <tr key={v.engine} className="border-t">
                <td className="px-3 py-1.5 sticky left-0 bg-muted text-xs" title="Доля проверенных запросов в ТОП-10">
                  Видимость
                </td>
                <td className="px-2 py-1.5">
                  <EngineBadge engine={v.engine} />
                </td>
                <td />
                {dates.map((d) => (
                  <td key={d} className="px-2 py-1.5 text-center text-xs font-medium tabular-nums text-foreground">
                    {v.byDate[d] == null ? '—' : `${v.byDate[d]}%`}
                  </td>
                ))}
              </tr>
            ))}
          </thead>
          {groups.map((g) => (
            <tbody key={g.label}>
              {g.label && (
                <tr className="border-t bg-muted/30">
                  <td colSpan={3 + dates.length} className="px-3 py-1.5 font-medium sticky left-0">
                    {g.label} <span className="text-muted-foreground font-normal">· {g.rows.length}</span>
                  </td>
                </tr>
              )}
              {g.rows.map((r) => (
                <tr key={`${r.keyword_id}-${r.engine}-${r.device}`} className="border-t">
                  <td className="px-3 py-1.5 sticky left-0 bg-background">{r.keyword}</td>
                  <td className="px-2 py-1.5">
                    <EngineBadge engine={r.engine} />
                  </td>
                  <td
                    className="px-2 py-1.5 text-right tabular-nums text-muted-foreground"
                    title={r.frequency_exact != null ? `Точная: ${r.frequency_exact}` : undefined}
                  >
                    {fmtFreq(r.frequency)}
                  </td>
                  {dates.map((d) => (
                    <td key={d} className="px-2 py-1.5 text-center">
                      {r.positions[d]?.position == null && r.positions[d]?.monitored ? (
                        <span className="text-muted-foreground text-xs">&gt;100</span>
                      ) : (
                        <PositionBadge
                          position={r.positions[d]?.position ?? null}
                          change={r.positions[d]?.delta ?? null}
                        />
                      )}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          ))}
        </table>
      </div>
    </div>
  )
}

function AuditTab({ slug }: { slug: string }) {
  const audit = useQuery<SiteAudit | null>({
    queryKey: ['public', slug, 'audit'],
    queryFn: () => api.get(`/public/${slug}/audit`).then((r) => r.data.data),
  })

  if (audit.isLoading) return <PageSkeleton />
  if (audit.isError) return <p className="text-sm text-destructive">{parseApiError(audit.error)}</p>
  if (!audit.data) return <p className="text-sm text-muted-foreground">Аудит сайта ещё не проводился.</p>

  return (
    <div className="space-y-2">
      <p className="text-sm text-muted-foreground">
        Проверка от {new Date(audit.data.created_at).toLocaleDateString('ru-RU')}
      </p>
      <AuditReport audit={audit.data} resultsUrl={`/public/${slug}/audit/results`} />
    </div>
  )
}
