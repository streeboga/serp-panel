import { createFileRoute, Link } from '@tanstack/react-router'
import { useState } from 'react'
import { useKeyword } from '@/hooks/useKeywords'
import { useSerpResults, useSerpDates, useSerpHistory } from '@/hooks/useSerp'
import { useWordstat, useWordstatTrends, useWordstatSuggestions } from '@/hooks/useWordstat'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'

export const Route = createFileRoute('/projects/$projectId/keywords/$keywordId')({
  component: KeywordDetailPage,
})

function SiteTypeBadge({ type }: { type: { name: string; color: string } | null }) {
  if (!type) return null
  return (
    <Badge style={{ backgroundColor: type.color, color: 'white' }}>
      {type.name}
    </Badge>
  )
}

function KeywordDetailPage() {
  const { projectId, keywordId } = Route.useParams()
  const { data: keywordData } = useKeyword(projectId, keywordId)
  const kw = keywordData?.data ?? keywordData

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Link to="/projects/$projectId/keywords" params={{ projectId }} className="hover:underline">
          Keywords
        </Link>
        <span>/</span>
        <span>{kw?.keyword ?? 'Loading...'}</span>
      </div>

      {kw && (
        <div className="flex items-center gap-3">
          <h2 className="text-xl font-bold">{kw.keyword}</h2>
          <Badge
            variant={kw.engine === 'yandex' ? 'default' : 'secondary'}
            className={kw.engine === 'yandex' ? 'bg-red-500 text-white' : 'bg-blue-500 text-white'}
          >
            {kw.engine === 'yandex' ? 'Ya' : 'G'}
          </Badge>
        </div>
      )}

      <Tabs defaultValue="serp">
        <TabsList>
          <TabsTrigger value="serp">SERP</TabsTrigger>
          <TabsTrigger value="history">History</TabsTrigger>
          <TabsTrigger value="wordstat">Wordstat</TabsTrigger>
          <TabsTrigger value="suggestions">Suggestions</TabsTrigger>
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
  const [date, setDate] = useState<string | undefined>(undefined)
  const [topN, setTopN] = useState(20)
  const { data: dates } = useSerpDates(keywordId)
  const { data: serpData, isLoading } = useSerpResults(keywordId, { date, top_n: topN })

  const dateList: string[] = dates?.data ?? dates ?? []
  const results = serpData?.data ?? serpData ?? []

  return (
    <div className="space-y-4 mt-4">
      <div className="flex gap-3">
        <Select value={date ?? '__latest__'} onValueChange={(v: string | null) => setDate(!v || v === '__latest__' ? undefined : v)}>
          <SelectTrigger>
            <SelectValue placeholder="Latest date" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__latest__">Latest</SelectItem>
            {dateList.map((d: string) => (
              <SelectItem key={d} value={d}>{d}</SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={String(topN)} onValueChange={(v: string | null) => setTopN(v ? Number(v) : 20)}>
          <SelectTrigger>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="10">TOP-10</SelectItem>
            <SelectItem value="20">TOP-20</SelectItem>
            <SelectItem value="50">TOP-50</SelectItem>
            <SelectItem value="100">TOP-100</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {isLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="w-16">#</TableHead>
              <TableHead>Domain</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Title</TableHead>
              <TableHead>URL</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {Array.isArray(results) && results.length > 0 ? results.map((item: {
              position: number
              domain: string
              site_type?: { name: string; color: string } | null
              title: string
              url: string
              is_own?: boolean
            }, idx: number) => (
              <TableRow key={idx} className={item.is_own ? 'bg-green-50' : ''}>
                <TableCell className="font-medium">{item.position}</TableCell>
                <TableCell>{item.domain}</TableCell>
                <TableCell><SiteTypeBadge type={item.site_type ?? null} /></TableCell>
                <TableCell className="max-w-xs truncate">{item.title}</TableCell>
                <TableCell className="max-w-xs truncate">
                  <a href={item.url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
                    {item.url}
                  </a>
                </TableCell>
              </TableRow>
            )) : (
              <TableRow>
                <TableCell colSpan={5} className="text-center text-muted-foreground">No SERP data</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      )}
    </div>
  )
}

function HistoryTab({ keywordId }: { keywordId: string }) {
  const { data, isLoading } = useSerpHistory(keywordId)
  const history = data?.data ?? data ?? []

  return (
    <div className="mt-4">
      {isLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Date</TableHead>
              <TableHead>Position</TableHead>
              <TableHead>URL</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {Array.isArray(history) && history.length > 0 ? history.map((item: {
              date: string
              position: number | null
              url?: string
            }, idx: number) => (
              <TableRow key={idx}>
                <TableCell>{item.date}</TableCell>
                <TableCell>{item.position ?? '-'}</TableCell>
                <TableCell className="max-w-sm truncate">{item.url ?? '-'}</TableCell>
              </TableRow>
            )) : (
              <TableRow>
                <TableCell colSpan={3} className="text-center text-muted-foreground">No history data</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      )}
    </div>
  )
}

function WordstatTab({ keywordId }: { keywordId: string }) {
  const { data: wordstat, isLoading: wLoading } = useWordstat(keywordId)
  const { data: trends, isLoading: tLoading } = useWordstatTrends(keywordId)

  const ws = wordstat?.data ?? wordstat
  const trendList = trends?.data ?? trends ?? []

  return (
    <div className="space-y-6 mt-4">
      {wLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : ws ? (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium text-muted-foreground">Exact</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{ws.exact ?? '-'}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium text-muted-foreground">Broad</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{ws.broad ?? '-'}</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-medium text-muted-foreground">Phrase</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-2xl font-bold">{ws.phrase ?? '-'}</p>
            </CardContent>
          </Card>
        </div>
      ) : (
        <p className="text-muted-foreground">No Wordstat data available.</p>
      )}

      <div>
        <h3 className="text-lg font-semibold mb-3">Trends</h3>
        {tLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Month</TableHead>
                <TableHead>Value</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {Array.isArray(trendList) && trendList.length > 0 ? trendList.map((item: { month: string; value: number }, idx: number) => (
                <TableRow key={idx}>
                  <TableCell>{item.month}</TableCell>
                  <TableCell>{item.value}</TableCell>
                </TableRow>
              )) : (
                <TableRow>
                  <TableCell colSpan={2} className="text-center text-muted-foreground">No trend data</TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        )}
      </div>
    </div>
  )
}

function SuggestionsTab({ keywordId }: { keywordId: string }) {
  const { data, isLoading } = useWordstatSuggestions(keywordId)
  const suggestions = data?.data ?? data ?? []

  return (
    <div className="mt-4">
      {isLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Suggestion</TableHead>
              <TableHead>Frequency</TableHead>
              <TableHead>Type</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {Array.isArray(suggestions) && suggestions.length > 0 ? suggestions.map((item: {
              suggestion: string
              frequency: number
              type?: string
            }, idx: number) => (
              <TableRow key={idx}>
                <TableCell>{item.suggestion}</TableCell>
                <TableCell>{item.frequency}</TableCell>
                <TableCell>
                  {item.type && <Badge variant="outline">{item.type}</Badge>}
                </TableCell>
              </TableRow>
            )) : (
              <TableRow>
                <TableCell colSpan={3} className="text-center text-muted-foreground">No suggestions</TableCell>
              </TableRow>
            )}
          </TableBody>
        </Table>
      )}
    </div>
  )
}
