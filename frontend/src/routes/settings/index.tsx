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
  useUpdateOrganization,
} from '@/hooks/useOrganization'
import { useBillingUsage } from '@/hooks/useBilling'
import { useYandexRedirect } from '@/hooks/useYandex'
import { useAccounts, useCreateAccount, useDeleteAccount, useTestAccount } from '@/hooks/useAccounts'
import type { ConnectedAccount } from '@/hooks/useAccounts'
import { parseApiError } from '@/lib/api'
import type { Member } from '@/types/api'

export const Route = createFileRoute('/settings/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: SettingsPage,
})

const ROLES = ['admin', 'manager', 'analyst', 'viewer']

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
  const updateOrganization = useUpdateOrganization()

  const { data: billingData } = useBillingUsage()
  const billing = billingData?.data ?? billingData

  const { data: accountsData } = useAccounts()
  const accounts: ConnectedAccount[] = accountsData?.data ?? []
  const createAccount = useCreateAccount()
  const deleteAccount = useDeleteAccount()
  const testAccount = useTestAccount()
  const yandexRedirect = useYandexRedirect()

  const [addAccountOpen, setAddAccountOpen] = useState(false)
  const [addAccountType, setAddAccountType] = useState('yandex')
  const [addAccountLabel, setAddAccountLabel] = useState('')
  const [addAccountCode, setAddAccountCode] = useState('')
  const [addAccountUser, setAddAccountUser] = useState('')
  const [addAccountKey, setAddAccountKey] = useState('')
  const [addAccountUrl, setAddAccountUrl] = useState('http://xmlriver.com/search/xml')
  const [testResults, setTestResults] = useState<Record<number, boolean | null>>({})

  const handleAddAccount = useCallback(async (e: React.FormEvent) => {
    e.preventDefault()
    const creds: Record<string, string> = {}
    if (addAccountType === 'yandex') creds.code = addAccountCode
    if (addAccountType === 'xmlriver') {
      creds.user = addAccountUser
      creds.key = addAccountKey
      creds.base_url = addAccountUrl
    }
    try {
      await createAccount.mutateAsync({ type: addAccountType, label: addAccountLabel, credentials: creds })
      setAddAccountOpen(false)
      setAddAccountLabel('')
      setAddAccountCode('')
      setAddAccountUser('')
      setAddAccountKey('')
    } catch {}
  }, [addAccountType, addAccountLabel, addAccountCode, addAccountUser, addAccountKey, addAccountUrl, createAccount])

  const handleTest = useCallback(async (id: number) => {
    setTestResults((p) => ({ ...p, [id]: null }))
    const res = await testAccount.mutateAsync(id)
    setTestResults((p) => ({ ...p, [id]: res?.ok ?? false }))
  }, [testAccount])

  const org = user?.organizations?.[0]
  const members: Member[] = useMemo(
    () => membersData?.data ?? membersData ?? [],
    [membersData],
  )

  const [inviteFormError, setInviteFormError] = useState<string | null>(null)
  const [roleFormError, setRoleFormError] = useState<string | null>(null)

  const [inviteEmail, setInviteEmail] = useState('')
  const [inviteRole, setInviteRole] = useState('viewer')

  const [editOrgOpen, setEditOrgOpen] = useState(false)
  const [editOrgName, setEditOrgName] = useState('')

  const [roleDialogOpen, setRoleDialogOpen] = useState(false)
  const [roleUserId, setRoleUserId] = useState<number | null>(null)
  const [roleValue, setRoleValue] = useState('member')

  const handleInvite = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!inviteEmail.trim()) return
      setInviteFormError(null)
      try {
        await inviteMember.mutateAsync({
          email: inviteEmail.trim(),
          role: inviteRole,
        })
        setInviteEmail('')
        setInviteRole('viewer')
      } catch (err) {
        setInviteFormError(parseApiError(err))
      }
    },
    [inviteEmail, inviteRole, inviteMember],
  )

  const handleRoleChange = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (roleUserId == null) return
      setRoleFormError(null)
      try {
        await updateRole.mutateAsync({ userId: roleUserId, role: roleValue })
        setRoleDialogOpen(false)
      } catch (err) {
        setRoleFormError(parseApiError(err))
      }
    },
    [roleUserId, roleValue, updateRole],
  )

  const handleEditOrg = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!editOrgName.trim()) return
      await updateOrganization.mutateAsync({ name: editOrgName.trim() })
      setEditOrgOpen(false)
    },
    [editOrgName, updateOrganization],
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
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>{t('settings.organization')}</CardTitle>
              {org && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setEditOrgName(org.name)
                    setEditOrgOpen(true)
                  }}
                >
                  {t('common.edit')}
                </Button>
              )}
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
                  {org?.role && (
                    <div>
                      <p className="text-sm text-muted-foreground">{t('settings.role')}</p>
                      <Badge variant="outline">{org.role}</Badge>
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
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="ru" label="Русский">Русский</SelectItem>
                    <SelectItem value="en" label="English">English</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('settings.theme')}</Label>
                <Select
                  value={theme}
                  onValueChange={handleThemeChange}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="system" label={t('settings.themeSystem')}>{t('settings.themeSystem')}</SelectItem>
                    <SelectItem value="light" label={t('settings.themeLight')}>{t('settings.themeLight')}</SelectItem>
                    <SelectItem value="dark" label={t('settings.themeDark')}>{t('settings.themeDark')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('billing.title')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {billing && (
              <>
                <div>
                  <p className="text-sm text-muted-foreground">{t('billing.currentTier')}</p>
                  <Badge variant="default" className="mt-1">{billing.tier}</Badge>
                </div>
                {[
                  { label: t('billing.keywords'), used: billing.keywords_used, limit: billing.keywords_limit },
                  { label: t('billing.projects'), used: billing.projects_used, limit: billing.projects_limit },
                  { label: t('billing.scrapers'), used: billing.scrapers_used, limit: billing.scrapers_limit },
                ].map(({ label, used, limit }) => (
                  <div key={label} className="space-y-1">
                    <div className="flex justify-between text-sm">
                      <span>{label}</span>
                      <span className="text-muted-foreground">{used} / {limit}</span>
                    </div>
                    <div className="h-2 bg-muted rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary rounded-full transition-all"
                        style={{ width: `${limit > 0 ? Math.min((used / limit) * 100, 100) : 0}%` }}
                      />
                    </div>
                  </div>
                ))}
              </>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <CardTitle>Подключения</CardTitle>
              <Button size="sm" className="text-xs" onClick={() => setAddAccountOpen(true)}>Добавить</Button>
            </div>
          </CardHeader>
          <CardContent className="space-y-3">
            {accounts.length === 0 ? (
              <p className="text-sm text-muted-foreground">Нет подключённых аккаунтов. Добавьте Яндекс или XMLRiver.</p>
            ) : (
              accounts.map((acc) => (
                <div key={acc.id} className="flex items-center justify-between py-2 border-b last:border-0">
                  <div>
                    <div className="flex items-center gap-2">
                      <Badge variant="outline" className="text-[10px]">{acc.type}</Badge>
                      <span className="text-sm font-medium">{acc.label}</span>
                    </div>
                    <p className="text-[11px] text-muted-foreground">
                      {acc.has_credentials ? 'Настроен' : 'Нет данных'}
                      {acc.expires_at && ` • истекает ${new Date(acc.expires_at).toLocaleDateString()}`}
                    </p>
                  </div>
                  <div className="flex items-center gap-1">
                    <Badge variant={acc.is_active ? 'default' : 'outline'} className="text-[10px]">
                      {acc.is_active ? 'Вкл' : 'Выкл'}
                    </Badge>
                    <Button variant="ghost" size="sm" className="h-7 text-[11px]" onClick={() => handleTest(acc.id)}>
                      {testResults[acc.id] === null ? '...' : testResults[acc.id] === true ? '✓' : testResults[acc.id] === false ? '✗' : 'Тест'}
                    </Button>
                    <Button variant="ghost" size="sm" className="h-7 text-[11px] text-destructive" onClick={() => { if (confirm('Удалить аккаунт?')) deleteAccount.mutate(acc.id) }}>
                      ✕
                    </Button>
                  </div>
                </div>
              ))
            )}
          </CardContent>
        </Card>

        {/* Add account dialog */}
        <Dialog open={addAccountOpen} onOpenChange={setAddAccountOpen}>
          <DialogContent className="sm:max-w-sm">
            <DialogHeader><DialogTitle>Добавить аккаунт</DialogTitle></DialogHeader>
            <form onSubmit={handleAddAccount} className="space-y-3">
              <div className="space-y-1">
                <Label className="text-xs">Тип</Label>
                <Select value={addAccountType} onValueChange={(v) => setAddAccountType(v ?? 'yandex')}>
                  <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="yandex" label="Яндекс (Wordstat)">Яндекс (Wordstat)</SelectItem>
                    <SelectItem value="xmlriver" label="XMLRiver (SERP)">XMLRiver (SERP)</SelectItem>
                    <SelectItem value="google" label="Google (скоро)" disabled>Google (скоро)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-xs">Название</Label>
                <Input value={addAccountLabel} onChange={(e) => setAddAccountLabel(e.target.value)} placeholder="Мой аккаунт Яндекс" required />
              </div>

              {addAccountType === 'yandex' && (
                <div className="space-y-2">
                  <div className="text-xs text-muted-foreground">
                    1. Нажмите кнопку ниже для авторизации в Яндекс<br />
                    2. Скопируйте код подтверждения<br />
                    3. Вставьте код в поле ниже
                  </div>
                  <Button type="button" variant="outline" size="sm" className="w-full text-xs" onClick={async () => {
                    const data = await yandexRedirect.mutateAsync()
                    if (data?.url) window.open(data.url, '_blank')
                  }}>
                    Открыть Яндекс OAuth →
                  </Button>
                  <div className="space-y-1">
                    <Label className="text-xs">Код подтверждения</Label>
                    <Input value={addAccountCode} onChange={(e) => setAddAccountCode(e.target.value)} placeholder="Вставьте код из Яндекса" required />
                  </div>
                </div>
              )}

              {addAccountType === 'xmlriver' && (
                <div className="space-y-2">
                  <div className="space-y-1">
                    <Label className="text-xs">User ID</Label>
                    <Input value={addAccountUser} onChange={(e) => setAddAccountUser(e.target.value)} placeholder="20272" required />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-xs">API Key</Label>
                    <Input value={addAccountKey} onChange={(e) => setAddAccountKey(e.target.value)} placeholder="8857dd2a..." required />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-xs">Base URL</Label>
                    <Input value={addAccountUrl} onChange={(e) => setAddAccountUrl(e.target.value)} placeholder="http://xmlriver.com/search/xml" />
                  </div>
                </div>
              )}

              <DialogFooter>
                <Button type="submit" disabled={createAccount.isPending}>
                  {createAccount.isPending ? 'Добавление...' : 'Добавить'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

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
                    setInviteRole(v ?? 'viewer')
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {ROLES.map((r) => (
                      <SelectItem key={r} value={r} label={r.charAt(0).toUpperCase() + r.slice(1)}>
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
            {inviteFormError && <p className="text-sm text-destructive">{inviteFormError}</p>}

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
          {roleFormError && <p className="text-sm text-destructive">{roleFormError}</p>}
          <form onSubmit={handleRoleChange} className="space-y-4">
            <div className="space-y-2">
              <Label>{t('settings.role')}</Label>
              <Select
                value={roleValue}
                onValueChange={(v: string | null) =>
                  setRoleValue(v ?? 'viewer')
                }
              >
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {ROLES.map((r) => (
                    <SelectItem key={r} value={r} label={r.charAt(0).toUpperCase() + r.slice(1)}>
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

      <Dialog open={editOrgOpen} onOpenChange={setEditOrgOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>{t('settings.editOrganization')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleEditOrg} className="space-y-4">
            <div className="space-y-2">
              <Label>{t('settings.name')}</Label>
              <Input
                value={editOrgName}
                onChange={(e) => setEditOrgName(e.target.value)}
                required
              />
              {updateOrganization.isError && (
                <p className="text-sm text-destructive">
                  {(updateOrganization.error as any)?.response?.data?.message ??
                    (updateOrganization.error as Error)?.message ??
                    t('common.error')}
                </p>
              )}
            </div>
            <DialogFooter>
              <Button type="submit" disabled={updateOrganization.isPending}>
                {updateOrganization.isPending ? t('settings.saving') : t('settings.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
