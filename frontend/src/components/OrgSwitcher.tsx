import { useMemo, useState, useCallback } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { Building2, Check, ChevronDown } from 'lucide-react'

export function OrgSwitcher() {
  const { user, organizationId, setOrganization } = useAuth()
  const orgs = user?.organizations ?? []
  const [open, setOpen] = useState(false)

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

  if (orgs.length <= 1) return null

  return (
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
          <div className="absolute top-full left-0 right-0 mt-1 glass-card rounded-lg shadow-lg z-50 py-1">
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
          </div>
        </>
      )}
    </div>
  )
}
