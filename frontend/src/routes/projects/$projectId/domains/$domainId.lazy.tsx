import { createLazyFileRoute, Link } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useDomain, useDomains, useIndexDomain, useDomainIndexResults, useDomainKeywords } from '@/hooks/useDomains'
import { usePages, useCreatePage, useImportPages, useBulkAttachPage } from '@/hooks/usePages'
import { useCategories } from '@/hooks/useCategories'
import { useKeywords, useProjectClusters } from '@/hooks/useKeywords'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SummaryCard } from '@/components/SummaryCard'
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
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { parseApiError } from '@/lib/api'
import type { Domain, DomainIndexResult } from '@/types/api'

export const Route = createLazyFileRoute(
  '/projects/$projectId/domains/$domainId',
)({
  component: DomainDetailPage,
})

const DOMAIN_TYPE_CONFIG: Record<
  string,
  { label: string; variant: 'default' | 'secondary' | 'outline' }
> = {
  own: { label: 'Свой', variant: 'default' },
  competitor: { label: 'Конкурент', variant: 'secondary' },
  satellite: { label: 'Сателлит', variant: 'outline' },
}

const PAGE_TYPE_LABELS: Record<string, string> = {
  commercial: 'Коммерческая',
  informational: 'Информационная',
  navigational: 'Навигационная',
  transactional: 'Транзакционная',
}

const PAGE_TYPE_BADGE_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
  commercial: 'default',
  informational: 'secondary',
  navigational: 'outline',
  transactional: 'default',
}

function truncateUrl(url: string, maxLen = 60): string {
  return url.length > maxLen ? `${url.slice(0, maxLen)}...` : url
}

const BULK_TYPES = ['keyword', 'cluster', 'category'] as const

