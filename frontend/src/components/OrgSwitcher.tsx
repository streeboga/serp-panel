import { useMemo, useState, useCallback } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { Building2, Check, ChevronDown, Plus } from 'lucide-react'
import { useCreateOrganization } from '@/hooks/useOrganization'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function OrgSwitcher() {
  const { user, organizationId, setOrganization } = useAuth()
  const orgs = user?.organizations ?? []
  const [open, setOpen] = useState(false)
  const [createOpen, setCreateOpen] = useState(false)
  const [newOrgName, setNewOrgName] = useState('')
  const createOrg = useCreateOrganization()

  const currentOrg = useMemo(
    () => orgs.find((o) => Number(o.id) === Number(organizationId)),
    [orgs, organizationId],
  )

  const handleSwitch = useCallback(
    (id: number) => {
      setOrganization(id)
      setOpen(false)
      window.location.reload()
    },
    [setOrganization],
  )

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!newOrgName.trim()) return
      try {
        const result = await createOrg.mutateAsync({ name: newOrgName.trim() })
        setCreateOpen(false)
        setNewOrgName('')
        const newId = result?.data?.id ?? result?.id
        if (newId) {
          setOrganization(Number(newId))
          window.location.reload()
        }
      } catch {}
    },
    [newOrgName, createOrg, setOrganization],
  )

  return (
    <>
      <div className="relative">
        <button
          onClick={() => setOpen(!open)}
          className="w-full flex items-center gap-2 text-[11px] px-2 py-1.5 rounded-lg border border-border bg-transparent hover:bg-muted transition-colors"
        >
          <Building2 className="size-3 text-muted-foreground shrink-0" />
          <span className="truncate flex-1 text-left">{currentOrg?.name ?? 'Organization'}</span>
          <ChevronDown className="size-3 text-muted-foreground shrink-0" />
        </button>
        {open && (
          <>
            <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
            <div className="absolute top-full left-0 right-0 mt-1 bg-popover border border-border rounded-lg shadow-lg z-50 py-1">
              {orgs.map((org) => (
                <button
                  key={org.id}
                  onClick={() => handleSwitch(Number(org.id))}
                  className={`w-full flex items-center gap-2 text-left text-[11px] px-2 py-1.5 hover:bg-muted transition-colors ${
                    Number(org.id) === Number(organizationId) ? 'text-accent-blue font-medium' : ''
                  }`}
                >
                  {Number(org.id) === Number(organizationId) ? (
                    <Check className="size-3 text-accent-blue shrink-0" />
                  ) : (
                    <span className="size-3 shrink-0" />
                  )}
                  {org.name}
                </button>
              ))}
              <div className="border-t border-border mt-1 pt-1">
                <button
                  onClick={() => {
                    setOpen(false)
                    setCreateOpen(true)
                  }}
                  className="w-full flex items-center gap-2 text-left text-[11px] px-2 py-1.5 hover:bg-muted transition-colors text-muted-foreground"
                >
                  <Plus className="size-3 shrink-0" />
                  Создать организацию
                </button>
              </div>
            </div>
          </>
        )}
      </div>

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="text-[15px]">Новая организация</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="space-y-1.5">
              <Label className="text-[11px]">Название</Label>
              <Input
                className="h-8"
                value={newOrgName}
                onChange={(e) => setNewOrgName(e.target.value)}
                placeholder="Моя компания"
                required
              />
            </div>
            <DialogFooter>
              <Button
                type="submit"
                size="sm"
                className="bg-[#155dfc] hover:bg-[#1249d6]"
                disabled={createOrg.isPending}
              >
                {createOrg.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  )
}
