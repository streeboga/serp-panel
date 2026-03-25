import { createFileRoute } from '@tanstack/react-router'
import { useMemo } from 'react'
import { useCompetitors } from '@/hooks/useCompetitors'
import {
  useReactTable, getCoreRowModel, getSortedRowModel, createColumnHelper, flexRender,
} from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'

export const Route = createFileRoute('/projects/$projectId/competitors')({
  component: CompetitorsPage,
})

interface CompetitorRow {
  domain: string
  site_type: string | null
  site_type_color: string | null
  is_own: boolean
  top3: number
  top10: number
  top20: number
  total_keywords: number
}

const columnHelper = createColumnHelper<CompetitorRow>()

function CompetitorsPage() {
  const { projectId } = Route.useParams()
  const { data, isLoading } = useCompetitors(projectId)

  const competitors: CompetitorRow[] = useMemo(() => {
    const raw: CompetitorRow[] = data?.data ?? data ?? []
    return [...raw].sort((a, b) => b.top10 - a.top10)
  }, [data])

  const columns = [
    columnHelper.accessor('domain', {
      header: 'Domain',
      cell: (info) => <span className="font-medium">{info.getValue()}</span>,
    }),
    columnHelper.accessor('site_type', {
      header: 'Site Type',
      cell: (info) => {
        const siteType = info.getValue()
        const color = info.row.original.site_type_color
        if (!siteType) return '-'
        return (
          <Badge
            style={color ? { backgroundColor: color, color: '#fff' } : undefined}
            variant={color ? 'default' : 'secondary'}
          >
            {siteType}
          </Badge>
        )
      },
    }),
    columnHelper.accessor('top3', {
      header: 'TOP-3',
      cell: (info) => info.getValue(),
    }),
    columnHelper.accessor('top10', {
      header: 'TOP-10',
      cell: (info) => <span className="font-semibold">{info.getValue()}</span>,
    }),
    columnHelper.accessor('top20', {
      header: 'TOP-20',
      cell: (info) => info.getValue(),
    }),
    columnHelper.accessor('total_keywords', {
      header: 'Total Keywords',
      cell: (info) => info.getValue(),
    }),
  ]

  const table = useReactTable({
    data: competitors,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  })

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-semibold">Competitors</h2>

      {isLoading ? (
        <p className="text-muted-foreground">Loading...</p>
      ) : (
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
                  No competitor data found
                </TableCell>
              </TableRow>
            ) : (
              table.getRowModel().rows.map((row) => (
                <TableRow
                  key={row.id}
                  className={row.original.is_own ? 'bg-green-50' : undefined}
                >
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
      )}
    </div>
  )
}
