import { createLazyFileRoute } from '@tanstack/react-router'
import { useMemo, useState } from 'react'
import {
  useAudit,
  useAudits,
  downloadAuditExport,
  useCancelAudit,
  useCheckCatalog,
  useStartAudit,
  type CheckCatalogEntry,
  type SiteAudit,
} from '@/hooks/useAudits'
import { useDomains } from '@/hooks/useDomains'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { AuditReport, STATUS_LABELS } from '@/components/AuditReport'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { parseApiError } from '@/lib/api'
import { Play, SlidersHorizontal, X } from 'lucide-react'
import type { Domain } from '@/types/api'

export const Route = createLazyFileRoute('/projects/$projectId/audit')({
  component: AuditPage,
})

function AuditPage() {
  const { projectId } = Route.useParams()

  const [selectedId, setSelectedId] = useState<number | null>(null)
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

  const handleExport = async (dataset: string, includePassed: boolean) => {
    if (!currentId) return
    try {
      await downloadAuditExport(currentId, dataset, includePassed)
    } catch (e) {
      setError(parseApiError(e))
    }
  }

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
          <AuditReport
            key={audit.id}
            audit={audit}
            resultsUrl={`/audits/${audit.id}/results`}
            catalog={catalog}
            onExport={handleExport}
          />
      )}
    </div>
  )
}
