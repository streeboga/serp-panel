import { createLazyFileRoute, Link } from '@tanstack/react-router'
import { useQueryClient } from '@tanstack/react-query'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useKeyword, useKeywordVariants } from '@/hooks/useKeywords'
import {
  useSerpResults,
  useSerpDates,
  useSerpHistory,
  useRescrapeKeyword,
} from '@/hooks/useSerp'
import {
  useWordstat,
  useWordstatTrends,
  useWordstatSuggestions,
} from '@/hooks/useWordstat'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuCheckboxItem,
  DropdownMenuSeparator,
  DropdownMenuGroup,
} from '@/components/ui/dropdown-menu'
import { EngineBadge } from '@/components/EngineBadge'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { PageTypeBadge } from '@/components/PageTypeBadge'
import { SummaryCard } from '@/components/SummaryCard'
import { EmptyState } from '@/components/EmptyState'
import { DataExportButton } from '@/components/DataExportButton'
import { TableSkeleton } from '@/components/PageSkeleton'
import { PositionChart } from '@/components/charts/PositionChart'
import { TrendChart } from '@/components/charts/TrendChart'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { TargetMatchIndicator } from '@/components/TargetMatchIndicator'
import { useMatchOrCreatePage, usePages, useAttachPage, useDetachPageable, useKeywordPages } from '@/hooks/usePages'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { SerpResult, SerpHistoryItem, WordstatTrend, WordstatSuggestion, Page } from '@/types/api'

const TOP_N_LABELS = { '10': 'TOP-10', '20': 'TOP-20', '50': 'TOP-50', '100': 'TOP-100' } as const

export const Route = createLazyFileRoute(
  '/projects/$projectId/keywords/$keywordId',
)({
  component: KeywordDetailPage,
})

function KeywordDetailPage() {
  const { t } = useTranslation()
  const { projectId, keywordId } = Route.useParams()
  const { data: keywordData } = useKeyword(projectId, keywordId)
  const kw = keywordData?.data ?? keywordData

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Link
          to="/projects/$projectId/keywords"
          params={{ projectId }}
          className="hover:underline"
        >
          {t('keywords.title')}
        </Link>
        <span>/</span>
        <span>{kw?.keyword ?? t('common.loading')}</span>
      </div>

      {kw ? (
        <KeywordHeader kw={kw} projectId={projectId} keywordId={keywordId} />
      ) : null}

      <TargetPagesSection projectId={projectId} keywordId={keywordId} kw={kw} />

      <Tabs defaultValue="serp">
        <TabsList>
          <TabsTrigger value="serp">{t('keywordDetail.serp')}</TabsTrigger>
          <TabsTrigger value="history">{t('keywordDetail.history')}</TabsTrigger>
          <TabsTrigger value="wordstat">{t('keywordDetail.wordstat')}</TabsTrigger>
          <TabsTrigger value="suggestions">{t('keywordDetail.suggestions')}</TabsTrigger>
        </TabsList>

        <TabsContent value="serp">
          <SerpTab keywordId={keywordId} />
        </TabsContent>
        <TabsContent value="history">
          <HistoryTab keywordId={keywordId} />
        </TabsContent>
        <TabsContent value="wordstat">
          <WordstatTab keywordId={keywordId} />
        </TabsContent>
        <TabsContent value="suggestions">
          <SuggestionsTab keywordId={keywordId} />
        </TabsContent>
      </Tabs>
    </div>
  )
}

