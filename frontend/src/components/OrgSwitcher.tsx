import { useMemo, useState, useCallback } from 'react'
import { useAuth } from '@/contexts/AuthContext'

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
    <div className="relative mt-1">
      <button
        onClick={() => setOpen(!open)}
        className="w-full text-left text-xs px-2 py-1 rounded border border-input bg-transparent hover:bg-muted transition-colors truncate"
      >
        {currentOrg?.name ?? 'Organization'}
        <span className="float-right">▾</span>
      </button>
      {open && (
        <div className="absolute top-full left-0 right-0 mt-1 bg-popover border rounded-md shadow-md z-50">
          {orgs.map((org) => (
            <button
              key={org.id}
              onClick={() => handleSwitch(Number(org.id))}
              className={`w-full text-left text-xs px-2 py-1.5 hover:bg-muted transition-colors ${
                Number(org.id) === Number(organizationId) ? 'font-semibold bg-muted' : ''
              }`}
            >
              {org.name}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
