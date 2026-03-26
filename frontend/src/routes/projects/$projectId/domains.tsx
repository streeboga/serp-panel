import { createFileRoute } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import {
  useDomains,
  useCreateDomain,
  useUpdateDomain,
  useDeleteDomain,
} from '@/hooks/useDomains'
import {
  useReactTable,
  getCoreRowModel,
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
import type { Domain } from '@/types/api'

export const Route = createFileRoute('/projects/$projectId/domains')({
  component: DomainsPage,
})

const columnHelper = createColumnHelper<Domain>()

function DomainsPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data, isLoading } = useDomains(projectId)
  const createDomain = useCreateDomain(projectId)
  const updateDomain = useUpdateDomain()
  const deleteDomain = useDeleteDomain()

  const [addOpen, setAddOpen] = useState(false)
  const [addName, setAddName] = useState('')
  const [addIsOwn, setAddIsOwn] = useState(false)

  const [editOpen, setEditOpen] = useState(false)
  const [editId, setEditId] = useState<number | null>(null)
  const [editName, setEditName] = useState('')
  const [editIsOwn, setEditIsOwn] = useState(false)

  const domains: Domain[] = useMemo(() => data?.data ?? data ?? [], [data])

  const columns = useMemo(
    () => [
      columnHelper.accessor('name', {
        header: t('domains.domain'),
        cell: (info) => (
          <span className="font-medium">{info.getValue()}</span>
        ),
      }),
      columnHelper.accessor('is_own', {
        header: t('domains.type'),
        cell: (info) =>
          info.getValue() ? (
            <Badge className="bg-green-500 text-white">{t('domains.own')}</Badge>
          ) : (
            <Badge variant="secondary">{t('domains.competitor')}</Badge>
          ),
      }),
      columnHelper.display({
        id: 'actions',
        header: t('domains.actions'),
        cell: (info) => {
          const domain = info.row.original
          return (
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setEditId(domain.id)
                  setEditName(domain.name)
                  setEditIsOwn(domain.is_own)
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
    [deleteDomain, t],
  )

  const table = useReactTable({
    data: domains,
    columns,
    getCoreRowModel: getCoreRowModel(),
  })

  const handleAdd = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!addName.trim()) return
      await createDomain.mutateAsync({
        name: addName.trim(),
        is_own: addIsOwn,
      })
      setAddName('')
      setAddIsOwn(false)
      setAddOpen(false)
    },
    [addName, addIsOwn, createDomain],
  )

  const handleEdit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (editId == null) return
      await updateDomain.mutateAsync({
        id: editId,
        name: editName.trim(),
        is_own: editIsOwn,
      })
      setEditOpen(false)
    },
    [editId, editName, editIsOwn, updateDomain],
  )

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">{t('domains.title')}</h2>
        <Dialog open={addOpen} onOpenChange={setAddOpen}>
          <DialogTrigger render={<Button />}>{t('domains.addDomain')}</DialogTrigger>
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
              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="add-is-own"
                  checked={addIsOwn}
                  onChange={(e) => setAddIsOwn(e.target.checked)}
                  className="h-4 w-4 rounded border-input"
                />
                <Label htmlFor="add-is-own">{t('domains.ownDomain')}</Label>
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
      ) : domains.length === 0 ? (
        <EmptyState title={t('domains.noDomains')} />
      ) : (
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
      )}

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
            <div className="flex items-center gap-2">
              <input
                type="checkbox"
                id="edit-is-own"
                checked={editIsOwn}
                onChange={(e) => setEditIsOwn(e.target.checked)}
                className="h-4 w-4 rounded border-input"
              />
              <Label htmlFor="edit-is-own">{t('domains.ownDomain')}</Label>
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
