import { createLazyFileRoute } from '@tanstack/react-router'
import { useMemo, useState } from 'react'
import {
  useAudit,
  useAudits,
  useAuditResults,
  useCancelAudit,
  useCheckCatalog,
  useStartAudit,
  type CheckCatalogEntry,
  type Finding,
  type PageAuditResult,
  type Severity,
  type SiteAudit,
} from '@/hooks/useAudits'
import { useDomains } from '@/hooks/useDomains'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { AuditFindingValue } from '@/components/AuditFindingValue'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { parseApiError } from '@/lib/api'
import { ChevronDown, ChevronRight, Play, SlidersHorizontal, X } from 'lucide-react'
import type { Domain } from '@/types/api'

export const Route = createLazyFileRoute('/projects/$projectId/audit')({
  component: AuditPage,
})

const SEVERITY_LABELS: Record<Severity, string> = {
  critical: 'Ошибка',
  warning: 'Предупреждение',
  notice: 'Замечание',
}

const SEVERITY_VARIANT: Record<Severity, 'destructive' | 'default' | 'secondary'> = {
  critical: 'destructive',
  warning: 'default',
  notice: 'secondary',
}

const STATUS_LABELS: Record<SiteAudit['status'], string> = {
  pending: 'В очереди',
  running: 'Идёт проверка',
  completed: 'Завершён',
  failed: 'Ошибка',
  cancelled: 'Отменён',
}

/** Оценка красится как в школе: ниже 60 — плохо, выше 90 — хорошо. */
function scoreClass(score: number | null): string {
  if (score === null) return 'text-muted-foreground'
  if (score >= 90) return 'text-emerald-600 dark:text-emerald-400'
  if (score >= 60) return 'text-amber-600 dark:text-amber-400'
  return 'text-red-600 dark:text-red-400'
}

function FindingRow({ finding }: { finding: Finding }) {
  const scalar =
    finding.value !== null &&
    finding.value !== undefined &&
    typeof finding.value !== 'object'

  return (
    // Код проверки не показываем, но держим в подсказке: он нужен, когда надо
    // сузить прогон через check_codes.
    <div className="flex items-start gap-2 py-1 text-sm" title={finding.check}>
      <Badge variant={SEVERITY_VARIANT[finding.severity]} className="shrink-0">
        {SEVERITY_LABELS[finding.severity]}
      </Badge>
      <div className="min-w-0 flex-1">
        <span>{finding.message}</span>
        {scalar && (
          <span className="text-muted-foreground">
            {' — '}
            <span className="font-mono">{String(finding.value)}</span>
          </span>
        )}
        {finding.expected !== null && finding.expected !== undefined && (
          <span className="text-muted-foreground"> (ожидается {String(finding.expected)})</span>
        )}
        {!scalar && (
          <div className="mt-1">
            <AuditFindingValue value={finding.value} />
          </div>
        )}
      </div>
    </div>
  )
}

