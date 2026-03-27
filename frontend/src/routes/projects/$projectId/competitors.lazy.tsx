import { createLazyFileRoute } from '@tanstack/react-router'
import { useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useCompetitors } from '@/hooks/useCompetitors'
import {
  useReactTable,
  getCoreRowModel,
  getSortedRowModel,
  createColumnHelper,
  flexRender,
} from '@tanstack/react-table'
import { Badge } from '@/components/ui/badge'
import { EmptyState } from '@/components/EmptyState'
import { DataExportButton } from '@/components/DataExportButton'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Competitor } from '@/types/api'

export const Route = createLazyFileRoute('/projects/$projectId/competitors')({
  component: CompetitorsPage,
})

const columnHelper = createColumnHelper<Competitor>()

function CompetitorsPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data, isLoading } = useCompetitors(projectId)

  const competitors: Competitor[] = useMemo(() => {
    const raw: Competitor[] = data?.data ?? data ?? []
    return [...raw].sort((a, b) => b.top10 - a.top10)
  }, [data])

  const columns = useMemo(
    () => [
      columnHelper.accessor('domain', {
        header: t('competitors.domain'),
        cell: (info) => (
          <span className="font-medium">{info.getValue()}</span>
        ),
      }),
      columnHelper.accessor('site_type', {
        header: t('competitors.siteType'),
        cell: (info) => {
          const siteType = info.getValue()
          const color = info.row.original.site_type_color
          if (!siteType) return '-'
          return (
            <Badge
              style={
                color ? { backgroundColor: color, color: '#fff' } : undefined
              }
              variant={color ? 'default' : 'secondary'}
            >
              {siteType}
            </Badge>
          )
        },
      }),
      columnHelper.accessor('top3', {
        header: 'TOP-3',
        cell: (info) => (
          <span className="tabular-nums">{info.getValue()}</span>
        ),
      }),
      columnHelper.accessor('top10', {
        header: 'TOP-10',
        cell: (info) => (
          <span className="font-semibold tabular-nums">
            {info.getValue()}
          </span>
        ),
      }),
      columnHelper.accessor('top20', {
        header: 'TOP-20',
        cell: (info) => (
          <span className="tabular-nums">{info.getValue()}</span>
        ),
      }),
      columnHelper.accessor('total_keywords', {
        header: t('competitors.totalKeywords'),
        cell: (info) => (
          <span className="tabular-nums">{info.getValue()}</span>
        ),
      }),
    ],
    [t],
  )

  const table = useReactTable({
    data: competitors,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  })

  const getExportData = useCallback(
    () =>
      competitors.map((c) => ({
        domain: c.domain,
        site_type: c.site_type ?? '',
        top3: c.top3,
        top10: c.top10,
        top20: c.top20,
        total_keywords: c.total_keywords,
        is_own: c.is_own ? 'yes' : 'no',
      })),
    [competitors],
  )

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold tracking-tight">{t('competitors.title')}</h2>
        <DataExportButton
          getData={getExportData}
          filename={`competitors-project-${projectId}`}
        />
      </div>

      {isLoading ? (
        <TableSkeleton rows={8} />
      ) : competitors.length === 0 ? (
        <EmptyState title={t('competitors.noData')} />
      ) : (
        <div className="glass-card rounded-lg overflow-hidden">
        <Table className="compact-table">
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
              <TableRow
                key={row.id}
                className={row.original.is_own ? 'bg-success/5' : undefined}
              >
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
        </div>
      )}
    </div>
  )
}
