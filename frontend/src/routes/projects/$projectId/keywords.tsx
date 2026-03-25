import { createFileRoute, Link } from '@tanstack/react-router'
import { useState } from 'react'
import { useKeywords, useImportKeywords } from '@/hooks/useKeywords'
import {
  useReactTable, getCoreRowModel, createColumnHelper, flexRender,
} from '@tanstack/react-table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter,
} from '@/components/ui/dialog'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

export const Route = createFileRoute('/projects/$projectId/keywords')({
  component: KeywordsPage,
})

interface KeywordRow {
  id: number
  keyword: string
  engine: string
  device: string
  latest_position: number | null
  position_change: number | null
  frequency: number | null
  our_url: string | null
}

function EngineBadge({ engine }: { engine: string }) {
  return (
    <Badge
      variant={engine === 'yandex' ? 'default' : 'secondary'}
      className={engine === 'yandex' ? 'bg-red-500 text-white' : 'bg-blue-500 text-white'}
    >
      {engine === 'yandex' ? 'Ya' : 'G'}
    </Badge>
  )
}

const columnHelper = createColumnHelper<KeywordRow>()

function KeywordsPage() {
  const { projectId } = Route.useParams()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [engine, setEngine] = useState<string | undefined>(undefined)
  const [device, setDevice] = useState<string | undefined>(undefined)

  const { data, isLoading } = useKeywords({
    projectId, page, per_page: 20, search: search || undefined, engine, device,
  })

  const importKeywords = useImportKeywords(projectId)
  const [importOpen, setImportOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importEngine, setImportEngine] = useState('google')

  const keywords: KeywordRow[] = data?.data ?? []
  const meta = data?.meta ?? { last_page: 1, current_page: 1, total: 0 }

  const columns = [
    columnHelper.accessor('keyword', {
      header: 'Keyword',
      cell: (info) => (
        <Link
          to="/projects/$projectId/keywords/$keywordId"
          params={{ projectId, keywordId: String(info.row.original.id) }}
          className="text-blue-600 hover:underline"
        >
          {info.getValue()}
        </Link>
      ),
    }),
    columnHelper.accessor('engine', {
      header: 'Engine',
      cell: (info) => <EngineBadge engine={info.getValue()} />,
    }),
    columnHelper.accessor('latest_position', {
      header: 'Position',
      cell: (info) => info.getValue() ?? '-',
    }),
    columnHelper.accessor('position_change', {
      header: 'Change',
      cell: (info) => {
        const val = info.getValue()
        if (val == null || val === 0) return '-'
        if (val > 0) return <span className="text-green-600">+{val}</span>
        return <span className="text-red-600">{val}</span>
      },
    }),
    columnHelper.accessor('frequency', {
      header: 'Frequency',
      cell: (info) => info.getValue() ?? '-',
    }),
    columnHelper.accessor('our_url', {
      header: 'Our URL',
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
  ]

  const table = useReactTable({
    data: keywords,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    pageCount: meta.last_page,
  })

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault()
    setSearch(searchInput)
    setPage(1)
  }

  const handleImport = async (e: React.FormEvent) => {
    e.preventDefault()
    const lines = importText.split('\n').map(l => l.trim()).filter(Boolean)
    if (lines.length === 0) return
    await importKeywords.mutateAsync({ keywords: lines, engine: importEngine })
    setImportText('')
    setImportOpen(false)
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-end gap-3">
        <form onSubmit={handleSearch} className="flex gap-2">
          <Input
            placeholder="Search keywords..."
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            className="w-60"
          />
          <Button type="submit" variant="outline" size="sm">Search</Button>
        </form>

        <Select value={engine ?? '__all__'} onValueChange={(v: string | null) => { setEngine(!v || v === '__all__' ? undefined : v); setPage(1) }}>
          <SelectTrigger>
            <SelectValue placeholder="Engine" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">All engines</SelectItem>
            <SelectItem value="google">Google</SelectItem>
            <SelectItem value="yandex">Yandex</SelectItem>
          </SelectContent>
        </Select>

        <Select value={device ?? '__all__'} onValueChange={(v: string | null) => { setDevice(!v || v === '__all__' ? undefined : v); setPage(1) }}>
          <SelectTrigger>
            <SelectValue placeholder="Device" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__all__">All devices</SelectItem>
            <SelectItem value="desktop">Desktop</SelectItem>
            <SelectItem value="mobile">Mobile</SelectItem>
          </SelectContent>
        </Select>

        <div className="ml-auto">
          <Dialog open={importOpen} onOpenChange={setImportOpen}>
            <DialogTrigger render={<Button />}>Import Keywords</DialogTrigger>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Import Keywords</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleImport} className="space-y-4">
                <div className="space-y-2">
                  <Label>Keywords (one per line)</Label>
                  <textarea
                    className="w-full min-h-32 rounded-lg border border-input bg-transparent px-3 py-2 text-sm"
                    value={importText}
                    onChange={(e) => setImportText(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>Engine</Label>
                  <Select value={importEngine} onValueChange={(v: string | null) => setImportEngine(v ?? 'google')}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="google">Google</SelectItem>
                      <SelectItem value="yandex">Yandex</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={importKeywords.isPending}>
                    {importKeywords.isPending ? 'Importing...' : 'Import'}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {isLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : (
        <>
          <Table>
            <TableHeader>
              {table.getHeaderGroups().map((hg) => (
                <TableRow key={hg.id}>
                  {hg.headers.map((header) => (
                    <TableHead key={header.id}>
                      {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
                    </TableHead>
                  ))}
                </TableRow>
              ))}
            </TableHeader>
            <TableBody>
              {table.getRowModel().rows.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={columns.length} className="text-center text-muted-foreground">
                    No keywords found
                  </TableCell>
                </TableRow>
              ) : (
                table.getRowModel().rows.map((row) => (
                  <TableRow key={row.id}>
                    {row.getVisibleCells().map((cell) => (
                      <TableCell key={cell.id}>
                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                      </TableCell>
                    ))}
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>

          <div className="flex items-center justify-between">
            <p className="text-sm text-muted-foreground">
              Total: {meta.total ?? keywords.length}
            </p>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page <= 1}>
                Previous
              </Button>
              <span className="text-sm flex items-center px-2">
                Page {meta.current_page} of {meta.last_page}
              </span>
              <Button variant="outline" size="sm" onClick={() => setPage(p => p + 1)} disabled={page >= meta.last_page}>
                Next
              </Button>
            </div>
          </div>
        </>
      )}
    </div>
  )
}
