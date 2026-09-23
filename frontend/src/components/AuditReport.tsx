import { useDeferredValue, useEffect, useMemo, useState } from 'react'
import {
  EXPORT_DATASETS,
  RESULTS_PER_PAGE,
  useAuditResults,
  type CheckCatalogEntry,
  type Finding,
  type PageAuditResult,
  type Severity,
  type SiteAudit,
} from '@/hooks/useAudits'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
import { ChevronDown, ChevronLeft, ChevronRight, Download } from 'lucide-react'

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

export const STATUS_LABELS: Record<SiteAudit['status'], string> = {
  pending: 'В очереди',
  running: 'Идёт проверка',
  completed: 'Завершён',
  failed: 'Ошибка',
  cancelled: 'Отменён',
}

/** Оценка красится как в школе: ниже 60 — плохо, выше 90 — хорошо. */
export function scoreClass(score: number | null): string {
  if (score === null) return 'text-muted-foreground'
  if (score >= 90) return 'text-emerald-600 dark:text-emerald-400'
  if (score >= 60) return 'text-amber-600 dark:text-amber-400'
  return 'text-red-600 dark:text-red-400'
}

/**
 * Две колонки фиксированной сетки: значок важности и текст. Ширина первой
 * колонки одна на все строки, поэтому описание всегда начинается с одного
 * отступа — «Замечание» и «Предупреждение» разной длины его не сдвигают.
 */
const ROW_GRID = 'grid grid-cols-[8.5rem_minmax(0,1fr)] items-start gap-x-3 py-1 text-sm'

/** Проверка, которая на странице запускалась и ничего не нашла. */
function PassedRow({ code, title }: { code: string; title: string }) {
  return (
    <div className={ROW_GRID} title={code}>
      <Badge
        variant="outline"
        className="justify-self-start border-emerald-300 text-emerald-700 dark:border-emerald-800 dark:text-emerald-400"
      >
        ОК
      </Badge>
      <div className="min-w-0 text-muted-foreground">{title}</div>
    </div>
  )
}

export function FindingRow({ finding }: { finding: Finding }) {
  const scalar =
    finding.value !== null &&
    finding.value !== undefined &&
    typeof finding.value !== 'object'

  return (
    // Код проверки не показываем, но держим в подсказке: он нужен, когда надо
    // сузить прогон через check_codes.
    <div className={ROW_GRID} title={finding.check}>
      <Badge variant={SEVERITY_VARIANT[finding.severity]} className="justify-self-start">
        {SEVERITY_LABELS[finding.severity]}
      </Badge>
      <div className="min-w-0">
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
        {finding.fix && (
          <div className="mt-0.5 text-xs text-muted-foreground">
            <span className="font-medium">Как исправить:</span> {finding.fix}
          </div>
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

function ResultRow({
  result,
  ranChecks,
}: {
  result: PageAuditResult
  /** Проверки, которые прогон запускал на каждой странице; null — не показывать пройденные. */
  ranChecks: Array<{ code: string; title: string }> | null
}) {
  const [open, setOpen] = useState(false)
  const findings = result.findings ?? []
  // Страница без ответа проверок не проходила — «ОК» на ней врал бы.
  const passed =
    ranChecks && !result.error
      ? ranChecks.filter((c) => !findings.some((f) => f.check === c.code))
      : []

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
            {findings.length === 0 && !result.error && passed.length === 0 && (
              <p className="text-sm text-muted-foreground">Замечаний нет.</p>
            )}
            {findings.map((finding, i) => (
              <FindingRow key={`${finding.check}-${i}`} finding={finding} />
            ))}
            {passed.map((c) => (
              <PassedRow key={c.code} code={c.code} title={c.title} />
            ))}
          </TableCell>
        </TableRow>
      )}
    </>
  )
}

/**
 * Отчёт по одному прогону: сводка, находки уровня сайта, постраничная таблица.
 * Общий для панели и публичной ссылки; без catalog не показывает пройденные
 * проверки, без onExport — выгрузки.
 */
export function AuditReport({
  audit,
  resultsUrl,
  catalog,
  onExport,
}: {
  audit: SiteAudit
  resultsUrl: string
  catalog?: CheckCatalogEntry[]
  onExport?: (dataset: string, includePassed: boolean) => Promise<void>
}) {
  const [severity, setSeverity] = useState<Severity | ''>('')
  const [showPassed, setShowPassed] = useState(false)
  const [page, setPage] = useState(1)
  const [exporting, setExporting] = useState(false)
  const [search, setSearch] = useState('')

  // Что именно запускал этот прогон: каталог, суженный его groups и check_codes.
  const ranChecks = useMemo(() => {
    if (!showPassed || !catalog) return null
    return catalog
      .filter((entry) => !audit.groups?.length || audit.groups.includes(entry.category))
      .flatMap((entry) => entry.checks)
      .filter((c) => !audit.check_codes?.length || audit.check_codes.includes(c.code))
  }, [showPassed, audit, catalog])

  // Поиск — отложенный: запрос уходит, когда пользователь перестал печатать,
  // а не на каждую букву.
  const deferredSearch = useDeferredValue(search)

  // Смена фильтра или прогона возвращает на первую страницу.
  useEffect(() => {
    setPage(1)
  }, [severity, deferredSearch, resultsUrl])

  const { data: resultsData, isFetching: resultsLoading } = useAuditResults(
    resultsUrl,
    { severity, search: deferredSearch },
    page,
  )

  const results: PageAuditResult[] = useMemo(() => resultsData?.data ?? [], [resultsData])
  const meta = resultsData?.meta
  const lastPage = meta?.last_page ?? 1
  const isRunning = ['pending', 'running'].includes(audit.status)

  const handleExport = async (dataset: string, includePassed = false) => {
    if (!onExport) return
    setExporting(true)
    try {
      await onExport(dataset, includePassed)
    } finally {
      setExporting(false)
    }
  }

  return (
    <div className="space-y-6">
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
          {catalog && (
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <Switch checked={showPassed} onCheckedChange={setShowPassed} />
              Показывать пройденные
            </label>
          )}
          {meta && (
            <span className="text-sm text-muted-foreground">
              {meta.total} стр.
            </span>
          )}
          {onExport && (
            <DropdownMenu>
              <DropdownMenuTrigger
                render={<Button variant="outline" size="sm" disabled={exporting} />}
              >
                <Download className="size-4 mr-2" />
                {exporting ? 'Готовим…' : 'Выгрузить CSV'}
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                {EXPORT_DATASETS.map((d) => (
                  <DropdownMenuItem
                    key={d.label}
                    onClick={() => handleExport(d.key, 'includePassed' in d && d.includePassed)}
                  >
                    {d.label}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
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
                <ResultRow key={result.id} result={result} ranChecks={ranChecks} />
              ))}
            </TableBody>
          </Table>
        )}

        {meta && meta.total > RESULTS_PER_PAGE && (
          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>
              {(meta.current_page - 1) * meta.per_page + 1}–
              {Math.min(meta.current_page * meta.per_page, meta.total)} из {meta.total}
            </span>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1 || resultsLoading}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                <ChevronLeft className="size-4" />
              </Button>
              <span>
                {page} / {lastPage}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= lastPage || resultsLoading}
                onClick={() => setPage((p) => Math.min(lastPage, p + 1))}
              >
                <ChevronRight className="size-4" />
              </Button>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