function DomainDetailPage() {
  const { t } = useTranslation()
  const { projectId, domainId } = Route.useParams()
  const { data: domainData } = useDomain(domainId)
  const { data: domainsData } = useDomains(projectId)

  const domain: Domain | null = useMemo(() => {
    const d = domainData?.data ?? domainData
    return d ?? null
  }, [domainData])

  const domains: Domain[] = useMemo(() => {
    const d = domainsData?.data ?? domainsData
    return Array.isArray(d) ? d : []
  }, [domainsData])

  const parentDomain = useMemo(() => {
    if (!domain?.parent_id) return null
    if (domain.parent) return domain.parent
    return domains.find((d) => d.id === domain.parent_id) ?? null
  }, [domain, domains])

  const typeCfg = useMemo(
    () => DOMAIN_TYPE_CONFIG[domain?.type ?? 'competitor'] ?? DOMAIN_TYPE_CONFIG.competitor,
    [domain?.type],
  )

  const [activeTab, setActiveTab] = useState('overview')

  return (
    <div className="space-y-2">
      {/* Tabs only — breadcrumbs handled by parent layout */}
      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList>
          <TabsTrigger value="overview">{t('domainDetail.overview')}</TabsTrigger>
          <TabsTrigger value="pages">{t('domainDetail.pages')}</TabsTrigger>
          <TabsTrigger value="index">{t('domainDetail.index')}</TabsTrigger>
          <TabsTrigger value="keywords">{t('domainDetail.keywords')}</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <OverviewTab domain={domain} projectId={projectId} domainId={domainId} />
        </TabsContent>

        <TabsContent value="pages">
          <PagesTab projectId={projectId} domainId={domainId} />
        </TabsContent>

        <TabsContent value="index">
          <IndexTab domainId={domainId} projectId={projectId} />
        </TabsContent>

        <TabsContent value="keywords">
          <KeywordsTab domainId={domainId} />
        </TabsContent>
      </Tabs>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Tab 1: Overview                                                    */
/* ------------------------------------------------------------------ */

function OverviewTab({
  domain,
  projectId,
  domainId,
}: {
  domain: Domain | null
  projectId: string
  domainId: string
}) {
  const { t } = useTranslation()
  const indexDomain = useIndexDomain()
  const { data: pagesData } = usePages({ projectId, domain_id: domainId })

  const pagesCount = useMemo(() => {
    const d = pagesData?.data ?? pagesData
    return Array.isArray(d) ? d.length : 0
  }, [pagesData])

  const handleRunIndex = useCallback(() => {
    indexDomain.mutate({ domainId })
  }, [indexDomain, domainId])

  return (
    <div className="space-y-4 pt-4">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <SummaryCard title={t('domainDetail.pagesCount')} value={pagesCount} />
        <SummaryCard
          title={t('domainDetail.indexedCount')}
          value={domain?.indexed_pages_count ?? 0}
        />
        <SummaryCard title="TOP-3" value="—" />
        <SummaryCard title="TOP-10" value="—" />
      </div>
      <div>
        <Button
          className="h-8 text-xs"
          onClick={handleRunIndex}
          disabled={indexDomain.isPending}
        >
          {indexDomain.isPending ? t('domains.indexing') : t('domainDetail.runIndex')}
        </Button>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Tab 2: Pages                                                       */
/* ------------------------------------------------------------------ */

function PagesTab({
  projectId,
  domainId,
}: {
  projectId: string
  domainId: string
}) {
  const { t } = useTranslation()
  const { data: pagesData, isLoading } = usePages({ projectId, domain_id: domainId })
  const createPage = useCreatePage(projectId)
  const bulkAttach = useBulkAttachPage()

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const pages: any[] = useMemo(() => {
    const d = pagesData?.data ?? pagesData
    return Array.isArray(d) ? d : []
  }, [pagesData])

  // Selection state
  const [selectedIds, setSelectedIds] = useState(() => new Set<number>())

  const toggleSelect = useCallback((id: number) => {
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }, [])

  const toggleAll = useCallback(() => {
    setSelectedIds((prev) =>
      prev.size === pages.length ? new Set() : new Set(pages.map((p) => p.id)),
    )
  }, [pages])

  // Bulk assign dialog
  const [bulkOpen, setBulkOpen] = useState(false)
  const [bulkType, setBulkType] = useState<'keyword' | 'cluster' | 'category'>('keyword')
  const [bulkIds, setBulkIds] = useState(() => new Set<number>())
  const [bulkError, setBulkError] = useState<string | null>(null)

  // Data for bulk assign selectors
  const { data: keywordsData } = useKeywords({ projectId, per_page: 500 })
  const { data: categoriesData } = useCategories(domainId)
  const { data: clustersData } = useProjectClusters(projectId)

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const projectKeywords: any[] = useMemo(() => {
    const d = keywordsData?.data ?? keywordsData
    return Array.isArray(d) ? d : []
  }, [keywordsData])

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const categories: any[] = useMemo(() => {
    const d = categoriesData?.data ?? categoriesData
    return Array.isArray(d) ? d : []
  }, [categoriesData])

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const clusters: any[] = useMemo(() => {
    const d = clustersData?.data ?? clustersData
    return Array.isArray(d) ? d : []
  }, [clustersData])

  const bulkItems = useMemo(() => {
    if (bulkType === 'keyword') return projectKeywords.map((k) => ({ id: k.id, name: k.keyword }))
    if (bulkType === 'cluster') return clusters.map((c) => ({ id: c.id, name: c.name }))
    return categories.map((c) => ({ id: c.id, name: c.name }))
  }, [bulkType, projectKeywords, clusters, categories])

  const toggleBulkId = useCallback((id: number) => {
    setBulkIds((prev) => {
      const next = new Set(prev)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }, [])

  const openBulkForPage = useCallback((pageId: number) => {
    setSelectedIds(new Set([pageId]))
    setBulkType('keyword')
    setBulkIds(new Set())
    setBulkSearch('')
    setBulkError(null)
    setBulkOpen(true)
  }, [])

  const [bulkSearch, setBulkSearch] = useState('')

  const filteredBulkItems = useMemo(() => {
    if (!bulkSearch.trim()) return bulkItems
    const q = bulkSearch.toLowerCase()
    return bulkItems.filter((item) => item.name?.toLowerCase().includes(q))
  }, [bulkItems, bulkSearch])

  const handleBulkAssign = useCallback(async () => {
    if (selectedIds.size === 0 || bulkIds.size === 0) return
    setBulkError(null)
    try {
      const ids = Array.from(bulkIds)
      await Promise.all(
        Array.from(selectedIds).map((pageId) =>
          bulkAttach.mutateAsync({
            pageId: String(pageId),
            data: { pageable_type: bulkType, pageable_ids: ids },
          }),
        ),
      )
      setBulkOpen(false)
      setBulkIds(new Set())
      setSelectedIds(new Set())
    } catch (err) {
      setBulkError(parseApiError(err))
    }
  }, [selectedIds, bulkIds, bulkType, bulkAttach])

  // Create dialog
  const [createOpen, setCreateOpen] = useState(false)
  const [createUrl, setCreateUrl] = useState('')
  const [createTitle, setCreateTitle] = useState('')
  const [createPageType, setCreatePageType] = useState('')
  const [createNotes, setCreateNotes] = useState('')
  const [createTags, setCreateTags] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)

  const resetCreateForm = useCallback(() => {
    setCreateUrl('')
    setCreateTitle('')
    setCreatePageType('')
    setCreateNotes('')
    setCreateTags('')
    setCreateError(null)
  }, [])

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setCreateError(null)
      try {
        await createPage.mutateAsync({
          url: createUrl.trim(),
          title: createTitle.trim() || undefined,
          page_type: createPageType || undefined,
          domain_id: Number(domainId),
          notes: createNotes.trim() || undefined,
          tags: createTags
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean),
        })
        resetCreateForm()
        setCreateOpen(false)
      } catch (err) {
        setCreateError(parseApiError(err))
      }
    },
    [createUrl, createTitle, createPageType, domainId, createNotes, createTags, createPage, resetCreateForm],
  )

  return (
    <div className="space-y-4 pt-4">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium text-muted-foreground">
          {t('domainDetail.pages')} ({pages.length})
        </h3>
        <div className="flex items-center gap-2">
          {selectedIds.size > 0 ? (
            <Button
              className="h-8 text-xs"
              variant="secondary"
              onClick={() => {
                setBulkType('keyword')
                setBulkIds(new Set())
                setBulkSearch('')
                setBulkError(null)
                setBulkOpen(true)
              }}
            >
              Назначить ({selectedIds.size})
            </Button>
          ) : null}
          <Button
            className="h-8 text-xs"
            onClick={() => {
              resetCreateForm()
              setCreateOpen(true)
            }}
          >
            {t('pages.addPage')}
          </Button>
        </div>
      </div>

      {isLoading ? (
        <TableSkeleton />
      ) : pages.length === 0 ? (
        <EmptyState title={t('domainDetail.noPages')} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-10">
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded border-input accent-primary"
                  checked={selectedIds.size === pages.length && pages.length > 0}
                  onChange={toggleAll}
                />
              </TableHead>
              <TableHead className="max-w-[300px]">{t('pages.url')}</TableHead>
              <TableHead className="max-w-[160px]">{t('pages.title_field')}</TableHead>
              <TableHead className="w-[120px]">{t('pages.pageType')}</TableHead>
              <TableHead className="max-w-[140px]">{t('pages.tags')}</TableHead>
              <TableHead className="w-[60px]">{t('pages.attachments')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {pages.map((page) => (
              <TableRow key={page.id} data-state={selectedIds.has(page.id) ? 'selected' : undefined}>
                <TableCell className="w-10">
                  <input
                    type="checkbox"
                    className="h-4 w-4 rounded border-input accent-primary"
                    checked={selectedIds.has(page.id)}
                    onChange={() => toggleSelect(page.id)}
                  />
                </TableCell>
                <TableCell className="text-sm font-medium max-w-[300px]" title={page.url}>
                  <span className="block truncate">{page.path || page.url}</span>
                </TableCell>
                <TableCell className="text-sm max-w-[160px]" title={page.title ?? ''}>
                  <span className="block truncate">{page.title ?? '—'}</span>
                </TableCell>
                <TableCell>
                  {page.page_type ? (
                    <Badge variant={PAGE_TYPE_BADGE_VARIANT[page.page_type] ?? 'default'}>
                      {PAGE_TYPE_LABELS[page.page_type] ?? page.page_type}
                    </Badge>
                  ) : (
                    '—'
                  )}
                </TableCell>
                <TableCell className="max-w-[140px]">
                  <div className="flex flex-wrap gap-1 overflow-hidden max-h-[1.75rem]">
                    {Array.isArray(page.tags) && page.tags.length > 0
                      ? page.tags.map((tag: any) => {
                          const name = typeof tag === 'string' ? tag : tag.name
                          return (
                            <Badge key={name} variant="secondary" className="text-xs truncate max-w-[100px]">
                              {name}
                            </Badge>
                          )
                        })
                      : '—'}
                  </div>
                </TableCell>
                <TableCell className="text-sm">
                  {(() => {
                    const kws = Array.isArray(page.keywords) ? page.keywords : []
                    const cls = Array.isArray(page.clusters) ? page.clusters : []
                    const cats = Array.isArray(page.categories) ? page.categories : []
                    const hasAny = kws.length > 0 || cls.length > 0 || cats.length > 0
                    if (!hasAny) {
                      return (
                        <Button
                          variant="ghost"
                          className="h-6 px-2 text-xs text-muted-foreground"
                          onClick={() => openBulkForPage(page.id)}
                        >
                          Выбрать
                        </Button>
                      )
                    }
                    return (
                      <div className="flex flex-wrap gap-1 overflow-hidden max-h-[2.5rem]">
                        {kws.map((k: any) => (
                          <Badge key={`kw-${k.id}`} variant="outline" className="text-[10px] truncate max-w-[90px]">
                            {k.keyword}
                          </Badge>
                        ))}
                        {cls.map((c: any) => (
                          <Badge key={`cl-${c.id}`} variant="secondary" className="text-[10px] truncate max-w-[90px]">
                            {c.name}
                          </Badge>
                        ))}
                        {cats.map((c: any) => (
                          <Badge key={`cat-${c.id}`} className="text-[10px] truncate max-w-[90px]">
                            {c.name}
                          </Badge>
                        ))}
                      </div>
                    )
                  })()}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}

      {/* Create page dialog */}
      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('pages.createPage')}</DialogTitle>
          </DialogHeader>
          {createError ? <p className="text-sm text-destructive">{createError}</p> : null}
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.url')}</Label>
              <Input
                placeholder="https://example.com/page"
                value={createUrl}
                onChange={(e) => setCreateUrl(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.title_field')}</Label>
              <Input
                placeholder="Название страницы"
                value={createTitle}
                onChange={(e) => setCreateTitle(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.pageType')}</Label>
              <Select value={createPageType} onValueChange={(v) => setCreatePageType(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Выберите тип" labels={PAGE_TYPE_LABELS} />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(PAGE_TYPE_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value} label={label}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.notes')}</Label>
              <textarea
                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Заметки..."
                value={createNotes}
                onChange={(e) => setCreateNotes(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.tags')}</Label>
              <Input
                placeholder="тег1, тег2, тег3"
                value={createTags}
                onChange={(e) => setCreateTags(e.target.value)}
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={createPage.isPending || !createUrl.trim()}>
                {createPage.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Bulk assign dialog */}
      <Dialog open={bulkOpen} onOpenChange={setBulkOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Назначить для {selectedIds.size} страниц</DialogTitle>
          </DialogHeader>
          {bulkError ? <p className="text-sm text-destructive">{bulkError}</p> : null}
          <div className="space-y-3">
            <div className="flex gap-1">
              {BULK_TYPES.map((type) => (
                <Button
                  key={type}
                  variant={bulkType === type ? 'default' : 'outline'}
                  className="h-8 text-xs"
                  onClick={() => {
                    setBulkType(type)
                    setBulkIds(new Set())
                    setBulkSearch('')
                  }}
                >
                  {type === 'keyword' ? 'Ключевики' : type === 'cluster' ? 'Кластеры' : 'Категории'}
                </Button>
              ))}
            </div>
            <Input
              placeholder="Поиск..."
              value={bulkSearch}
              onChange={(e) => setBulkSearch(e.target.value)}
              className="h-8 text-sm"
            />
            <div className="border rounded-md max-h-[280px] overflow-y-auto">
              {filteredBulkItems.length === 0 ? (
                <p className="text-sm text-muted-foreground p-3">Нет элементов</p>
              ) : (
                filteredBulkItems.map((item) => (
                  <label
                    key={item.id}
                    className="flex items-center gap-2 px-3 py-1.5 hover:bg-muted cursor-pointer text-sm"
                  >
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded border-input accent-primary"
                      checked={bulkIds.has(item.id)}
                      onChange={() => toggleBulkId(item.id)}
                    />
                    <span className="truncate">{item.name}</span>
                  </label>
                ))
              )}
            </div>
            <DialogFooter>
              <Button
                onClick={handleBulkAssign}
                disabled={bulkIds.size === 0 || bulkAttach.isPending}
                className="h-8 text-xs"
              >
                {bulkAttach.isPending
                  ? 'Назначаем...'
                  : `Назначить (${bulkIds.size})`}
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Tab 3: Index Results                                               */
/* ------------------------------------------------------------------ */

function IndexTab({ domainId, projectId }: { domainId: string; projectId: string }) {
  const { t } = useTranslation()
  const [limit, setLimit] = useState(100)
  const { data: indexData, isLoading } = useDomainIndexResults(domainId, limit)
  const indexDomain = useIndexDomain()
  const importPages = useImportPages(projectId)

  const results: DomainIndexResult[] = useMemo(() => {
    const d = indexData?.data ?? indexData
    return Array.isArray(d) ? d : []
  }, [indexData])

  const handleIndexGoogle = useCallback(() => {
    indexDomain.mutate({ domainId, engine: 'google', limit: 100 })
  }, [indexDomain, domainId])

  const handleIndexYandex = useCallback(() => {
    indexDomain.mutate({ domainId, engine: 'yandex', limit: 100 })
  }, [indexDomain, domainId])

  const handleLoadMore = useCallback(() => {
    // Re-index with current + 100 more
    const newLimit = results.length + 100
    indexDomain.mutate({ domainId, engine: 'google', limit: newLimit })
    setLimit(newLimit)
  }, [indexDomain, domainId, results.length])

  const handleLoadAll = useCallback(() => {
    // Index ALL pages — limit 1000 (100 pages × 10 results)
    indexDomain.mutate({ domainId, engine: 'google', limit: 1000 })
    setLimit(10000)
  }, [indexDomain, domainId])

  const formatDate = useCallback((dateStr: string) => {
    const d = new Date(dateStr)
    return `${String(d.getDate()).padStart(2, '0')}.${String(d.getMonth() + 1).padStart(2, '0')}`
  }, [])

  return (
    <div className="space-y-3 pt-3">
      <div className="flex items-center gap-2 flex-wrap">
        <Button
          className="h-8 text-xs"
          onClick={handleIndexGoogle}
          disabled={indexDomain.isPending}
        >
          {indexDomain.isPending ? '⏳ Индексируем...' : 'Индексировать Google'}
        </Button>
        <Button
          className="h-8 text-xs"
          variant="outline"
          onClick={handleIndexYandex}
          disabled={indexDomain.isPending}
        >
          {indexDomain.isPending ? '⏳ Индексируем...' : 'Индексировать Яндекс'}
        </Button>
        {indexDomain.isSuccess ? (
          <Badge variant="secondary" className="text-[10px]">✓ Задача в очереди</Badge>
        ) : null}
        <Button
          className="h-8 text-xs"
          variant="outline"
          onClick={handleLoadMore}
        >
          {t('domainDetail.loadMore')}
        </Button>
        <Button
          className="h-8 text-xs"
          variant="outline"
          onClick={handleLoadAll}
        >
          {t('domainDetail.loadAll')}
        </Button>
        {results.length > 0 ? (
          <Button
            className="h-8 text-xs"
            variant="secondary"
            disabled={importPages.isPending}
            onClick={() => {
              const pages = results.map((r) => ({
                url: r.url,
                title: r.title ?? undefined,
              }))
              importPages.mutate({ pages })
            }}
          >
            {importPages.isPending ? '⏳ Создаём...' : `Создать ${results.length} страниц`}
          </Button>
        ) : null}
        {importPages.isSuccess ? (
          <Badge variant="secondary" className="text-[10px]">✓ Страницы созданы</Badge>
        ) : null}
        <span className="text-xs text-muted-foreground ml-auto">
          {results.length > 0 ? `${results.length} результатов` : ''}
        </span>
      </div>

      {isLoading ? (
        <TableSkeleton />
      ) : results.length === 0 ? (
        <EmptyState title={t('domainDetail.noIndex')} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-12">{t('domainDetail.position')}</TableHead>
              <TableHead className="max-w-[200px]">{t('domainDetail.url')}</TableHead>
              <TableHead className="max-w-[180px]">{t('domainDetail.title')}</TableHead>
              <TableHead className="max-w-[250px]">{t('domainDetail.description')}</TableHead>
              <TableHead className="max-w-[120px]">{t('domainDetail.snippetLinks')}</TableHead>
              <TableHead className="w-[60px]">{t('domainDetail.engine')}</TableHead>
              <TableHead className="w-[60px]">{t('domainDetail.date')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {results.map((row) => (
              <TableRow key={row.id}>
                <TableCell className="tabular-nums font-medium">{row.position}</TableCell>
                <TableCell className="text-sm max-w-[200px]" title={row.url}>
                  <a
                    href={row.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary hover:underline block truncate"
                  >
                    {new URL(row.url).pathname}
                  </a>
                </TableCell>
                <TableCell className="text-sm max-w-[180px]" title={row.title ?? ''}>
                  <span className="block truncate">{row.title ?? '—'}</span>
                </TableCell>
                <TableCell className="text-sm text-muted-foreground max-w-[250px]">
                  <span className="block truncate">{row.description ?? '—'}</span>
                </TableCell>
                <TableCell className="max-w-[120px]">
                  {row.snippet_links && row.snippet_links.length > 0 ? (
                    <div className="flex flex-wrap gap-1 overflow-hidden max-h-[1.75rem]">
                      {row.snippet_links.map((link, idx) => (
                        <a
                          key={idx}
                          href={link.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-block"
                        >
                          <Badge variant="outline" className="text-xs cursor-pointer hover:bg-muted truncate max-w-[100px]">
                            {link.title}
                          </Badge>
                        </a>
                      ))}
                    </div>
                  ) : (
                    '—'
                  )}
                </TableCell>
                <TableCell>
                  <Badge variant="secondary" className="text-xs">
                    {row.engine}
                  </Badge>
                </TableCell>
                <TableCell className="text-sm text-muted-foreground tabular-nums">
                  {formatDate(row.collected_at)}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Tab 4: Keywords                                                    */
/* ------------------------------------------------------------------ */

const ENGINE_LABELS: Record<string, string> = {
  google: 'Google',
  yandex: 'Яндекс',
}

const DEVICE_LABELS: Record<string, string> = {
  desktop: 'ПК',
  mobile: 'Моб.',
}

function KeywordsTab({ domainId }: { domainId: string }) {
  const { t } = useTranslation()
  const { data: keywordsData, isLoading } = useDomainKeywords(domainId)

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const keywords: any[] = useMemo(() => {
    const d = keywordsData?.data ?? keywordsData
    return Array.isArray(d) ? d : []
  }, [keywordsData])

  return (
    <div className="space-y-4 pt-4">
      <h3 className="text-sm font-medium text-muted-foreground">
        {t('domainDetail.keywords')} ({keywords.length})
      </h3>

      {isLoading ? (
        <TableSkeleton />
      ) : keywords.length === 0 ? (
        <EmptyState title={t('domainDetail.noKeywords')} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="max-w-[250px]">{t('keywords.keyword')}</TableHead>
              <TableHead className="w-[70px]">{t('keywords.engine')}</TableHead>
              <TableHead className="w-[60px]">{t('keywords.device')}</TableHead>
              <TableHead className="max-w-[140px]">{t('keywords.cluster')}</TableHead>
              <TableHead className="max-w-[140px]">{t('keywords.category')}</TableHead>
              <TableHead className="max-w-[100px]">{t('keywords.region')}</TableHead>
              <TableHead className="w-[70px] text-right">{t('keywords.position')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {keywords.map((kw) => (
              <TableRow key={kw.id}>
                <TableCell className="text-sm font-medium max-w-[250px]">
                  <span className="block truncate">{kw.keyword}</span>
                </TableCell>
                <TableCell>
                  <Badge variant="secondary" className="text-xs">
                    {ENGINE_LABELS[kw.engine] ?? kw.engine}
                  </Badge>
                </TableCell>
                <TableCell>
                  <Badge variant="outline" className="text-xs">
                    {DEVICE_LABELS[kw.device] ?? kw.device}
                  </Badge>
                </TableCell>
                <TableCell className="text-sm max-w-[140px]">
                  <span className="block truncate">{kw.cluster ?? '—'}</span>
                </TableCell>
                <TableCell className="text-sm max-w-[140px]">
                  <span className="block truncate">{kw.category ?? '—'}</span>
                </TableCell>
                <TableCell className="text-sm max-w-[100px]">
                  <span className="block truncate">{kw.region ?? '—'}</span>
                </TableCell>
                <TableCell className="text-right tabular-nums font-medium">
                  {kw.latest_position != null ? (
                    <Badge
                      variant={kw.latest_position <= 3 ? 'default' : kw.latest_position <= 10 ? 'secondary' : 'outline'}
                      className={kw.latest_position <= 3 ? 'bg-green-500 text-white hover:bg-green-600' : undefined}
                    >
                      {kw.latest_position}
                    </Badge>
                  ) : (
                    '—'
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
