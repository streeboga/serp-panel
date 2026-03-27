import { createLazyFileRoute, Link, Outlet, useMatches } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import {
  useDomains,
  useCreateDomain,
  useUpdateDomain,
  useDeleteDomain,
  useIndexDomain,
} from '@/hooks/useDomains'
import { useCompetitors } from '@/hooks/useCompetitors'
import {
  useReactTable,
  getCoreRowModel,
  getSortedRowModel,
  createColumnHelper,
  flexRender,
} from '@tanstack/react-table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
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
import type { Domain, Competitor } from '@/types/api'

export const Route = createLazyFileRoute('/projects/$projectId/domains')({
  component: DomainsPage,
})

const DOMAIN_TYPE_CONFIG: Record<string, { label: string; variant: 'default' | 'secondary' | 'outline' }> = {
  own: { label: 'Свой', variant: 'default' },
  competitor: { label: 'Конкурент', variant: 'secondary' },
  satellite: { label: 'Сателлит', variant: 'outline' },
}

const DOMAIN_TYPE_LABELS: Record<string, string> = {
  own: 'Свой',
  competitor: 'Конкурент',
  satellite: 'Сателлит',
}

interface DomainRow extends Domain {
  top3?: number
  top10?: number
  top100?: number
}

const columnHelper = createColumnHelper<DomainRow>()

function DomainsPage() {
  // If a child route (domain detail) is active, render it instead
  const matches = useMatches()
  const hasChildRoute = matches.some((m) => m.id.includes('domainId'))
  if (hasChildRoute) return <Outlet />

  return <DomainsTable />
}

