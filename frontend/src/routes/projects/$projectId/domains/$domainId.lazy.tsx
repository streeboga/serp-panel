import { createLazyFileRoute, Link } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useDomain, useDomains, useIndexDomain, useDomainIndexResults, useDomainKeywords } from '@/hooks/useDomains'
import { usePages, useCreatePage, useImportPages } from '@/hooks/usePages'
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
    <div className="space-y-3">
      {/* Compact header: breadcrumb + domain name + type in one line */}
      <div className="flex items-center gap-2 text-sm">
        <Link to="/projects/$projectId/domains" params={{ projectId }} className="text-muted-foreground hover:underline">
          Домены
        </Link>
        <span className="text-muted-foreground">/</span>
        <span className="font-semibold">{domain?.name ?? '...'}</span>
        {domain ? (
          <Badge
            variant={typeCfg.variant}
            className={`text-[10px] ${domain.type === 'own' ? 'bg-green-500 text-white hover:bg-green-600' : ''}`}
          >
            {typeCfg.label}
          </Badge>
        ) : null}
        {domain?.tags?.map((tag) => (
          <Badge key={tag.id} variant="outline" className="text-[10px]">{tag.name}</Badge>
        ))}
        {parentDomain ? (
          <span className="text-xs text-muted-foreground">
            ← <Link to="/projects/$projectId/domains/$domainId" params={{ projectId, domainId: String(parentDomain.id) }} className="hover:underline text-primary">{parentDomain.name}</Link>
          </span>
        ) : null}
        {domain?.indexed_pages_count ? (
          <span className="text-xs text-muted-foreground ml-auto">В индексе: {domain.indexed_pages_count}</span>
        ) : null}
      </div>

      {/* Tabs */}
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

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const pages: any[] = useMemo(() => {
    const d = pagesData?.data ?? pagesData
    return Array.isArray(d) ? d : []
  }, [pagesData])

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

      {isLoading ? (
        <TableSkeleton />
      ) : pages.length === 0 ? (
        <EmptyState title={t('domainDetail.noPages')} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('pages.url')}</TableHead>
              <TableHead>{t('pages.title_field')}</TableHead>
              <TableHead>{t('pages.pageType')}</TableHead>
              <TableHead>{t('pages.tags')}</TableHead>
              <TableHead>{t('pages.attachments')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {pages.map((page) => (
              <TableRow key={page.id}>
                <TableCell className="text-sm font-medium max-w-[400px]" title={page.url}>
                  {truncateUrl(page.url ?? '')}
                </TableCell>
                <TableCell className="text-sm max-w-[200px]" title={page.title ?? ''}>
                  {page.title ?? '—'}
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
                <TableCell>
                  <div className="flex flex-wrap gap-1">
                    {Array.isArray(page.tags) && page.tags.length > 0
                      ? page.tags.map((tag: any) => {
                          const name = typeof tag === 'string' ? tag : tag.name
                          return (
                            <Badge key={name} variant="secondary" className="text-xs">
                              {name}
                            </Badge>
                          )
                        })
                      : '—'}
                  </div>
                </TableCell>
                <TableCell className="text-sm text-muted-foreground">
                  {page.keywords_count ?? page.attachments_count ?? 0}
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
              <TableHead>{t('domainDetail.url')}</TableHead>
              <TableHead>{t('domainDetail.title')}</TableHead>
              <TableHead className="max-w-[300px]">{t('domainDetail.description')}</TableHead>
              <TableHead>{t('domainDetail.snippetLinks')}</TableHead>
              <TableHead>{t('domainDetail.engine')}</TableHead>
              <TableHead>{t('domainDetail.date')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {results.map((row) => (
              <TableRow key={row.id}>
                <TableCell className="tabular-nums font-medium">{row.position}</TableCell>
                <TableCell className="text-sm max-w-[250px]" title={row.url}>
                  <a
                    href={row.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-primary hover:underline"
                  >
                    {truncateUrl(row.url, 50)}
                  </a>
                </TableCell>
                <TableCell className="text-sm max-w-[200px]" title={row.title ?? ''}>
                  {row.title ?? '—'}
                </TableCell>
                <TableCell className="text-sm text-muted-foreground max-w-[300px]">
                  <div>
                    {row.description
                      ? row.description.length > 120
                        ? `${row.description.slice(0, 120)}...`
                        : row.description
                      : '—'}
                  </div>
                </TableCell>
                <TableCell>
                  {row.snippet_links && row.snippet_links.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                      {row.snippet_links.map((link, idx) => (
                        <a
                          key={idx}
                          href={link.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-block"
                        >
                          <Badge variant="outline" className="text-xs cursor-pointer hover:bg-muted">
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
              <TableHead>{t('keywords.keyword')}</TableHead>
              <TableHead>{t('keywords.engine')}</TableHead>
              <TableHead>{t('keywords.device')}</TableHead>
              <TableHead>{t('keywords.cluster')}</TableHead>
              <TableHead>{t('keywords.category')}</TableHead>
              <TableHead>{t('keywords.region')}</TableHead>
              <TableHead className="text-right">{t('keywords.position')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {keywords.map((kw) => (
              <TableRow key={kw.id}>
                <TableCell className="text-sm font-medium">{kw.keyword}</TableCell>
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
                <TableCell className="text-sm">{kw.cluster ?? '—'}</TableCell>
                <TableCell className="text-sm">{kw.category ?? '—'}</TableCell>
                <TableCell className="text-sm">{kw.region ?? '—'}</TableCell>
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
