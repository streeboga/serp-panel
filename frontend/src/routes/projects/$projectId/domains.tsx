import { createFileRoute } from '@tanstack/react-router'
import { useState } from 'react'
import { useDomains, useCreateDomain, useUpdateDomain, useDeleteDomain } from '@/hooks/useDomains'
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

export const Route = createFileRoute('/projects/$projectId/domains')({
  component: DomainsPage,
})

interface DomainRow {
  id: number
  name: string
  is_own: boolean
}

const columnHelper = createColumnHelper<DomainRow>()

function DomainsPage() {
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

  const domains: DomainRow[] = data?.data ?? data ?? []

  const columns = [
    columnHelper.accessor('name', {
      header: 'Domain',
      cell: (info) => <span className="font-medium">{info.getValue()}</span>,
    }),
    columnHelper.accessor('is_own', {
      header: 'Type',
      cell: (info) =>
        info.getValue() ? (
          <Badge className="bg-green-500 text-white">Свой</Badge>
        ) : (
          <Badge variant="secondary">Конкурент</Badge>
        ),
    }),
    columnHelper.display({
      id: 'actions',
      header: 'Actions',
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
              Edit
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="text-red-600 hover:text-red-700"
              onClick={() => {
                if (confirm('Delete this domain?')) {
                  deleteDomain.mutate(domain.id)
                }
              }}
            >
              Delete
            </Button>
          </div>
        )
      },
    }),
  ]

  const table = useReactTable({
    data: domains,
    columns,
    getCoreRowModel: getCoreRowModel(),
  })

  const handleAdd = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!addName.trim()) return
    await createDomain.mutateAsync({ name: addName.trim(), is_own: addIsOwn })
    setAddName('')
    setAddIsOwn(false)
    setAddOpen(false)
  }

  const handleEdit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (editId == null) return
    await updateDomain.mutateAsync({ id: editId, name: editName.trim(), is_own: editIsOwn })
    setEditOpen(false)
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold">Domains</h2>
        <Dialog open={addOpen} onOpenChange={setAddOpen}>
          <DialogTrigger render={<Button />}>Add Domain</DialogTrigger>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Add Domain</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleAdd} className="space-y-4">
              <div className="space-y-2">
                <Label>Domain name</Label>
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
                  className="h-4 w-4 rounded border-gray-300"
                />
                <Label htmlFor="add-is-own">Own domain</Label>
              </div>
              <DialogFooter>
                <Button type="submit" disabled={createDomain.isPending}>
                  {createDomain.isPending ? 'Adding...' : 'Add'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>

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
                  No domains found
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
      )}

      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Edit Domain</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleEdit} className="space-y-4">
            <div className="space-y-2">
              <Label>Domain name</Label>
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
                className="h-4 w-4 rounded border-gray-300"
              />
              <Label htmlFor="edit-is-own">Own domain</Label>
            </div>
            <DialogFooter>
              <Button type="submit" disabled={updateDomain.isPending}>
                {updateDomain.isPending ? 'Saving...' : 'Save'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