function DomainsTable() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data, isLoading } = useDomains(projectId)
  const { data: competitorData } = useCompetitors(projectId)
  const createDomain = useCreateDomain(projectId)
  const updateDomain = useUpdateDomain()
  const deleteDomain = useDeleteDomain()
  const indexDomain = useIndexDomain()

  // Add dialog state
  const [addOpen, setAddOpen] = useState(false)
  const [addName, setAddName] = useState('')
  const [addType, setAddType] = useState<string>('competitor')
  const [addParentId, setAddParentId] = useState<string>('')
  const [addTags, setAddTags] = useState('')

  // Edit dialog state
  const [editOpen, setEditOpen] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [editName, setEditName] = useState('')
  const [editType, setEditType] = useState<string>('competitor')
  const [editParentId, setEditParentId] = useState<string>('')
  const [editTags, setEditTags] = useState('')

  // Indexing state
  const [indexingDomainId, setIndexingDomainId] = useState<number | null>(null)

  const domains: Domain[] = useMemo(() => data?.data ?? data ?? [], [data])

  const competitors: Competitor[] = useMemo(() => {
    const raw: Competitor[] = competitorData?.data ?? competitorData ?? []
    return raw
  }, [competitorData])

  // Build a map of competitor stats by domain name
  const competitorStatsMap = useMemo(() => {
    const map = new Map<string, Competitor>()
    for (const c of competitors) {
      map.set(c.domain, c)
    }
    return map
  }, [competitors])

  // Merge domain data with competitor stats
  const domainRows: DomainRow[] = useMemo(() => {
    return domains.map((d) => {
      const stats = competitorStatsMap.get(d.name)
      return {
        ...d,
        top3: stats?.top3,
        top10: stats?.top10,
        top100: undefined,
      }
    })
  }, [domains, competitorStatsMap])

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: t('domains.domain'),
        cell: (info) => (
          <Link
            to="/projects/$projectId/domains/$domainId"
            params={{ projectId, domainId: String(info.row.original.id) }}
            className="text-primary hover:underline font-medium"
          >
            {info.getValue()}
          </Link>
        ),
      }),
      columnHelper.accessor('type', {
        header: t('domains.domainType'),
        cell: (info) => {
          const typeVal = info.getValue() ?? (info.row.original.is_own ? 'own' : 'competitor')
          const cfg = DOMAIN_TYPE_CONFIG[typeVal] ?? DOMAIN_TYPE_CONFIG.competitor
          return (
            <Badge
              variant={cfg.variant}
              className={typeVal === 'own' ? 'bg-green-500 text-white hover:bg-green-600' : undefined}
            >
              {cfg.label}
            </Badge>
          )
        },
      }),
      columnHelper.display({
        id: 'parent',
        header: t('domains.parentDomain'),
        cell: (info) => {
          const d = info.row.original
          if (d.parent?.name) return <span className="text-muted-foreground">{d.parent.name}</span>
          if (d.parent_id) {
            const parent = domains.find((dom) => dom.id === d.parent_id)
            if (parent) return <span className="text-muted-foreground">{parent.name}</span>
          }
          return <span className="text-muted-foreground">—</span>
        },
      }),
      columnHelper.display({
        id: 'tags',
        header: t('domains.tags'),
        cell: (info) => {
          const tags = info.row.original.tags
          if (!tags || tags.length === 0) return <span className="text-muted-foreground">—</span>
          return (
            <div className="flex flex-wrap gap-1">
              {tags.map((tag) => (
                <Badge key={tag.id} variant="outline" className="text-xs">
                  {tag.name}
                </Badge>
              ))}
            </div>
          )
        },
      }),
      columnHelper.accessor('top3', {
        header: 'TOP-3',
        cell: (info) => {
          const v = info.getValue()
          return v != null ? <span className="tabular-nums">{v}</span> : <span className="text-muted-foreground">—</span>
        },
      }),
      columnHelper.accessor('top10', {
        header: 'TOP-10',
        cell: (info) => {
          const v = info.getValue()
          return v != null ? <span className="font-semibold tabular-nums">{v}</span> : <span className="text-muted-foreground">—</span>
        },
      }),
      columnHelper.accessor('indexed_pages_count', {
        header: t('domains.indexCount'),
        cell: (info) => {
          const v = info.getValue()
          return v != null ? <span className="tabular-nums">{v}</span> : <span className="text-muted-foreground">—</span>
        },
      }),
      columnHelper.display({
        id: 'actions',
        header: t('domains.actions'),
        cell: (info) => {
          const domain = info.row.original
          const isIndexing = indexingDomainId === domain.id && indexDomain.isPending
          return (
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={isIndexing}
                onClick={() => {
                  setIndexingDomainId(domain.id)
                  indexDomain.mutate({ domainId: String(domain.id) })
                }}
              >
                {isIndexing ? t('domains.indexing') : t('domains.runIndex')}
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setEditId(domain.id)
                  setEditName(domain.name)
                  setEditType(domain.type ?? (domain.is_own ? 'own' : 'competitor'))
                  setEditParentId(domain.parent_id ? String(domain.parent_id) : '')
                  setEditTags(
                    domain.tags?.map((tag) => tag.name).join(', ') ?? ''
                  )
                  setEditOpen(true)
                }}
              >
                {t('domains.edit')}
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="text-destructive hover:text-destructive"
                onClick={() => {
                  if (confirm(t('domains.deleteDomainConfirm'))) {
                    deleteDomain.mutate(domain.id)
                  }
                }}
              >
                {t('domains.delete')}
              </Button>
            </div>
          )
        },
      }),
    ],
    [deleteDomain, indexDomain, indexingDomainId, domains, t],
  )

  const table = useReactTable({
    data: domainRows,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
  })

  const handleAdd = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!addName.trim()) return
      const tags = addTags
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
      await createDomain.mutateAsync({
        name: addName.trim(),
        type: addType,
        is_own: addType === 'own',
        parent_id: addParentId ? Number(addParentId) : null,
        tags: tags.length > 0 ? tags : undefined,
      })
      setAddName('')
      setAddType('competitor')
      setAddParentId('')
      setAddTags('')
      setAddOpen(false)
    },
    [addName, addType, addParentId, addTags, createDomain],
  )

  const handleEdit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (editId == null) return
      const tags = editTags
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean)
      await updateDomain.mutateAsync({
        id: editId,
        name: editName.trim(),
        type: editType,
        is_own: editType === 'own',
        parent_id: editParentId ? Number(editParentId) : null,
        tags: tags.length > 0 ? tags : undefined,
      })
      setEditOpen(false)
    },
    [editId, editName, editType, editParentId, editTags, updateDomain],
  )

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold tracking-tight">{t('domains.title')}</h2>
        <Dialog open={addOpen} onOpenChange={setAddOpen}>
          <DialogTrigger render={<Button size="sm" />}>{t('domains.addDomain')}</DialogTrigger>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>{t('domains.addDomain')}</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleAdd} className="space-y-4">
              <div className="space-y-2">
                <Label>{t('domains.domainName')}</Label>
                <Input
                  placeholder="example.com"
                  value={addName}
                  onChange={(e) => setAddName(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>{t('domains.domainType')}</Label>
                <Select value={addType} onValueChange={(v) => setAddType(v)}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('domains.domainType')} labels={DOMAIN_TYPE_LABELS} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="own" label={t('domains.own')}>{t('domains.own')}</SelectItem>
                    <SelectItem value="competitor" label={t('domains.competitor')}>{t('domains.competitor')}</SelectItem>
                    <SelectItem value="satellite" label={t('domains.satellite')}>{t('domains.satellite')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('domains.parentDomain')}</Label>
                <Select value={addParentId} onValueChange={(v) => setAddParentId(v)}>
                  <SelectTrigger className="w-full">
                    <SelectValue
                      placeholder={t('domains.noParent')}
                      labels={Object.fromEntries(domains.map((d) => [String(d.id), d.name]))}
                    />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="" label={t('domains.noParent')}>{t('domains.noParent')}</SelectItem>
                    {domains.map((d) => (
                      <SelectItem key={d.id} value={String(d.id)} label={d.name}>
                        {d.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('domains.tags')}</Label>
                <Input
                  placeholder={t('domains.tagsPlaceholder')}
                  value={addTags}
                  onChange={(e) => setAddTags(e.target.value)}
                />
              </div>
              <DialogFooter>
                <Button type="submit" disabled={createDomain.isPending}>
                  {createDomain.isPending ? t('domains.adding') : t('domains.add')}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      {isLoading ? (
        <TableSkeleton />
      ) : domainRows.length === 0 ? (
        <EmptyState title={t('domains.noDomains')} />
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

      {/* Edit dialog */}
      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('domains.editDomain')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleEdit} className="space-y-4">
            <div className="space-y-2">
              <Label>{t('domains.domainName')}</Label>
              <Input
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t('domains.domainType')}</Label>
              <Select value={editType} onValueChange={(v) => setEditType(v)}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('domains.domainType')} labels={DOMAIN_TYPE_LABELS} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="own" label={t('domains.own')}>{t('domains.own')}</SelectItem>
                  <SelectItem value="competitor" label={t('domains.competitor')}>{t('domains.competitor')}</SelectItem>
                  <SelectItem value="satellite" label={t('domains.satellite')}>{t('domains.satellite')}</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t('domains.parentDomain')}</Label>
              <Select value={editParentId} onValueChange={(v) => setEditParentId(v)}>
                <SelectTrigger className="w-full">
                  <SelectValue
                    placeholder={t('domains.noParent')}
                    labels={Object.fromEntries(
                      domains.filter((d) => d.id !== editId).map((d) => [String(d.id), d.name])
                    )}
                  />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="" label={t('domains.noParent')}>{t('domains.noParent')}</SelectItem>
                  {domains
                    .filter((d) => d.id !== editId)
                    .map((d) => (
                      <SelectItem key={d.id} value={String(d.id)} label={d.name}>
                        {d.name}
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t('domains.tags')}</Label>
              <Input
                placeholder={t('domains.tagsPlaceholder')}
                value={editTags}
                onChange={(e) => setEditTags(e.target.value)}
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={updateDomain.isPending}>
                {updateDomain.isPending ? t('domains.saving') : t('domains.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