function ResultRow({ result }: { result: PageAuditResult }) {
  const [open, setOpen] = useState(false)
  const findings = result.findings ?? []

  return (
    <>
      <TableRow className="cursor-pointer" onClick={() => setOpen((v) => !v)}>
        <TableCell className="w-8">
          {open ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
        </TableCell>
        <TableCell className="max-w-[420px] truncate font-mono text-xs" title={result.url}>
          {result.url}
        </TableCell>
        <TableCell>{result.http_status ?? '—'}</TableCell>
        <TableCell className={`font-semibold ${scoreClass(result.score)}`}>
          {result.score ?? '—'}
        </TableCell>
        <TableCell>
          <span className="text-red-600 dark:text-red-400">{result.issues_critical}</span>
          {' / '}
          <span className="text-amber-600 dark:text-amber-400">{result.issues_warning}</span>
          {' / '}
          <span className="text-muted-foreground">{result.issues_notice}</span>
        </TableCell>
        <TableCell>{result.response_time_ms ? `${result.response_time_ms} мс` : '—'}</TableCell>
      </TableRow>
      {open && (
        <TableRow>
          <TableCell colSpan={6} className="bg-muted/40">
            {result.error && <p className="text-sm text-red-600">{result.error}</p>}
            {findings.length === 0 && !result.error && (
              <p className="text-sm text-muted-foreground">Замечаний нет.</p>
            )}
            {findings.map((finding, i) => (
              <FindingRow key={`${finding.check}-${i}`} finding={finding} />
            ))}
          </TableCell>
        </TableRow>
      )}
    </>
  )
}

function AuditPage() {
  const { projectId } = Route.useParams()

  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [severity, setSeverity] = useState<Severity | ''>('')
  const [search, setSearch] = useState('')
  const [scope, setScope] = useState<'site' | 'url'>('site')
  const [enabled, setEnabled] = useState<Set<string> | null>(null)
  const [picking, setPicking] = useState(false)
  const [url, setUrl] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: auditsData, isLoading } = useAudits(projectId)
  const { data: catalogData } = useCheckCatalog()
  const { data: domainsData } = useDomains(projectId)
  const startAudit = useStartAudit(projectId)
  const cancelAudit = useCancelAudit()

  const audits: SiteAudit[] = useMemo(() => {
    const d = auditsData?.data ?? auditsData
    return Array.isArray(d) ? d : []
  }, [auditsData])

  const domains: Domain[] = useMemo(() => {
    const d = domainsData?.data ?? domainsData
    return Array.isArray(d) ? d : []
  }, [domainsData])

  const catalog: CheckCatalogEntry[] = useMemo(() => {
    const d = catalogData?.data ?? catalogData
    return Array.isArray(d) ? d : []
  }, [catalogData])

  const allCodes = useMemo(
    () => catalog.flatMap((c) => c.checks.map((check) => check.code)),
    [catalog],
  )

  // null означает «все» — так пустой выбор не превращается в «ни одной».
  const isOn = (code: string) => enabled === null || enabled.has(code)

  const toggle = (code: string) => {
    setEnabled((prev) => {
      const next = new Set(prev ?? allCodes)
      if (next.has(code)) next.delete(code)
      else next.add(code)
      return next.size === allCodes.length ? null : next
    })
  }

  const toggleCategory = (entry: CheckCatalogEntry) => {
    const codes = entry.checks.map((c) => c.code)
    const allOn = codes.every(isOn)
    setEnabled((prev) => {
      const next = new Set(prev ?? allCodes)
      codes.forEach((c) => (allOn ? next.delete(c) : next.add(c)))
      return next.size === allCodes.length ? null : next
    })
  }

  const ownDomain = domains.find((d) => d.is_own) ?? domains[0]

  const currentId = selectedId ?? audits[0]?.id ?? null
  const { data: auditData } = useAudit(currentId)
  const audit: SiteAudit | undefined = auditData?.data ?? auditData

  const { data: resultsData, isFetching: resultsLoading } = useAuditResults(currentId, {
    severity,
    search,
  })

  const results: PageAuditResult[] = useMemo(() => {
    const d = resultsData?.data ?? resultsData
    return Array.isArray(d) ? d : []
  }, [resultsData])

  const isRunning = audit ? ['pending', 'running'].includes(audit.status) : false

  const handleStart = () => {
    setError(null)
    const check_codes = enabled === null ? undefined : [...enabled]

    startAudit.mutate(
      scope === 'url'
        ? { scope: 'url', url, check_codes }
        : { scope: 'site', domain_id: ownDomain ? Number(ownDomain.id) : null, check_codes },
      {
        onSuccess: (response) => setSelectedId(response?.data?.id ?? null),
        onError: (e) => setError(parseApiError(e)),
      },
    )
  }

  if (isLoading) return <TableSkeleton />

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-end gap-3">
        <div className="space-y-1">
          <span className="text-xs text-muted-foreground">Что проверяем</span>
          <Select value={scope} onValueChange={(v) => setScope(v as 'site' | 'url')}>
            <SelectTrigger className="w-52">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="site" label="Весь сайт">
                Весь сайт
              </SelectItem>
              <SelectItem value="url" label="Один адрес">
                Один адрес
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        {scope === 'url' && (
          <Input
            className="w-96"
            placeholder="https://example.com/page/"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
          />
        )}

        <Button
          onClick={handleStart}
          disabled={startAudit.isPending || isRunning || (scope === 'url' && !url)}
        >
          <Play className="mr-1 size-3.5" />
          {isRunning ? 'Проверка идёт' : 'Запустить аудит'}
        </Button>

        {isRunning && audit && (
          <Button variant="outline" onClick={() => cancelAudit.mutate(audit.id)}>
            <X className="mr-1 size-3.5" />
            Отменить
          </Button>
        )}

        {catalog.length > 0 && (
          <Button variant="outline" onClick={() => setPicking((v) => !v)}>
            <SlidersHorizontal className="mr-1 size-3.5" />
            Проверки
            {enabled !== null && (
              <Badge variant="secondary" className="ml-1">
                {enabled.size} из {allCodes.length}
              </Badge>
            )}
          </Button>
        )}

        {audits.length > 0 && (
          <div className="space-y-1">
            <span className="text-xs text-muted-foreground">Прогон</span>
            <Select
              value={String(currentId ?? '')}
              onValueChange={(v) => setSelectedId(Number(v))}
            >
              <SelectTrigger className="w-64">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {audits.map((a) => {
                  const label = `${new Date(a.created_at).toLocaleString('ru-RU')} · ${STATUS_LABELS[a.status]}`
                  return (
                    <SelectItem key={a.id} value={String(a.id)} label={label}>
                      {label}
                    </SelectItem>
                  )
                })}
              </SelectContent>
            </Select>
          </div>
        )}
      </div>

      {picking && (
        <div className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-3">
          {catalog.map((entry) => (
            <div key={entry.category}>
              <button
                type="button"
                className="mb-1 text-left text-sm font-semibold hover:underline"
                onClick={() => toggleCategory(entry)}
              >
                {entry.title}
              </button>
              {entry.checks.map((check) => (
                <label key={check.code} className="flex items-start gap-2 py-0.5 text-sm">
                  <input
                    type="checkbox"
                    className="mt-1"
                    checked={isOn(check.code)}
                    onChange={() => toggle(check.code)}
                  />
                  <span>
                    {check.title}
                    <span className="ml-1 font-mono text-xs text-muted-foreground">{check.code}</span>
                  </span>
                </label>
              ))}
            </div>
          ))}
        </div>
      )}

      {error && <p className="text-sm text-red-600">{error}</p>}

      {!audit && (
        <EmptyState
          title="Аудит ещё не запускали"
          description="Проверим страницы сайта: технические данные, мета-теги, контент, ссылки и изображения. Для страниц с целевыми ключами посчитаем релевантность."
        />
      )}

      {audit && (
        <>
          <div className="grid gap-4 sm:grid-cols-4">
            <div className="rounded-lg border p-4">
              <div className="text-xs text-muted-foreground">Оценка</div>
              <div className={`text-3xl font-semibold ${scoreClass(audit.score)}`}>
                {audit.score ?? '—'}
              </div>
            </div>
            <div className="rounded-lg border p-4">
              <div className="text-xs text-muted-foreground">Страниц проверено</div>
              <div className="text-3xl font-semibold">
                {audit.pages_done}
                <span className="text-base text-muted-foreground"> / {audit.pages_total}</span>
              </div>
              {isRunning && (
                <div className="mt-2 h-1.5 w-full rounded bg-muted">
                  <div
                    className="h-1.5 rounded bg-primary transition-all"
                    style={{ width: `${audit.progress}%` }}
                  />
                </div>
              )}
            </div>
            <div className="rounded-lg border p-4">
              <div className="text-xs text-muted-foreground">Находки</div>
              <div className="text-3xl font-semibold">
                <span className="text-red-600 dark:text-red-400">{audit.issues_critical}</span>
                <span className="text-base text-muted-foreground"> / </span>
                <span className="text-amber-600 dark:text-amber-400">{audit.issues_warning}</span>
                <span className="text-base text-muted-foreground"> / {audit.issues_notice}</span>
              </div>
            </div>
            <div className="rounded-lg border p-4">
              <div className="text-xs text-muted-foreground">Статус</div>
              <div className="text-lg font-semibold">{STATUS_LABELS[audit.status]}</div>
              {audit.error && <p className="mt-1 text-xs text-red-600">{audit.error}</p>}
            </div>
          </div>

          {audit.findings?.length > 0 && (
            <div className="rounded-lg border p-4">
              <h3 className="mb-2 font-semibold">Уровень сайта</h3>
              <p className="mb-2 text-xs text-muted-foreground">
                robots.txt, карта сайта, SSL, оформление 404, канонические редиректы, фавикон
              </p>
              {audit.findings.map((finding, i) => (
                <FindingRow key={`${finding.check}-${i}`} finding={finding} />
              ))}
            </div>
          )}

          <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-3">
              <h3 className="font-semibold">По страницам</h3>
              <Select value={severity || 'all'} onValueChange={(v) => setSeverity(v === 'all' ? '' : (v as Severity))}>
                <SelectTrigger className="w-52">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all" label="Все страницы">
                    Все страницы
                  </SelectItem>
                  {(Object.keys(SEVERITY_LABELS) as Severity[]).map((s) => (
                    <SelectItem key={s} value={s} label={`Есть: ${SEVERITY_LABELS[s].toLowerCase()}`}>
                      {`Есть: ${SEVERITY_LABELS[s].toLowerCase()}`}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Input
                className="w-72"
                placeholder="Поиск по URL"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>

            {resultsLoading && results.length === 0 ? (
              <TableSkeleton />
            ) : results.length === 0 ? (
              <EmptyState
                title="Страниц нет"
                description={
                  isRunning
                    ? 'Проверка ещё идёт — результаты появятся по мере обхода.'
                    : 'Под текущий фильтр ничего не подошло.'
                }
              />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-8" />
                    <TableHead>URL</TableHead>
                    <TableHead>Код</TableHead>
                    <TableHead>Оценка</TableHead>
                    <TableHead title="Ошибки / предупреждения / замечания">Находки</TableHead>
                    <TableHead>Ответ</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {results.map((result) => (
                    <ResultRow key={result.id} result={result} />
                  ))}
                </TableBody>
              </Table>
            )}
          </div>
        </>
      )}
    </div>
  )
}
