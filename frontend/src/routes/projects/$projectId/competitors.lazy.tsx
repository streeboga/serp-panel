import { createLazyFileRoute } from '@tanstack/react-router'
import { useMemo, useCallback, useState, Fragment } from 'react'
import { useTranslation } from 'react-i18next'
import { useCompetitors, useCompetitorPages } from '@/hooks/useCompetitors'
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
import type { Competitor, CompetitorPage } from '@/types/api'

export const Route = createLazyFileRoute('/projects/$projectId/competitors')({
  component: CompetitorsPage,
})

const columnHelper = createColumnHelper<Competitor>()

function CompetitorsPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data, isLoading } = useCompetitors(projectId)
  const [openDomain, setOpenDomain] = useState<string | null>(null)
  const { data: pagesData, isLoading: pagesLoading } = useCompetitorPages(
    projectId,
    openDomain,
  )
  const pages: CompetitorPage[] = pagesData?.data ?? pagesData ?? []

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
              <Fragment key={row.id}>
                <TableRow
                  className={`cursor-pointer ${row.original.is_own ? 'bg-success/5' : ''}`}
                  onClick={() =>
                    setOpenDomain(
                      openDomain === row.original.domain
                        ? null
                        : row.original.domain,
                    )
                  }
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
                {openDomain === row.original.domain && (
                  <TableRow>
                    <TableCell
                      colSpan={row.getVisibleCells().length}
                      className="bg-muted/30 p-0"
                    >
                      {pagesLoading ? (
                        <div className="p-3 text-xs text-muted-foreground">
                          {t('common.loading')}
                        </div>
                      ) : pages.length === 0 ? (
                        <div className="p-3 text-xs text-muted-foreground">
                          {t('competitors.noPages')}
                        </div>
                      ) : (
                        <div className="max-h-80 overflow-auto p-2">
                          <table className="w-full text-xs">
                            <thead className="text-muted-foreground">
                              <tr>
                                <th className="w-12 p-1 text-left">#</th>
                                <th className="p-1 text-left">
                                  {t('competitors.keyword')}
                                </th>
                                <th className="p-1 text-left">
                                  {t('competitors.page')}
                                </th>
                              </tr>
                            </thead>
                            <tbody>
                              {pages.map((p, i) => (
                                <tr key={`${p.url}-${p.keyword_id}-${i}`}>
                                  <td className="p-1 tabular-nums">
                                    {p.position}
                                  </td>
                                  <td className="p-1">{p.keyword}</td>
                                  <td className="p-1">
                                    <a
                                      href={p.url}
                                      target="_blank"
                                      rel="noreferrer"
                                      className="text-primary hover:underline"
                                      onClick={(e) => e.stopPropagation()}
                                    >
                                      {p.url}
                                    </a>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                )}
              </Fragment>
            ))}
          </TableBody>
        </Table>
        </div>
      )}
    </div>
  )
}