function TargetPagesSection({ projectId, keywordId, kw }: { projectId: string; keywordId: string; kw: any }) {
  const { data: pagesData } = usePages({ projectId })
  const { data: keywordPagesData } = useKeywordPages(keywordId)
  const qc = useQueryClient()
  const attachPage = useAttachPage()
  const detachPageable = useDetachPageable()
  const [attachOpen, setAttachOpen] = useState(false)
  const [selectedPageId, setSelectedPageId] = useState('')
  const [isTargetAttach, setIsTargetAttach] = useState(true)

  const allPages: Page[] = useMemo(() => {
    const d = pagesData?.data ?? pagesData ?? []
    return Array.isArray(d) ? d : []
  }, [pagesData])

  // Pages actually attached to this keyword (from dedicated endpoint)
  const keywordPages = useMemo(() => {
    const d = keywordPagesData?.data ?? keywordPagesData ?? []
    return Array.isArray(d) ? d : []
  }, [keywordPagesData])

  const targetPages = useMemo(
    () => keywordPages.filter((p: any) => p.is_target),
    [keywordPages],
  )
  const competitorPages = useMemo(
    () => keywordPages.filter((p: any) => !p.is_target),
    [keywordPages],
  )

  const attachedPageIds = useMemo(
    () => new Set(keywordPages.map((p: any) => String(p.id))),
    [keywordPages],
  )

  const availablePages = useMemo(
    () => allPages.filter((p) => !attachedPageIds.has(String(p.id))),
    [allPages, attachedPageIds],
  )

  const handleAttach = useCallback(async () => {
    if (!selectedPageId) return
    await attachPage.mutateAsync({
      pageId: selectedPageId,
      data: { pageable_type: 'keyword', pageable_id: Number(keywordId), is_target: isTargetAttach, priority: 0 },
    })
    qc.invalidateQueries({ queryKey: ['keyword-pages', keywordId] })
    setAttachOpen(false)
    setSelectedPageId('')
  }, [selectedPageId, keywordId, isTargetAttach, attachPage, qc])

  const handleDetach = useCallback(async (pageableId: number) => {
    if (confirm('Отвязать страницу?')) {
      await detachPageable.mutateAsync(String(pageableId))
      qc.invalidateQueries({ queryKey: ['keyword-pages', keywordId] })
    }
  }, [detachPageable, keywordId, qc])

  const renderPageRow = useCallback((page: any) => (
    <div key={page.id} className="flex items-center gap-2 text-sm">
      {page.is_target ? (
        <Badge variant="default" className="text-[10px] shrink-0">целевая</Badge>
      ) : (
        <Badge variant="secondary" className="text-[10px] shrink-0">конкурент</Badge>
      )}
      {page.page_type ? <PageTypeBadge type={page.page_type} /> : null}
      <a
        href={page.url}
        target="_blank"
        rel="noopener noreferrer"
        className="text-primary hover:underline truncate max-w-md"
      >
        {page.url}
      </a>
      {page.title ? (
        <span className="text-muted-foreground text-xs truncate">— {page.title}</span>
      ) : null}
      {Array.isArray(page.tags) && page.tags.length > 0 ? (
        <div className="flex gap-1 shrink-0">
          {page.tags.map((tag: any) => (
            <Badge key={tag.id ?? tag.name} variant="outline" className="text-[10px]">
              {tag.name ?? tag}
            </Badge>
          ))}
        </div>
      ) : null}
      {page.pageable_id ? (
        <button
          type="button"
          className="text-xs text-destructive hover:underline shrink-0 ml-auto"
          onClick={() => handleDetach(page.pageable_id)}
        >
          Отвязать
        </button>
      ) : null}
    </div>
  ), [handleDetach])

  return (
    <div className="rounded-lg border p-3 space-y-2">
      <div className="flex items-center justify-between">
        <span className="text-sm font-medium text-muted-foreground">Привязанные страницы</span>
        <Button variant="outline" size="sm" className="h-7 text-xs" onClick={() => setAttachOpen(true)}>
          + Привязать
        </Button>
      </div>

      {targetPages.length > 0 ? (
        <div className="space-y-1">
          <span className="text-xs font-medium text-muted-foreground">Целевые</span>
          {targetPages.map(renderPageRow)}
        </div>
      ) : null}

      {competitorPages.length > 0 ? (
        <div className="space-y-1">
          <span className="text-xs font-medium text-muted-foreground">Конкуренты</span>
          {competitorPages.map(renderPageRow)}
        </div>
      ) : null}

      {keywordPages.length === 0 ? (
        <p className="text-xs text-muted-foreground">Нет привязанных страниц. Привяжите страницу или разметьте из SERP через 🏷️</p>
      ) : null}

      <Dialog open={attachOpen} onOpenChange={setAttachOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Привязать страницу</DialogTitle>
          </DialogHeader>
          {availablePages.length > 0 ? (
            <div className="space-y-3">
              <Select value={selectedPageId} onValueChange={setSelectedPageId}>
                <SelectTrigger>
                  <SelectValue placeholder="Выберите страницу" />
                </SelectTrigger>
                <SelectContent>
                  {availablePages.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)} label={p.url}>
                      <div className="flex items-center gap-2">
                        <PageTypeBadge type={p.page_type} />
                        <span className="truncate">{p.url}</span>
                      </div>
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <label className="flex items-center gap-2 text-sm cursor-pointer">
                <input
                  type="checkbox"
                  checked={isTargetAttach}
                  onChange={() => setIsTargetAttach(!isTargetAttach)}
                  className="rounded"
                />
                Целевая страница
              </label>
              <DialogFooter>
                <Button onClick={handleAttach} disabled={!selectedPageId || attachPage.isPending}>
                  {attachPage.isPending ? 'Привязываю...' : 'Привязать'}
                </Button>
              </DialogFooter>
            </div>
          ) : (
            <div className="text-center py-4">
              <p className="text-sm text-muted-foreground mb-2">Нет доступных страниц.</p>
              <p className="text-xs text-muted-foreground">
                Создайте страницу на вкладке{' '}
                <Link to="/projects/$projectId/pages" params={{ projectId }} className="text-primary hover:underline">
                  Страницы
                </Link>{' '}
                или разметьте прямо из SERP через 🏷️
              </p>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

function KeywordHeader({ kw, projectId, keywordId }: { kw: any; projectId: string; keywordId: string }) {
  const { data: variantsData } = useKeywordVariants(projectId, kw.keyword)
  const variants = useMemo(() => {
    const d = variantsData?.data ?? variantsData
    return Array.isArray(d) ? d.filter((v: any) => v.keyword === kw.keyword) : []
  }, [variantsData, kw.keyword])

  const engineLabel = (e: string) => e === 'google' ? 'G' : 'Я'
  const deviceLabel = (d: string) => d === 'desktop' ? 'D' : 'M'

  const hasMultipleRegions = useMemo(() => {
    const regions = new Set(variants.map((v: any) => v.region?.name ?? v.region_id))
    return regions.size > 1
  }, [variants])

  return (
    <div className="flex items-center gap-3 flex-wrap">
      <h2 className="text-xl font-bold">{kw.keyword}</h2>
      {variants.length > 1 ? (
        <div className="flex gap-1 flex-wrap">
          {variants.map((v: any) => {
            const regionSuffix = hasMultipleRegions ? ` ${v.region?.name ?? ''}` : ''
            return (
              <Link
                key={v.id}
                to="/projects/$projectId/keywords/$keywordId"
                params={{ projectId, keywordId: String(v.id) }}
                className={`px-2 py-1 text-xs rounded font-medium transition-colors ${
                  String(v.id) === String(keywordId)
                    ? 'bg-primary text-primary-foreground'
                    : 'bg-muted hover:bg-muted/80 text-muted-foreground'
                }`}
              >
                {engineLabel(v.engine)} {deviceLabel(v.device)}{regionSuffix}
              </Link>
            )
          })}
        </div>
      ) : (
        <EngineBadge engine={kw.engine} />
      )}
      {kw.region ? (
        <Badge variant="outline" className="text-xs">{kw.region.name}</Badge>
      ) : null}
    </div>
  )
}

function QuickMarkupDialog({
  open,
  onClose,
  serpResult,
  projectId,
  keywordId,
}: {
  open: boolean
  onClose: () => void
  serpResult: SerpResult | null
  projectId: string
  keywordId: string
}) {
  const matchOrCreate = useMatchOrCreatePage(projectId)
  const qc = useQueryClient()
  const [pageType, setPageType] = useState<string>('')
  const [isTarget, setIsTarget] = useState(false)
  const [tags, setTags] = useState('')

  const handleSubmit = useCallback(async () => {
    if (!serpResult) return
    await matchOrCreate.mutateAsync({
      url: serpResult.url,
      title: serpResult.title,
      page_type: pageType || null,
      tags: tags ? tags.split(',').map(t => t.trim()).filter(Boolean) : [],
      attach_to: {
        type: 'keyword',
        id: Number(keywordId),
        is_target: isTarget,
      },
    })
    qc.invalidateQueries({ queryKey: ['keyword-pages', keywordId] })
    setTags('')
    onClose()
  }, [serpResult, pageType, isTarget, tags, keywordId, matchOrCreate, qc, onClose])

  const PAGE_TYPE_LABELS: Record<string, string> = useMemo(() => ({
    '': 'Не указан',
    commercial: 'Коммерческая',
    informational: 'Информационная',
    navigational: 'Навигационная',
    transactional: 'Транзакционная',
  }), [])

  return (
    <Dialog open={open} onOpenChange={(v) => { if (!v) onClose() }}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Разметка страницы</DialogTitle>
        </DialogHeader>
        {serpResult ? (
          <div className="space-y-3">
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">URL</label>
              <p className="text-sm break-all bg-muted/50 rounded px-2 py-1">{serpResult.url}</p>
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium text-muted-foreground">Title</label>
              <p className="text-sm bg-muted/50 rounded px-2 py-1 truncate">{serpResult.title}</p>
            </div>
            <div className="space-y-1">
              <label className="text-xs font-medium">Тип страницы</label>
              <Select value={pageType} onValueChange={(v) => setPageType(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Не указан" labels={PAGE_TYPE_LABELS} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="" label="Не указан">Не указан</SelectItem>
                  <SelectItem value="commercial" label="Коммерческая">Коммерческая</SelectItem>
                  <SelectItem value="informational" label="Информационная">Информационная</SelectItem>
                  <SelectItem value="navigational" label="Навигационная">Навигационная</SelectItem>
                  <SelectItem value="transactional" label="Транзакционная">Транзакционная</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <label className="flex items-center gap-2 text-sm cursor-pointer">
              <input
                type="checkbox"
                checked={isTarget}
                onChange={() => setIsTarget(!isTarget)}
                className="rounded"
              />
              Целевая страница
            </label>
            <div className="space-y-1">
              <Label className="text-xs font-medium">Теги (через запятую)</Label>
              <Input
                value={tags}
                onChange={(e) => setTags(e.target.value)}
                placeholder="конкурент, банк"
              />
            </div>
          </div>
        ) : null}
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Отмена</Button>
          <Button onClick={handleSubmit} disabled={matchOrCreate.isPending}>
            {matchOrCreate.isPending ? 'Сохранение...' : 'Сохранить'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

type CompetitorFilter = 'all' | 'marked' | 'unmarked' | string

interface MergedSerpRow {
  url: string
  domain: string
  title: string
  site_type?: SerpResult['site_type']
  is_own?: boolean
  position1: number | null
  position2: number | null
  delta: number | null
}

function SerpTab({ keywordId }: { keywordId: string }) {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const [date, setDate] = useState<string | undefined>(undefined)
  const [topN, setTopN] = useState(20)
  const [markupResult, setMarkupResult] = useState<SerpResult | null>(null)
  const handleCloseMarkup = useCallback(() => setMarkupResult(null), [])
  const rescrape = useRescrapeKeyword()
  const handleRescrape = useCallback(() => {
    rescrape.mutate(keywordId)
  }, [keywordId, rescrape])

  // Compare mode state
  const [compareMode, setCompareMode] = useState(false)
  const [compareDate, setCompareDate] = useState<string | undefined>(undefined)

  // Competitor filter state
  const [competitorFilters, setCompetitorFilters] = useState<Set<CompetitorFilter>>(new Set(['all']))
  // Page type filter state
  const [pageTypeFilter, setPageTypeFilter] = useState<string>('all')

  const { data: dates } = useSerpDates(keywordId)
  const { data: serpData, isLoading } = useSerpResults(keywordId, {
    date,
    top_n: topN,
  })

  // Second SERP fetch for comparison
  const { data: compareSerpData, isLoading: compareLoading } = useSerpResults(keywordId, {
    date: compareDate,
    top_n: topN,
  }, { enabled: compareMode && !!compareDate })

  // Pages for competitor filter
  const { data: pagesData } = usePages({ projectId })

  const pagesList: Page[] = useMemo(() => {
    const d = pagesData?.data ?? pagesData
    return Array.isArray(d) ? d : []
  }, [pagesData])

  // Set of known domains from pages (for "marked"/"unmarked" filter)
  const knownDomains: Set<string> = useMemo(() => {
    const set = new Set<string>()
    for (const page of pagesList) {
      try {
        const hostname = new URL(page.url).hostname.replace(/^www\./, '')
        set.add(hostname)
      } catch {
        // skip invalid URLs
      }
    }
    return set
  }, [pagesList])

  // Map pages by domain and path for SERP enrichment (page_type badge)
  const pagesByDomain: Map<string, Page> = useMemo(() => {
    const map = new Map<string, Page>()
    for (const page of pagesList) {
      try {
        const hostname = new URL(page.url).hostname.replace(/^www\./, '')
        if (!map.has(hostname)) map.set(hostname, page)
      } catch {
        // skip invalid URLs
      }
      if (page.path) map.set(page.path, page)
    }
    return map
  }, [pagesList])

  // Unique competitor domains from pages for individual filter options
  const competitorDomainList: string[] = useMemo(() => {
    return Array.from(knownDomains).sort()
  }, [knownDomains])

  const dateList: string[] = useMemo(
    () => {
      const d = dates?.data ?? dates ?? []
      if (Array.isArray(d) && d.length > 0) return d
      const snapshots = serpData?.data ?? serpData ?? []
      if (Array.isArray(snapshots)) {
        return snapshots.map((s: any) => s.collected_at?.split('T')[0] ?? s.date).filter(Boolean)
      }
      return []
    },
    [dates, serpData],
  )

  const extractResults = useCallback((data: any): SerpResult[] => {
    const snapshots = data?.data ?? data ?? []
    if (!Array.isArray(snapshots) || snapshots.length === 0) return []
    const snap = snapshots[0]
    const res = snap?.results ?? snap?.data?.results ?? []
    return Array.isArray(res) ? res : []
  }, [])

  const results: SerpResult[] = useMemo(() => extractResults(serpData), [serpData, extractResults])
  const compareResults: SerpResult[] = useMemo(() => extractResults(compareSerpData), [compareSerpData, extractResults])

  // Apply competitor filter to results
  const applyCompetitorFilter = useCallback((items: SerpResult[]): SerpResult[] => {
    if (competitorFilters.has('all')) return items
    return items.filter((item) => {
      const domain = item.domain?.replace(/^www\./, '') ?? ''
      if (competitorFilters.has('marked') && knownDomains.has(domain)) return true
      if (competitorFilters.has('unmarked') && !knownDomains.has(domain)) return true
      // Check individual domain filters
      for (const f of competitorFilters) {
        if (f !== 'all' && f !== 'marked' && f !== 'unmarked' && domain === f) return true
      }
      return false
    })
  }, [competitorFilters, knownDomains])

  const applyPageTypeFilter = useCallback((items: SerpResult[]): SerpResult[] => {
    if (pageTypeFilter === 'all') return items
    return items.filter((item) => {
      const domain = item.domain?.replace(/^www\./, '') ?? ''
      const matched = pagesByDomain.get(domain)
      const type = matched?.page_type ?? null
      if (pageTypeFilter === 'none') return type === null
      return type === pageTypeFilter
    })
  }, [pageTypeFilter, pagesByDomain])

  const filteredResults: SerpResult[] = useMemo(
    () => applyPageTypeFilter(applyCompetitorFilter(results)),
    [results, applyCompetitorFilter, applyPageTypeFilter],
  )

  // Build merged rows for comparison mode
  const mergedRows: MergedSerpRow[] = useMemo(() => {
    if (!compareMode || !compareDate) return []

    const posMap1 = new Map<string, SerpResult>()
    for (const r of filteredResults) {
      posMap1.set(r.url, r)
    }
    const posMap2 = new Map<string, SerpResult>()
    for (const r of compareResults) {
      posMap2.set(r.url, r)
    }

    const allUrls = new Set<string>([...posMap1.keys(), ...posMap2.keys()])
    const rows: MergedSerpRow[] = []

    for (const url of allUrls) {
      const r1 = posMap1.get(url)
      const r2 = posMap2.get(url)
      const p1 = r1?.position ?? null
      const p2 = r2?.position ?? null
      const delta = (p1 !== null && p2 !== null) ? p2 - p1 : null

      rows.push({
        url,
        domain: r1?.domain ?? r2?.domain ?? '',
        title: r1?.title ?? r2?.title ?? '',
        site_type: r1?.site_type ?? r2?.site_type,
        is_own: r1?.is_own ?? r2?.is_own,
        position1: p1,
        position2: p2,
        delta,
      })
    }

    // Sort by first date position, then second
    rows.sort((a, b) => {
      const pa = a.position1 ?? 999
      const pb = b.position1 ?? 999
      return pa !== pb ? pa - pb : (a.position2 ?? 999) - (b.position2 ?? 999)
    })

    // Apply competitor filter
    if (!competitorFilters.has('all')) {
      return rows.filter((row) => {
        const domain = row.domain?.replace(/^www\./, '') ?? ''
        if (competitorFilters.has('marked') && knownDomains.has(domain)) return true
        if (competitorFilters.has('unmarked') && !knownDomains.has(domain)) return true
        for (const f of competitorFilters) {
          if (f !== 'all' && f !== 'marked' && f !== 'unmarked' && domain === f) return true
        }
        return false
      })
    }

    return rows
  }, [compareMode, compareDate, filteredResults, compareResults, competitorFilters, knownDomains])

  const dateLabels = useMemo(
    () => ({ '__latest__': t('keywordDetail.latestDate'), ...Object.fromEntries(dateList.map((d) => [d, d])) }),
    [dateList, t],
  )

  const handleDateChange = useCallback((v: string | null) => {
    setDate(!v || v === '__latest__' ? undefined : v)
  }, [])

  const handleCompareDateChange = useCallback((v: string | null) => {
    setCompareDate(!v || v === '__latest__' ? undefined : v)
  }, [])

  const handleTopNChange = useCallback((v: string | null) => {
    setTopN(v ? Number(v) : 20)
  }, [])

  const handleToggleCompare = useCallback(() => {
    setCompareMode((prev) => {
      if (!prev && dateList.length > 1) {
        // Auto-select second date (previous date)
        setCompareDate(dateList[1])
      }
      if (prev) {
        setCompareDate(undefined)
      }
      return !prev
    })
  }, [dateList])

  const handleCompetitorToggle = useCallback((value: CompetitorFilter) => {
    setCompetitorFilters((prev) => {
      const next = new Set(prev)
      if (value === 'all') {
        return new Set(['all'])
      }
      next.delete('all')
      if (next.has(value)) {
        next.delete(value)
      } else {
        next.add(value)
      }
      return next.size === 0 ? new Set(['all']) : next
    })
  }, [])

  const competitorFilterLabel = useMemo(() => {
    if (competitorFilters.has('all')) return 'Все сайты'
    const parts: string[] = []
    if (competitorFilters.has('marked')) parts.push('Размеченные')
    if (competitorFilters.has('unmarked')) parts.push('Неразмеченные')
    const domainCount = Array.from(competitorFilters).filter(
      (f) => f !== 'marked' && f !== 'unmarked' && f !== 'all',
    ).length
    if (domainCount > 0) parts.push(`${domainCount} домен${domainCount > 1 ? 'ов' : ''}`)
    return parts.join(', ') || 'Все сайты'
  }, [competitorFilters])

  const getExportData = useCallback(
    () => {
      if (compareMode && mergedRows.length > 0) {
        return mergedRows.map((row) => ({
          domain: row.domain,
          url: row.url,
          title: row.title,
          position_date1: row.position1 ?? '-',
          position_date2: row.position2 ?? '-',
          delta: row.delta !== null ? (row.delta > 0 ? `+${row.delta}` : String(row.delta)) : '-',
        }))
      }
      return filteredResults.map((item) => ({
        position: item.position,
        domain: item.domain,
        type: item.site_type?.name ?? '',
        title: item.title,
        url: item.url,
        is_own: item.is_own ? 'yes' : 'no',
      }))
    },
    [compareMode, mergedRows, filteredResults],
  )

  const isCompareReady = compareMode && !!compareDate
  const showLoading = isCompareReady ? (isLoading || compareLoading) : isLoading
  const showData = isCompareReady ? mergedRows : filteredResults

  return (
    <div className="space-y-4 mt-4">
      {/* Filters row */}
      <div className="flex gap-2 items-center flex-wrap">
        <Select
          value={date ?? '__latest__'}
          onValueChange={handleDateChange}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue placeholder={t('keywordDetail.latestDate')} labels={dateLabels} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__latest__" label={t('keywordDetail.latestDate')}>{t('keywordDetail.latestDate')}</SelectItem>
            {dateList.map((d) => (
              <SelectItem key={d} value={d} label={d}>
                {d}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Button
          variant={compareMode ? 'default' : 'outline'}
          size="sm"
          className="h-8 text-xs"
          onClick={handleToggleCompare}
        >
          Сравнить
        </Button>

        {compareMode ? (
          <Select
            value={compareDate ?? '__latest__'}
            onValueChange={handleCompareDateChange}
          >
            <SelectTrigger className="h-8 text-xs">
              <SelectValue placeholder="Дата сравнения" labels={dateLabels} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__latest__" label={t('keywordDetail.latestDate')}>{t('keywordDetail.latestDate')}</SelectItem>
              {dateList.map((d) => (
                <SelectItem key={d} value={d} label={d}>
                  {d}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : null}

        <Select
          value={String(topN)}
          onValueChange={handleTopNChange}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue labels={TOP_N_LABELS} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="10" label="TOP-10">TOP-10</SelectItem>
            <SelectItem value="20" label="TOP-20">TOP-20</SelectItem>
            <SelectItem value="50" label="TOP-50">TOP-50</SelectItem>
            <SelectItem value="100" label="TOP-100">TOP-100</SelectItem>
          </SelectContent>
        </Select>

        {/* Competitor filter */}
        <DropdownMenu>
          <DropdownMenuTrigger
            className="inline-flex items-center justify-start gap-2 rounded-md border border-input bg-background px-3 h-8 text-xs font-medium ring-offset-background hover:bg-accent hover:text-accent-foreground min-w-[140px] text-left"
          >
            <span className="truncate">{competitorFilterLabel}</span>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" className="w-56">
            <DropdownMenuGroup>
              <DropdownMenuCheckboxItem
                checked={competitorFilters.has('all')}
                onCheckedChange={() => handleCompetitorToggle('all')}
              >
                Все сайты
              </DropdownMenuCheckboxItem>
              <DropdownMenuCheckboxItem
                checked={competitorFilters.has('marked')}
                onCheckedChange={() => handleCompetitorToggle('marked')}
              >
                Только размеченные
              </DropdownMenuCheckboxItem>
              <DropdownMenuCheckboxItem
                checked={competitorFilters.has('unmarked')}
                onCheckedChange={() => handleCompetitorToggle('unmarked')}
              >
                Только неразмеченные
              </DropdownMenuCheckboxItem>
            </DropdownMenuGroup>
            {competitorDomainList.length > 0 ? (
              <DropdownMenuGroup>
                <DropdownMenuSeparator />
                {competitorDomainList.map((domain) => (
                  <DropdownMenuCheckboxItem
                    key={domain}
                    checked={competitorFilters.has(domain)}
                    onCheckedChange={() => handleCompetitorToggle(domain)}
                  >
                    {domain}
                  </DropdownMenuCheckboxItem>
                ))}
              </DropdownMenuGroup>
            ) : null}
          </DropdownMenuContent>
        </DropdownMenu>

        <Select value={pageTypeFilter} onValueChange={(v) => setPageTypeFilter(v ?? 'all')}>
          <SelectTrigger className="w-[140px] h-8 text-xs">
            <SelectValue placeholder="Тип страницы" labels={{
              all: 'Все типы',
              commercial: 'Коммерческие',
              informational: 'Информационные',
              navigational: 'Навигационные',
              transactional: 'Транзакционные',
              none: 'Без типа',
            }} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all" label="Все типы">Все типы</SelectItem>
            <SelectItem value="commercial" label="Коммерческие">Коммерческие</SelectItem>
            <SelectItem value="informational" label="Информационные">Информационные</SelectItem>
            <SelectItem value="navigational" label="Навигационные">Навигационные</SelectItem>
            <SelectItem value="transactional" label="Транзакционные">Транзакционные</SelectItem>
            <SelectItem value="none" label="Без типа">Без типа</SelectItem>
          </SelectContent>
        </Select>

        <DataExportButton
          getData={getExportData}
          filename={`serp-keyword-${keywordId}`}
        />
        <Button
          variant="outline"
          size="sm"
          className="h-8 text-xs"
          onClick={handleRescrape}
          disabled={rescrape.isPending}
        >
          {rescrape.isPending ? '⏳' : '🔄'} Обновить SERP
        </Button>
      </div>

      {/* Table */}
      {showLoading ? (
        <TableSkeleton />
      ) : showData.length === 0 ? (
        <EmptyState title={t('keywordDetail.noSerpData')} />
      ) : isCompareReady ? (
        /* Comparison table */
        <div className="glass-card rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-20">Позиция 1</TableHead>
              <TableHead className="w-20">Позиция 2</TableHead>
              <TableHead className="w-20">Дельта</TableHead>
              <TableHead>{t('keywordDetail.domain')}</TableHead>
              <TableHead>{t('keywordDetail.type')}</TableHead>
              <TableHead>{t('keywordDetail.title')}</TableHead>
              <TableHead>{t('keywordDetail.url')}</TableHead>
              <TableHead className="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {mergedRows.map((row) => {
              const domain = row.domain?.replace(/^www\./, '') ?? ''
              const matchedPage = pagesByDomain.get(domain)
              return (
              <TableRow
                key={row.url}
                className={row.is_own ? 'bg-green-50 dark:bg-green-950/20' : ''}
              >
                <TableCell className="font-medium tabular-nums">
                  {row.position1 ?? '—'}
                </TableCell>
                <TableCell className="font-medium tabular-nums">
                  {row.position2 ?? '—'}
                </TableCell>
                <TableCell className="font-medium tabular-nums">
                  <PositionDelta delta={row.delta} />
                </TableCell>
                <TableCell>
                  {row.is_own ? '🏠 ' : matchedPage ? '🏢 ' : ''}
                  {row.domain}
                </TableCell>
                <TableCell>
                  <SiteTypeBadge type={row.site_type ?? null} />
                  {matchedPage ? <PageTypeBadge type={matchedPage.page_type} /> : null}
                </TableCell>
                <TableCell className="max-w-xs truncate">
                  {row.title}
                </TableCell>
                <TableCell className="max-w-xs truncate">
                  <a
                    href={row.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary hover:underline"
                  >
                    {row.url}
                  </a>
                </TableCell>
                <TableCell>
                  <button
                    type="button"
                    className="text-xs hover:bg-muted rounded p-1 transition-colors"
                    title="Разметить страницу"
                    onClick={() => setMarkupResult({
                      position: row.position1 ?? row.position2 ?? 0,
                      domain: row.domain,
                      title: row.title,
                      url: row.url,
                      site_type: row.site_type,
                      is_own: row.is_own,
                    })}
                  >
                    🏷️
                  </button>
                </TableCell>
              </TableRow>
              )
            })}
          </TableBody>
        </Table>
        </div>
      ) : (
        /* Normal single-date table */
        <div className="glass-card rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-16">#</TableHead>
              <TableHead>{t('keywordDetail.domain')}</TableHead>
              <TableHead>{t('keywordDetail.type')}</TableHead>
              <TableHead>{t('keywordDetail.title')}</TableHead>
              <TableHead>{t('keywordDetail.url')}</TableHead>
              <TableHead className="w-10"></TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filteredResults.map((item) => {
              const domain = item.domain?.replace(/^www\./, '') ?? ''
              const matchedPage = pagesByDomain.get(domain)
              return (
              <TableRow
                key={`${item.position}-${item.domain}`}
                className={item.is_own ? 'bg-green-50 dark:bg-green-950/20' : ''}
              >
                <TableCell className="font-medium tabular-nums">
                  {item.position}
                </TableCell>
                <TableCell>
                  {item.is_own ? '🏠 ' : matchedPage ? '🏢 ' : ''}
                  {item.domain}
                </TableCell>
                <TableCell>
                  <SiteTypeBadge type={item.site_type ?? null} />
                  {matchedPage ? <PageTypeBadge type={matchedPage.page_type} /> : null}
                </TableCell>
                <TableCell className="max-w-xs truncate">
                  {item.title}
                </TableCell>
                <TableCell className="max-w-xs truncate">
                  <a
                    href={item.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary hover:underline"
                  >
                    {item.url}
                  </a>
                </TableCell>
                <TableCell>
                  <button
                    type="button"
                    className="text-xs hover:bg-muted rounded p-1 transition-colors"
                    title="Разметить страницу"
                    onClick={() => setMarkupResult(item)}
                  >
                    🏷️
                  </button>
                </TableCell>
              </TableRow>
              )
            })}
          </TableBody>
        </Table>
        </div>
      )}

      <QuickMarkupDialog
        open={markupResult !== null}
        onClose={handleCloseMarkup}
        serpResult={markupResult}
        projectId={projectId}
        keywordId={keywordId}
      />
    </div>
  )
}

function PositionDelta({ delta }: { delta: number | null }) {
  if (delta === null) return <span className="text-muted-foreground">—</span>

  // Positive delta means position number increased (dropped in ranking)
  // Negative delta means position number decreased (improved in ranking)
  return delta < 0 ? (
    <span className="text-green-600 font-medium">{delta}</span>
  ) : delta > 0 ? (
    <span className="text-red-600 font-medium">+{delta}</span>
  ) : (
    <span className="text-muted-foreground">=</span>
  )
}

function HistoryTab({ keywordId }: { keywordId: string }) {
  const { t } = useTranslation()
  const { data, isLoading } = useSerpHistory(keywordId)
  const history: SerpHistoryItem[] = useMemo(
    () => {
      const raw = data?.data ?? data ?? []
      if (!Array.isArray(raw)) return []
      return raw.map((item: any) => ({
        date: item.date ?? item.collected_at ?? '',
        position: item.position ?? null,
        url: item.url,
      }))
    },
    [data],
  )

  return (
    <div className="mt-4 space-y-6">
      {!isLoading && history.length > 0 && (
        <div className="rounded-lg border p-4">
          <PositionChart data={history} />
        </div>
      )}

      {isLoading ? (
        <TableSkeleton rows={8} />
      ) : history.length === 0 ? (
        <EmptyState title={t('keywordDetail.noHistory')} />
      ) : (
        <div className="glass-card rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('keywordDetail.date')}</TableHead>
              <TableHead>{t('keywordDetail.position')}</TableHead>
              <TableHead>{t('keywordDetail.url')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {history.map((item) => (
              <TableRow key={item.date}>
                <TableCell>{item.date}</TableCell>
                <TableCell className="tabular-nums">
                  {item.position ?? '-'}
                </TableCell>
                <TableCell className="max-w-sm truncate">
                  {item.url ?? '-'}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        </div>
      )}
    </div>
  )
}

function WordstatTab({ keywordId }: { keywordId: string }) {
  const { t } = useTranslation()
  const { data: wordstat, isLoading: wLoading } = useWordstat(keywordId)
  const { data: trends, isLoading: tLoading } = useWordstatTrends(keywordId)

  const wsRaw = wordstat?.data ?? wordstat
  const ws = Array.isArray(wsRaw) ? wsRaw[0] ?? null : wsRaw
  const trendList: WordstatTrend[] = useMemo(
    () => trends?.data ?? trends ?? [],
    [trends],
  )

  return (
    <div className="space-y-6 mt-4">
      {wLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 animate-pulse">
          <div className="h-28 bg-muted rounded-lg" />
          <div className="h-28 bg-muted rounded-lg" />
          <div className="h-28 bg-muted rounded-lg" />
        </div>
      ) : ws ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <SummaryCard title={t('keywordDetail.exact')} value={ws.frequency_exact ?? ws.exact ?? '-'} />
          <SummaryCard title={t('keywordDetail.broad')} value={ws.frequency_broad ?? ws.broad ?? '-'} />
          <SummaryCard title={t('keywordDetail.phrase')} value={ws.frequency_phrase ?? ws.phrase ?? '-'} />
        </div>
      ) : (
        <EmptyState title={t('keywordDetail.noWordstat')} />
      )}

      <div>
        <h3 className="text-base font-semibold tracking-tight mb-3">{t('keywordDetail.trends')}</h3>
        {!tLoading && trendList.length > 0 && (
          <div className="rounded-lg border p-4 mb-4">
            <TrendChart data={trendList} />
          </div>
        )}
        {tLoading ? (
          <TableSkeleton rows={6} />
        ) : trendList.length === 0 ? (
          <EmptyState title={t('keywordDetail.noTrends')} />
        ) : (
          <div className="glass-card rounded-lg overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('keywordDetail.month')}</TableHead>
                <TableHead>{t('keywordDetail.value')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {trendList.map((item) => (
                <TableRow key={item.month}>
                  <TableCell>{item.month}</TableCell>
                  <TableCell className="tabular-nums">{item.value}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          </div>
        )}
      </div>
    </div>
  )
}

function SuggestionsTab({ keywordId }: { keywordId: string }) {
  const { t } = useTranslation()
  const { data, isLoading } = useWordstatSuggestions(keywordId)
  const suggestions: WordstatSuggestion[] = useMemo(
    () => data?.data ?? data ?? [],
    [data],
  )

  return (
    <div className="mt-4">
      {isLoading ? (
        <TableSkeleton rows={8} />
      ) : suggestions.length === 0 ? (
        <EmptyState title={t('keywordDetail.noSuggestions')} />
      ) : (
        <div className="glass-card rounded-lg overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('keywordDetail.suggestion')}</TableHead>
              <TableHead>{t('keywordDetail.frequency')}</TableHead>
              <TableHead>{t('keywordDetail.type')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {suggestions.map((item) => (
              <TableRow key={item.suggestion}>
                <TableCell>{item.suggestion}</TableCell>
                <TableCell className="tabular-nums">
                  {item.frequency}
                </TableCell>
                <TableCell>
                  {item.type ? <Badge variant="outline">{item.type}</Badge> : null}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        </div>
      )}
    </div>
  )
}
