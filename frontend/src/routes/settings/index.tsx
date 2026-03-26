import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { useAuth } from '@/contexts/AuthContext'
import { useTheme } from '@/contexts/ThemeContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import {
  useMembers,
  useInviteMember,
  useRemoveMember,
  useUpdateMemberRole,
} from '@/hooks/useOrganization'
import type { Member } from '@/types/api'

export const Route = createFileRoute('/settings/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: SettingsPage,
})

const ROLES = ['owner', 'admin', 'member', 'viewer']

function roleBadgeVariant(role: string) {
  switch (role) {
    case 'owner':
      return 'default' as const
    case 'admin':
      return 'secondary' as const
    default:
      return 'outline' as const
  }
}

function SettingsPage() {
  const { t, i18n } = useTranslation()
  const { user } = useAuth()
  const { theme, setTheme } = useTheme()
  const { data: membersData, isLoading: membersLoading } = useMembers()
  const inviteMember = useInviteMember()
  const removeMember = useRemoveMember()
  const updateRole = useUpdateMemberRole()

  const org = user?.organizations?.[0]
  const members: Member[] = useMemo(
    () => membersData?.data ?? membersData ?? [],
    [membersData],
  )

  const [inviteEmail, setInviteEmail] = useState('')
  const [inviteRole, setInviteRole] = useState('member')

  const [roleDialogOpen, setRoleDialogOpen] = useState(false)
  const [roleUserId, setRoleUserId] = useState<number | null>(null)
  const [roleValue, setRoleValue] = useState('member')

  const handleInvite = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!inviteEmail.trim()) return
      await inviteMember.mutateAsync({
        email: inviteEmail.trim(),
        role: inviteRole,
      })
      setInviteEmail('')
      setInviteRole('member')
    },
    [inviteEmail, inviteRole, inviteMember],
  )

  const handleRoleChange = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (roleUserId == null) return
      await updateRole.mutateAsync({ userId: roleUserId, role: roleValue })
      setRoleDialogOpen(false)
    },
    [roleUserId, roleValue, updateRole],
  )

  const handleLanguageChange = useCallback(
    (v: string | null) => {
      if (v) {
        i18n.changeLanguage(v)
      }
    },
    [i18n],
  )

  const handleThemeChange = useCallback(
    (v: string | null) => {
      if (v) {
        setTheme(v as 'light' | 'dark' | 'system')
      }
    },
    [setTheme],
  )

  return (
    <AppLayout>
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">{t('settings.title')}</h1>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>{t('settings.organization')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {org ? (
                <>
                  <div>
                    <p className="text-sm text-muted-foreground">{t('settings.name')}</p>
                    <p className="font-medium">{org.name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">{t('settings.slug')}</p>
                    <p className="font-mono text-sm">{org.slug}</p>
                  </div>
                </>
              ) : (
                <p className="text-muted-foreground">{t('settings.noOrganization')}</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>{t('settings.yourAccount')}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {user ? (
                <>
                  <div>
                    <p className="text-sm text-muted-foreground">{t('settings.name')}</p>
                    <p className="font-medium">{user.name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">{t('settings.email')}</p>
                    <p>{user.email}</p>
                  </div>
                  {org && (
                    <div>
                      <p className="text-sm text-muted-foreground">{t('settings.role')}</p>
                      <Badge variant="outline">{org.pivot.role}</Badge>
                    </div>
                  )}
                </>
              ) : (
                <p className="text-muted-foreground">{t('common.loading')}</p>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>{t('settings.appearance')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>{t('settings.language')}</Label>
                <Select
                  value={i18n.language?.startsWith('ru') ? 'ru' : 'en'}
                  onValueChange={handleLanguageChange}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ru">Русский</SelectItem>
                    <SelectItem value="en">English</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('settings.theme')}</Label>
                <Select
                  value={theme}
                  onValueChange={handleThemeChange}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="system">{t('settings.themeSystem')}</SelectItem>
                    <SelectItem value="light">{t('settings.themeLight')}</SelectItem>
                    <SelectItem value="dark">{t('settings.themeDark')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('settings.members')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <form onSubmit={handleInvite} className="flex items-end gap-3">
              <div className="space-y-1">
                <Label>{t('settings.email')}</Label>
                <Input
                  type="email"
                  placeholder="user@example.com"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  required
                  className="w-64"
                />
              </div>
              <div className="space-y-1">
                <Label>{t('settings.inviteRole')}</Label>
                <Select
                  value={inviteRole}
                  onValueChange={(v: string | null) =>
                    setInviteRole(v ?? 'member')
                  }
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {ROLES.map((r) => (
                      <SelectItem key={r} value={r}>
                        {r.charAt(0).toUpperCase() + r.slice(1)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <Button type="submit" disabled={inviteMember.isPending}>
                {inviteMember.isPending ? t('settings.inviting') : t('settings.invite')}
              </Button>
            </form>

            {membersLoading ? (
              <TableSkeleton rows={3} />
            ) : members.length === 0 ? (
              <EmptyState title={t('settings.noMembers')} />
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('settings.name')}</TableHead>
                    <TableHead>{t('settings.email')}</TableHead>
                    <TableHead>{t('settings.role')}</TableHead>
                    <TableHead>{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {members.map((member) => (
                    <TableRow key={member.id}>
                      <TableCell className="font-medium">
                        {member.name}
                      </TableCell>
                      <TableCell>{member.email}</TableCell>
                      <TableCell>
                        <Badge variant={roleBadgeVariant(member.role)}>
                          {member.role}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-2">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              setRoleUserId(member.id)
                              setRoleValue(member.role)
                              setRoleDialogOpen(true)
                            }}
                          >
                            {t('settings.changeRole')}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive hover:text-destructive"
                            onClick={() => {
                              if (confirm(t('settings.removeConfirm'))) {
                                removeMember.mutate(member.id)
                              }
                            }}
                          >
                            {t('settings.remove')}
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}
          </CardContent>
        </Card>
      </div>

      <Dialog open={roleDialogOpen} onOpenChange={setRoleDialogOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>{t('settings.changeRole')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleRoleChange} className="space-y-4">
            <div className="space-y-2">
              <Label>{t('settings.role')}</Label>
              <Select
                value={roleValue}
                onValueChange={(v: string | null) =>
                  setRoleValue(v ?? 'member')
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {ROLES.map((r) => (
                    <SelectItem key={r} value={r}>
                      {r.charAt(0).toUpperCase() + r.slice(1)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <DialogFooter>
              <Button type="submit" disabled={updateRole.isPending}>
                {updateRole.isPending ? t('settings.saving') : t('settings.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
