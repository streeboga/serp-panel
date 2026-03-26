import { createLazyFileRoute, Link } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useKeyword } from '@/hooks/useKeywords'
import {
  useSerpResults,
  useSerpDates,
  useSerpHistory,
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
import { EngineBadge } from '@/components/EngineBadge'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { SummaryCard } from '@/components/SummaryCard'
import { EmptyState } from '@/components/EmptyState'
import { DataExportButton } from '@/components/DataExportButton'
import { TableSkeleton } from '@/components/PageSkeleton'
import { PositionChart } from '@/components/charts/PositionChart'
import { TrendChart } from '@/components/charts/TrendChart'
import type { SerpResult, SerpHistoryItem, WordstatTrend, WordstatSuggestion } from '@/types/api'

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
    <div className="space-y-6">
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

      {kw && (
        <div className="flex items-center gap-3">
          <h2 className="text-xl font-bold">{kw.keyword}</h2>
          <EngineBadge engine={kw.engine} />
        </div>
      )}

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

function SerpTab({ keywordId }: { keywordId: string }) {
  const { t } = useTranslation()
  const [date, setDate] = useState<string | undefined>(undefined)
  const [topN, setTopN] = useState(20)
  const { data: dates } = useSerpDates(keywordId)
  const { data: serpData, isLoading } = useSerpResults(keywordId, {
    date,
    top_n: topN,
  })

  const dateList: string[] = useMemo(
    () => {
      // Try from dates endpoint, fallback to extracting from snapshots
      const d = dates?.data ?? dates ?? []
      if (Array.isArray(d) && d.length > 0) return d
      // Extract dates from snapshots
      const snapshots = serpData?.data ?? serpData ?? []
      if (Array.isArray(snapshots)) {
        return snapshots.map((s: any) => s.collected_at?.split('T')[0] ?? s.date).filter(Boolean)
      }
      return []
    },
    [dates, serpData],
  )
  const results: SerpResult[] = useMemo(
    () => {
      const snapshots = serpData?.data ?? serpData ?? []
      if (!Array.isArray(snapshots) || snapshots.length === 0) return []
      // Get the first (latest) snapshot's results
      const snap = snapshots[0]
      const res = snap?.results ?? snap?.data?.results ?? []
      return Array.isArray(res) ? res : []
    },
    [serpData],
  )

  const handleDateChange = useCallback((v: string | null) => {
    setDate(!v || v === '__latest__' ? undefined : v)
  }, [])

  const handleTopNChange = useCallback((v: string | null) => {
    setTopN(v ? Number(v) : 20)
  }, [])

  const getExportData = useCallback(
    () =>
      results.map((item) => ({
        position: item.position,
        domain: item.domain,
        type: item.site_type?.name ?? '',
        title: item.title,
        url: item.url,
        is_own: item.is_own ? 'yes' : 'no',
      })),
    [results],
  )

  return (
    <div className="space-y-4 mt-4">
      <div className="flex gap-3 items-end">
        <Select
          value={date ?? '__latest__'}
          onValueChange={handleDateChange}
        >
          <SelectTrigger>
            <SelectValue placeholder={t('keywordDetail.latestDate')} labels={{ '__latest__': t('keywordDetail.latestDate'), ...Object.fromEntries(dateList.map((d) => [d, d])) }} />
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

        <Select
          value={String(topN)}
          onValueChange={handleTopNChange}
        >
          <SelectTrigger>
            <SelectValue labels={{ '10': 'TOP-10', '20': 'TOP-20', '50': 'TOP-50', '100': 'TOP-100' }} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="10" label="TOP-10">TOP-10</SelectItem>
            <SelectItem value="20" label="TOP-20">TOP-20</SelectItem>
            <SelectItem value="50" label="TOP-50">TOP-50</SelectItem>
            <SelectItem value="100" label="TOP-100">TOP-100</SelectItem>
          </SelectContent>
        </Select>

        <DataExportButton
          getData={getExportData}
          filename={`serp-keyword-${keywordId}`}
        />
      </div>

      {isLoading ? (
        <TableSkeleton />
      ) : results.length === 0 ? (
        <EmptyState title={t('keywordDetail.noSerpData')} />
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-16">#</TableHead>
              <TableHead>{t('keywordDetail.domain')}</TableHead>
              <TableHead>{t('keywordDetail.type')}</TableHead>
              <TableHead>{t('keywordDetail.title')}</TableHead>
              <TableHead>{t('keywordDetail.url')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {results.map((item, idx) => (
              <TableRow
                key={idx}
                className={item.is_own ? 'bg-green-50 dark:bg-green-950/20' : ''}
              >
                <TableCell className="font-medium tabular-nums">
                  {item.position}
                </TableCell>
                <TableCell>{item.domain}</TableCell>
                <TableCell>
                  <SiteTypeBadge type={item.site_type ?? null} />
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
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
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
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('keywordDetail.date')}</TableHead>
              <TableHead>{t('keywordDetail.position')}</TableHead>
              <TableHead>{t('keywordDetail.url')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {history.map((item, idx) => (
              <TableRow key={idx}>
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
      )}
    </div>
  )
}

function WordstatTab({ keywordId }: { keywordId: string }) {
  const { t } = useTranslation()
  const { data: wordstat, isLoading: wLoading } = useWordstat(keywordId)
  const { data: trends, isLoading: tLoading } = useWordstatTrends(keywordId)

  const ws = wordstat?.data ?? wordstat
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
          <SummaryCard title={t('keywordDetail.exact')} value={ws.exact ?? '-'} />
          <SummaryCard title={t('keywordDetail.broad')} value={ws.broad ?? '-'} />
          <SummaryCard title={t('keywordDetail.phrase')} value={ws.phrase ?? '-'} />
        </div>
      ) : (
        <EmptyState title={t('keywordDetail.noWordstat')} />
      )}

      <div>
        <h3 className="text-lg font-semibold mb-3">{t('keywordDetail.trends')}</h3>
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
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('keywordDetail.month')}</TableHead>
                <TableHead>{t('keywordDetail.value')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {trendList.map((item, idx) => (
                <TableRow key={idx}>
                  <TableCell>{item.month}</TableCell>
                  <TableCell className="tabular-nums">{item.value}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
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
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('keywordDetail.suggestion')}</TableHead>
              <TableHead>{t('keywordDetail.frequency')}</TableHead>
              <TableHead>{t('keywordDetail.type')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {suggestions.map((item, idx) => (
              <TableRow key={idx}>
                <TableCell>{item.suggestion}</TableCell>
                <TableCell className="tabular-nums">
                  {item.frequency}
                </TableCell>
                <TableCell>
                  {item.type && <Badge variant="outline">{item.type}</Badge>}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
