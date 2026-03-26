import { createFileRoute, Link } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import {
  useKeywords,
  useImportKeywords,
  useRegions,
  useProjectClusters,
} from '@/hooks/useKeywords'
import {
  useReactTable,
  getCoreRowModel,
  createColumnHelper,
  flexRender,
} from '@tanstack/react-table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EngineBadge } from '@/components/EngineBadge'
import { PositionBadge } from '@/components/PositionBadge'
import { FilterBar } from '@/components/FilterBar'
import { DataExportButton } from '@/components/DataExportButton'
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
  DialogTrigger,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { Keyword, Cluster, Region } from '@/types/api'

export const Route = createFileRoute('/projects/$projectId/keywords')({
  component: KeywordsPage,
})

const columnHelper = createColumnHelper<Keyword>()

function KeywordsPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [engine, setEngine] = useState<string | undefined>(undefined)
  const [device, setDevice] = useState<string | undefined>(undefined)

  const { data, isLoading } = useKeywords({
    projectId,
    page,
    per_page: 20,
    search: search || undefined,
    engine,
    device,
  })

  const importKeywords = useImportKeywords()
  const [importOpen, setImportOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importEngine, setImportEngine] = useState('google')
  const [importDevice, setImportDevice] = useState('desktop')
  const [importClusterId, setImportClusterId] = useState<string>('')
  const [importRegionId, setImportRegionId] = useState<string>('')

  const { data: clusters } = useProjectClusters(projectId)
  const { data: regions } = useRegions()

  const keywords: Keyword[] = useMemo(() => data?.data ?? [], [data])
  const meta = useMemo(
    () => data?.meta ?? { last_page: 1, current_page: 1, total: 0 },
    [data],
  )

  const columns = useMemo(
    () => [
      columnHelper.accessor('keyword', {
        header: t('keywords.keyword'),
        cell: (info) => (
          <Link
            to="/projects/$projectId/keywords/$keywordId"
            params={{
              projectId,
              keywordId: String(info.row.original.id),
            }}
            className="text-primary hover:underline"
          >
            {info.getValue()}
          </Link>
        ),
      }),
      columnHelper.accessor('engine', {
        header: t('keywords.engine'),
        cell: (info) => <EngineBadge engine={info.getValue()} />,
      }),
      columnHelper.accessor('latest_position', {
        header: t('keywords.position'),
        cell: (info) => (
          <PositionBadge
            position={info.getValue()}
            change={info.row.original.position_change}
          />
        ),
      }),
      columnHelper.accessor('frequency', {
        header: t('keywords.frequency'),
        cell: (info) => info.getValue() ?? '-',
      }),
      columnHelper.accessor('our_url', {
        header: t('keywords.ourUrl'),
        cell: (info) => {
          const url = info.getValue()
          if (!url) return '-'
          try {
            return new URL(url).pathname
          } catch {
            return url
          }
        },
      }),
    ],
    [projectId, t],
  )

  const table = useReactTable({
    data: keywords,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: meta.last_page,
  })

  const handleSearch = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault()
      setSearch(searchInput)
      setPage(1)
    },
    [searchInput],
  )

  const handleEngineChange = useCallback((v: string | null) => {
    setEngine(!v || v === '__all__' ? undefined : v)
    setPage(1)
  }, [])

  const handleDeviceChange = useCallback((v: string | null) => {
    setDevice(!v || v === '__all__' ? undefined : v)
    setPage(1)
  }, [])

  const handleImport = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      const lines = importText
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
      if (lines.length === 0) return
      await importKeywords.mutateAsync({
        keywords: lines,
        engine: importEngine,
        device: importDevice,
        cluster_id: Number(importClusterId),
        region_id: Number(importRegionId),
      })
      setImportText('')
      setImportOpen(false)
    },
    [
      importText,
      importEngine,
      importDevice,
      importClusterId,
      importRegionId,
      importKeywords,
    ],
  )

  const getExportData = useCallback(
    () =>
      keywords.map((k) => ({
        keyword: k.keyword,
        engine: k.engine,
        position: k.latest_position,
        change: k.position_change,
        frequency: k.frequency,
        url: k.our_url,
      })),
    [keywords],
  )

  return (
    <div className="space-y-4">
      <FilterBar>
        <form onSubmit={handleSearch} className="flex gap-2">
          <Input
            placeholder={t('keywords.searchPlaceholder')}
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="w-60"
          />
          <Button type="submit" variant="outline" size="sm">
            {t('keywords.search')}
          </Button>
        </form>

        <Select
          value={engine ?? '__all__'}
          onValueChange={handleEngineChange}
        >
          <SelectTrigger>
            <SelectValue placeholder={t('keywords.engine')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">{t('keywords.allEngines')}</SelectItem>
            <SelectItem value="google">Google</SelectItem>
            <SelectItem value="yandex">Yandex</SelectItem>
          </SelectContent>
        </Select>

        <Select
          value={device ?? '__all__'}
          onValueChange={handleDeviceChange}
        >
          <SelectTrigger>
            <SelectValue placeholder={t('keywords.device')} />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">{t('keywords.allDevices')}</SelectItem>
            <SelectItem value="desktop">Desktop</SelectItem>
            <SelectItem value="mobile">Mobile</SelectItem>
          </SelectContent>
        </Select>

        <DataExportButton
          getData={getExportData}
          filename={`keywords-project-${projectId}`}
        />

        <div className="ml-auto">
          <Dialog open={importOpen} onOpenChange={setImportOpen}>
            <DialogTrigger render={<Button />}>
              {t('keywords.importKeywords')}
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>{t('keywords.importKeywords')}</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleImport} className="space-y-4">
                <div className="space-y-2">
                  <Label>{t('keywords.uploadFile')}</Label>
                  <Input
                    type="file"
                    accept=".csv,.txt"
                    onChange={(e) => {
                      const file = e.target.files?.[0]
                      if (file) {
                        const reader = new FileReader()
                        reader.onload = (ev) => {
                          setImportText(ev.target?.result as string)
                        }
                        reader.readAsText(file)
                      }
                    }}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('keywords.keywordsPerLine')}</Label>
                  <textarea
                    className="w-full min-h-32 rounded-lg border border-input bg-transparent px-3 py-2 text-sm"
                    value={importText}
                    onChange={(e) => setImportText(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('keywords.cluster')}</Label>
                  <Select
                    value={importClusterId}
                    onValueChange={(v: string | null) =>
                      setImportClusterId(v ?? '')
                    }
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t('keywords.selectCluster')} />
                    </SelectTrigger>
                    <SelectContent>
                      {(clusters ?? []).map((c: Cluster) => (
                        <SelectItem key={c.id} value={String(c.id)}>
                          {c.category?.name
                            ? `${c.category.name} / ${c.name}`
                            : c.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t('keywords.region')}</Label>
                  <Select
                    value={importRegionId}
                    onValueChange={(v: string | null) =>
                      setImportRegionId(v ?? '')
                    }
                  >
                    <SelectTrigger>
                      <SelectValue placeholder={t('keywords.selectRegion')} />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(
                        (regions ?? {}) as Record<string, Region[]>,
                      ).flatMap(([eng, list]) =>
                        (list ?? []).map((r) => (
                          <SelectItem key={r.id} value={String(r.id)}>
                            {eng}: {r.name}
                          </SelectItem>
                        )),
                      )}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t('keywords.engine')}</Label>
                  <Select
                    value={importEngine}
                    onValueChange={(v: string | null) =>
                      setImportEngine(v ?? 'google')
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="google">Google</SelectItem>
                      <SelectItem value="yandex">Yandex</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t('keywords.device')}</Label>
                  <Select
                    value={importDevice}
                    onValueChange={(v: string | null) =>
                      setImportDevice(v ?? 'desktop')
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="desktop">Desktop</SelectItem>
                      <SelectItem value="mobile">Mobile</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <DialogFooter>
                  <Button
                    type="submit"
                    disabled={
                      importKeywords.isPending ||
                      !importClusterId ||
                      !importRegionId
                    }
                  >
                    {importKeywords.isPending ? t('keywords.importing') : t('keywords.import')}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </FilterBar>

      {isLoading ? (
        <TableSkeleton rows={10} />
      ) : keywords.length === 0 ? (
        <EmptyState title={t('keywords.noKeywords')} />
      ) : (
        <>
          <Table>
            <TableHeader>
              {table.getHeaderGroups().map((hg) => (
                <TableRow key={hg.id}>
                  {hg.headers.map((header) => (
                    <TableHead key={header.id}>
                      {header.isPlaceholder
                        ? null
                        : flexRender(
                            header.column.columnDef.header,
                            header.getContext(),
                          )}
                    </TableHead>
                  ))}
                </TableRow>
              ))}
            </TableHeader>
            <TableBody>
              {table.getRowModel().rows.map((row) => (
                <TableRow key={row.id}>
                  {row.getVisibleCells().map((cell) => (
                    <TableCell key={cell.id}>
                      {flexRender(
                        cell.column.columnDef.cell,
                        cell.getContext(),
                      )}
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">
              {t('keywords.total')}: {meta.total ?? keywords.length}
            </p>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                disabled={page <= 1}
              >
                {t('keywords.previous')}
              </Button>
              <span className="text-sm flex items-center px-2">
                {t('keywords.page', { current: meta.current_page, last: meta.last_page })}
              </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setPage((p) => p + 1)}
                disabled={page >= meta.last_page}
              >
                {t('keywords.next')}
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
