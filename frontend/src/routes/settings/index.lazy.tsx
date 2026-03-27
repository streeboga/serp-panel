import { createLazyFileRoute } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { useAuth } from '@/contexts/AuthContext'
import { useTheme } from '@/contexts/ThemeContext'
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
  useDeleteOrganization,
} from '@/hooks/useOrganization'
import { useTokens, useCreateToken, useRevokeToken } from '@/hooks/useTokens'
import { useProjects } from '@/hooks/useProjects'
import { useBillingUsage } from '@/hooks/useBilling'
import { useYandexRedirect } from '@/hooks/useYandex'
import { useAccounts, useCreateAccount, useDeleteAccount, useTestAccount } from '@/hooks/useAccounts'
import type { ConnectedAccount } from '@/hooks/useAccounts'
import { parseApiError } from '@/lib/api'
import type { Member, ApiToken, Project } from '@/types/api'
import { Settings, Building2, User, Palette, CreditCard, Link2, Users, Pencil, Plus, Trash2, FlaskConical, ExternalLink, Key, Copy, Check } from 'lucide-react'

export const Route = createLazyFileRoute('/settings/')({
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
  const { user, organizationId, setOrganization } = useAuth()
  const { theme, setTheme } = useTheme()
  const { data: membersData, isLoading: membersLoading } = useMembers()
  const inviteMember = useInviteMember()
  const removeMember = useRemoveMember()
  const updateRole = useUpdateMemberRole()
  const updateOrganization = useUpdateOrganization()

  const deleteOrganization = useDeleteOrganization()

  const { data: tokensData } = useTokens()
  const tokens: ApiToken[] = useMemo(() => {
    const d = tokensData?.data ?? tokensData
    return Array.isArray(d) ? d : []
  }, [tokensData])
  const createToken = useCreateToken()
  const revokeToken = useRevokeToken()

  const { data: projectsData } = useProjects()
  const projects: Project[] = useMemo(() => {
    const d = projectsData?.data ?? projectsData
    return Array.isArray(d) ? d : []
  }, [projectsData])

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

  const [deleteOrgOpen, setDeleteOrgOpen] = useState(false)

  const [createTokenOpen, setCreateTokenOpen] = useState(false)
  const [tokenName, setTokenName] = useState('')
  const [tokenRole, setTokenRole] = useState('viewer')
  const [tokenProjectId, setTokenProjectId] = useState<string>('')
  const [tokenExpires, setTokenExpires] = useState('never')
  const [createdToken, setCreatedToken] = useState<string | null>(null)
  const [showTokenDialog, setShowTokenDialog] = useState(false)
  const [tokenCopied, setTokenCopied] = useState(false)

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

  const handleDeleteOrg = useCallback(async () => {
    try {
      await deleteOrganization.mutateAsync()
      setDeleteOrgOpen(false)
      // Switch to first remaining org or reload
      const remaining = (user?.organizations ?? []).filter(
        (o) => Number(o.id) !== Number(organizationId),
      )
      if (remaining.length > 0) {
        setOrganization(Number(remaining[0].id))
      }
      window.location.reload()
    } catch {}
  }, [deleteOrganization, user, organizationId, setOrganization])

  const handleCreateToken = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!tokenName.trim()) return
      const expiresMap: Record<string, string | null> = {
        '30d': new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0],
        '90d': new Date(Date.now() + 90 * 86400000).toISOString().split('T')[0],
        '1y': new Date(Date.now() + 365 * 86400000).toISOString().split('T')[0],
        never: null,
      }
      try {
        const result = await createToken.mutateAsync({
          name: tokenName.trim(),
          role: tokenRole,
          project_id: tokenProjectId ? Number(tokenProjectId) : null,
          expires_at: expiresMap[tokenExpires] ?? null,
        })
        const plainText = result?.plain_text_token ?? result?.data?.plain_text_token
        setCreateTokenOpen(false)
        setTokenName('')
        setTokenRole('viewer')
        setTokenProjectId('')
        setTokenExpires('never')
        if (plainText) {
          setCreatedToken(plainText)
          setShowTokenDialog(true)
          setTokenCopied(false)
        }
      } catch {}
    },
    [tokenName, tokenRole, tokenProjectId, tokenExpires, createToken],
  )

  const handleCopyToken = useCallback(() => {
    if (createdToken) {
      navigator.clipboard.writeText(createdToken)
      setTokenCopied(true)
      setTimeout(() => setTokenCopied(false), 2000)
    }
  }, [createdToken])

  const tokenRoleLabel = useCallback((abilities: string[]) => {
    const joined = abilities.join(',')
    if (joined.includes(':write')) return 'manager'
    if (joined.includes(':export')) return 'analyst'
    return 'viewer'
  }, [])

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
      <div className="space-y-5">
        <div>
          <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
            <Settings className="h-5 w-5 text-accent-blue" />
            {t('settings.title')}
          </h1>
          <p className="text-[13px] text-muted-foreground mt-0.5">Управление организацией, аккаунтом и подключениями</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div className="glass-card rounded-lg p-4">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-[13px] font-semibold flex items-center gap-1.5">
                <Building2 className="h-4 w-4 text-accent-blue" />
                {t('settings.organization')}
              </h3>
              {org && (
                <div className="flex gap-1">
                  <Button
                    variant="outline"
                    size="xs"
                    onClick={() => {
                      setEditOrgName(org.name)
                      setEditOrgOpen(true)
                    }}
                  >
                    <Pencil className="h-3 w-3 mr-1" />
                    {t('common.edit')}
                  </Button>
                  {org.role === 'admin' && (
                    <Button
                      variant="outline"
                      size="xs"
                      className="text-[11px] text-destructive hover:text-destructive"
                      onClick={() => setDeleteOrgOpen(true)}
                    >
                      <Trash2 className="h-3 w-3 mr-1" />
                      Удалить
                    </Button>
                  )}
                </div>
              )}
            </div>
            <div className="space-y-2">
              {org ? (
                <>
                  <div>
                    <p className="text-[11px] text-muted-foreground">{t('settings.name')}</p>
                    <p className="text-[12px] font-medium">{org.name}</p>
                  </div>
                  <div>
                    <p className="text-[11px] text-muted-foreground">{t('settings.slug')}</p>
                    <p className="font-mono text-[12px]">{org.slug}</p>
                  </div>
                </>
              ) : (
                <p className="text-[12px] text-muted-foreground">{t('settings.noOrganization')}</p>
              )}
            </div>
          </div>

          <div className="glass-card rounded-lg p-4">
            <h3 className="text-[13px] font-semibold flex items-center gap-1.5 mb-3">
              <User className="h-4 w-4 text-accent-blue" />
              {t('settings.yourAccount')}
            </h3>
            <div className="space-y-2">
              {user ? (
                <>
                  <div>
                    <p className="text-[11px] text-muted-foreground">{t('settings.name')}</p>
                    <p className="text-[12px] font-medium">{user.name}</p>
                  </div>
                  <div>
                    <p className="text-[11px] text-muted-foreground">{t('settings.email')}</p>
                    <p className="text-[12px]">{user.email}</p>
                  </div>
                  {org?.role && (
                    <div>
                      <p className="text-[11px] text-muted-foreground">{t('settings.role')}</p>
                      <Badge variant="outline">{org.role}</Badge>
                    </div>
                  )}
                </>
              ) : (
                <p className="text-[12px] text-muted-foreground">{t('common.loading')}</p>
              )}
            </div>
          </div>
        </div>

        <div className="glass-card rounded-lg p-4">
          <h3 className="text-[13px] font-semibold flex items-center gap-1.5 mb-3">
            <Palette className="h-4 w-4 text-accent-blue" />
            {t('settings.appearance')}
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label className="text-[11px]">{t('settings.language')}</Label>
              <Select
                value={i18n.language?.startsWith('ru') ? 'ru' : 'en'}
                onValueChange={handleLanguageChange}
              >
                <SelectTrigger className="w-full h-8">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="ru" label="Русский">Русский</SelectItem>
                  <SelectItem value="en" label="English">English</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label className="text-[11px]">{t('settings.theme')}</Label>
              <Select
                value={theme}
                onValueChange={handleThemeChange}
              >
                <SelectTrigger className="w-full h-8">
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
        </div>

        <div className="glass-card rounded-lg p-4">
          <h3 className="text-[13px] font-semibold flex items-center gap-1.5 mb-3">
            <CreditCard className="h-4 w-4 text-accent-blue" />
            {t('billing.title')}
          </h3>
          <div className="space-y-3">
            {billing && (
              <>
                <div>
                  <p className="text-[11px] text-muted-foreground">{t('billing.currentTier')}</p>
                  <Badge variant="default" className="mt-1">{billing.tier}</Badge>
                </div>
                {[
                  { label: t('billing.keywords'), used: billing.keywords_used, limit: billing.keywords_limit },
                  { label: t('billing.projects'), used: billing.projects_used, limit: billing.projects_limit },
                  { label: t('billing.scrapers'), used: billing.scrapers_used, limit: billing.scrapers_limit },
                ].map(({ label, used, limit }) => (
                  <div key={label} className="space-y-1">
                    <div className="flex justify-between text-[12px]">
                      <span>{label}</span>
                      <span className="text-muted-foreground">{used} / {limit}</span>
                    </div>
                    <div className="h-1.5 bg-muted rounded-full overflow-hidden">
                      <div
                        className="h-full bg-accent-blue rounded-full transition-all"
                        style={{ width: `${limit > 0 ? Math.min((used / limit) * 100, 100) : 0}%` }}
                      />
                    </div>
                  </div>
                ))}
              </>
            )}
          </div>
        </div>

        <div className="glass-card rounded-lg p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-[13px] font-semibold flex items-center gap-1.5">
              <Link2 className="h-4 w-4 text-accent-blue" />
              Подключения
            </h3>
            <Button size="xs" className="text-[11px] bg-[#155dfc] hover:bg-[#1249d6]" onClick={() => setAddAccountOpen(true)}>
              <Plus className="h-3 w-3 mr-1" />
              Добавить
            </Button>
          </div>
          <div className="space-y-2">
            {accounts.length === 0 ? (
              <p className="text-[12px] text-muted-foreground">Нет подключённых аккаунтов. Добавьте Яндекс или XMLRiver.</p>
            ) : (
              accounts.map((acc) => (
                <div key={acc.id} className="flex items-center justify-between py-2 border-b last:border-0">
                  <div>
                    <div className="flex items-center gap-2">
                      <Badge variant="outline" className="text-[10px]">{acc.type}</Badge>
                      <span className="text-[12px] font-medium">{acc.label}</span>
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
                    <Button variant="ghost" size="xs" className="h-7 text-[11px]" onClick={() => handleTest(acc.id)}>
                      <FlaskConical className="h-3 w-3 mr-1" />
                      {testResults[acc.id] === null ? '...' : testResults[acc.id] === true ? '✓' : testResults[acc.id] === false ? '✗' : 'Тест'}
                    </Button>
                    <Button variant="ghost" size="xs" className="h-7 text-[11px] text-destructive hover:text-destructive" onClick={() => { if (confirm('Удалить аккаунт?')) deleteAccount.mutate(acc.id) }}>
                      <Trash2 className="h-3 w-3" />
                    </Button>
                  </div>
                </div>
              ))
            )}
          </div>
        </div>

        {/* Add account dialog */}
        <Dialog open={addAccountOpen} onOpenChange={setAddAccountOpen}>
          <DialogContent className="sm:max-w-sm">
            <DialogHeader><DialogTitle className="text-[15px]">Добавить аккаунт</DialogTitle></DialogHeader>
            <form onSubmit={handleAddAccount} className="space-y-3">
              <div className="space-y-1">
                <Label className="text-[11px]">Тип</Label>
                <Select value={addAccountType} onValueChange={(v) => setAddAccountType(v ?? 'yandex')}>
                  <SelectTrigger className="w-full h-8"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="yandex" label="Яндекс (Wordstat)">Яндекс (Wordstat)</SelectItem>
                    <SelectItem value="xmlriver" label="XMLRiver (SERP)">XMLRiver (SERP)</SelectItem>
                    <SelectItem value="google" label="Google (скоро)" disabled>Google (скоро)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-[11px]">Название</Label>
                <Input className="h-8" value={addAccountLabel} onChange={(e) => setAddAccountLabel(e.target.value)} placeholder="Мой аккаунт Яндекс" required />
              </div>

              {addAccountType === 'yandex' && (
                <div className="space-y-2">
                  <div className="text-[11px] text-muted-foreground">
                    1. Нажмите кнопку ниже для авторизации в Яндекс<br />
                    2. Скопируйте код подтверждения<br />
                    3. Вставьте код в поле ниже
                  </div>
                  <Button type="button" variant="outline" size="sm" className="w-full text-[11px] hover:border-accent-blue hover:text-accent-blue" onClick={async () => {
                    const data = await yandexRedirect.mutateAsync()
                    if (data?.url) window.open(data.url, '_blank')
                  }}>
                    <ExternalLink className="h-3 w-3 mr-1" />
                    Открыть Яндекс OAuth
                  </Button>
                  <div className="space-y-1">
                    <Label className="text-[11px]">Код подтверждения</Label>
                    <Input className="h-8" value={addAccountCode} onChange={(e) => setAddAccountCode(e.target.value)} placeholder="Вставьте код из Яндекса" required />
                  </div>
                </div>
              )}

              {addAccountType === 'xmlriver' && (
                <div className="space-y-2">
                  <div className="space-y-1">
                    <Label className="text-[11px]">User ID</Label>
                    <Input className="h-8" value={addAccountUser} onChange={(e) => setAddAccountUser(e.target.value)} placeholder="20272" required />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-[11px]">API Key</Label>
                    <Input className="h-8" value={addAccountKey} onChange={(e) => setAddAccountKey(e.target.value)} placeholder="8857dd2a..." required />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-[11px]">Base URL</Label>
                    <Input className="h-8" value={addAccountUrl} onChange={(e) => setAddAccountUrl(e.target.value)} placeholder="http://xmlriver.com/search/xml" />
                  </div>
                </div>
              )}

              <DialogFooter>
                <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={createAccount.isPending}>
                  {createAccount.isPending ? 'Добавление...' : 'Добавить'}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>

        <div className="glass-card rounded-lg p-4">
          <h3 className="text-[13px] font-semibold flex items-center gap-1.5 mb-3">
            <Users className="h-4 w-4 text-accent-blue" />
            {t('settings.members')}
          </h3>
          <div className="space-y-3">
            <form onSubmit={handleInvite} className="flex items-end gap-3">
              <div className="space-y-1">
                <Label className="text-[11px]">{t('settings.email')}</Label>
                <Input
                  type="email"
                  placeholder="user@example.com"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  required
                  className="w-64 h-8"
                />
              </div>
              <div className="space-y-1">
                <Label className="text-[11px]">{t('settings.inviteRole')}</Label>
                <Select
                  value={inviteRole}
                  onValueChange={(v: string | null) =>
                    setInviteRole(v ?? 'viewer')
                  }
                >
                  <SelectTrigger className="w-full h-8">
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
              <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={inviteMember.isPending}>
                {inviteMember.isPending ? t('settings.inviting') : t('settings.invite')}
              </Button>
            </form>
            {inviteFormError && <p className="text-[12px] text-destructive">{inviteFormError}</p>}

            {membersLoading ? (
              <TableSkeleton rows={3} />
            ) : members.length === 0 ? (
              <EmptyState title={t('settings.noMembers')} />
            ) : (
              <div className="rounded-lg border overflow-hidden">
                <Table className="compact-table">
                  <TableHeader>
                    <TableRow>
                      <TableHead className="text-[11px]">{t('settings.name')}</TableHead>
                      <TableHead className="text-[11px]">{t('settings.email')}</TableHead>
                      <TableHead className="text-[11px]">{t('settings.role')}</TableHead>
                      <TableHead className="text-[11px]">{t('common.actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {members.map((member) => (
                      <TableRow key={member.id}>
                        <TableCell className="text-[12px] font-medium">
                          {member.name}
                        </TableCell>
                        <TableCell className="text-[12px]">{member.email}</TableCell>
                        <TableCell>
                          <Badge variant={roleBadgeVariant(member.role)}>
                            {member.role}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            <Button
                              variant="outline"
                              size="xs"
                              className="text-[11px] hover:border-accent-blue hover:text-accent-blue"
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
                              size="xs"
                              className="text-[11px] text-destructive hover:text-destructive"
                              onClick={() => {
                                if (confirm(t('settings.removeConfirm'))) {
                                  removeMember.mutate(member.id)
                                }
                              }}
                            >
                              <Trash2 className="h-3 w-3" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>
        </div>

        {/* API Tokens */}
        <div className="glass-card rounded-lg p-4">
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-[13px] font-semibold flex items-center gap-1.5">
              <Key className="h-4 w-4 text-accent-blue" />
              API Токены
            </h3>
            <Button size="xs" className="text-[11px] bg-[#155dfc] hover:bg-[#1249d6]" onClick={() => setCreateTokenOpen(true)}>
              <Plus className="h-3 w-3 mr-1" />
              Создать токен
            </Button>
          </div>
          {tokens.length === 0 ? (
            <p className="text-[12px] text-muted-foreground">Нет API токенов. Создайте токен для интеграции.</p>
          ) : (
            <div className="rounded-lg border overflow-hidden">
              <Table className="compact-table">
                <TableHeader>
                  <TableRow>
                    <TableHead className="text-[11px]">Название</TableHead>
                    <TableHead className="text-[11px]">Роль</TableHead>
                    <TableHead className="text-[11px]">Последнее использование</TableHead>
                    <TableHead className="text-[11px]">Истекает</TableHead>
                    <TableHead className="text-[11px]">Действия</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tokens.map((token) => (
                    <TableRow key={token.id}>
                      <TableCell className="text-[12px] font-medium">{token.name}</TableCell>
                      <TableCell>
                        <Badge variant="outline">{tokenRoleLabel(token.abilities)}</Badge>
                      </TableCell>
                      <TableCell className="text-[12px] text-muted-foreground">
                        {token.last_used_at
                          ? new Date(token.last_used_at).toLocaleDateString()
                          : 'Никогда'}
                      </TableCell>
                      <TableCell className="text-[12px] text-muted-foreground">
                        {token.expires_at
                          ? new Date(token.expires_at).toLocaleDateString()
                          : 'Бессрочно'}
                      </TableCell>
                      <TableCell>
                        <Button
                          variant="outline"
                          size="xs"
                          className="text-[11px] text-destructive hover:text-destructive"
                          onClick={() => {
                            if (confirm('Отозвать токен? Это действие нельзя отменить.')) {
                              revokeToken.mutate(token.id)
                            }
                          }}
                        >
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </div>
      </div>

      {/* Delete organization dialog */}
      <Dialog open={deleteOrgOpen} onOpenChange={setDeleteOrgOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="text-[15px]">Удалить организацию</DialogTitle>
          </DialogHeader>
          <p className="text-[12px] text-muted-foreground">
            Вы уверены? Организация будет удалена (можно восстановить).
          </p>
          <DialogFooter>
            <Button variant="outline" size="sm" onClick={() => setDeleteOrgOpen(false)}>
              Отмена
            </Button>
            <Button
              variant="destructive"
              size="sm"
              onClick={handleDeleteOrg}
              disabled={deleteOrganization.isPending}
            >
              {deleteOrganization.isPending ? 'Удаление...' : 'Удалить'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Create token dialog */}
      <Dialog open={createTokenOpen} onOpenChange={setCreateTokenOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="text-[15px]">Создать API токен</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCreateToken} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-[11px]">Название</Label>
              <Input
                className="h-8"
                value={tokenName}
                onChange={(e) => setTokenName(e.target.value)}
                placeholder="Мой API токен"
                required
              />
            </div>
            <div className="space-y-1">
              <Label className="text-[11px]">Роль</Label>
              <Select value={tokenRole} onValueChange={(v) => setTokenRole(v ?? 'viewer')}>
                <SelectTrigger className="w-full h-8">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="viewer" label="Viewer">Viewer</SelectItem>
                  <SelectItem value="analyst" label="Analyst">Analyst</SelectItem>
                  <SelectItem value="manager" label="Manager">Manager</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-[11px]">Проект</Label>
              <Select value={tokenProjectId} onValueChange={(v) => setTokenProjectId(v ?? '')}>
                <SelectTrigger className="w-full h-8">
                  <SelectValue placeholder="Все проекты" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="" label="Все проекты">Все проекты</SelectItem>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)} label={p.name}>
                      {p.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-[11px]">Срок действия</Label>
              <Select value={tokenExpires} onValueChange={(v) => setTokenExpires(v ?? 'never')}>
                <SelectTrigger className="w-full h-8">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="30d" label="30 дней">30 дней</SelectItem>
                  <SelectItem value="90d" label="90 дней">90 дней</SelectItem>
                  <SelectItem value="1y" label="1 год">1 год</SelectItem>
                  <SelectItem value="never" label="Бессрочно">Бессрочно</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <DialogFooter>
              <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={createToken.isPending}>
                {createToken.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Token created dialog */}
      <Dialog open={showTokenDialog} onOpenChange={(open) => { setShowTokenDialog(open); if (!open) setCreatedToken(null) }}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="text-[15px]">Токен создан</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="flex items-center gap-2">
              <Input
                className="h-8 font-mono text-[11px] flex-1"
                value={createdToken ?? ''}
                readOnly
              />
              <Button variant="outline" size="xs" className="shrink-0" onClick={handleCopyToken}>
                {tokenCopied ? <Check className="h-3 w-3 text-green-500" /> : <Copy className="h-3 w-3" />}
              </Button>
            </div>
            <p className="text-[11px] text-amber-500 font-medium">
              Сохраните токен — он больше не будет показан
            </p>
          </div>
          <DialogFooter>
            <Button size="sm" onClick={() => { setShowTokenDialog(false); setCreatedToken(null) }}>
              Готово
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={roleDialogOpen} onOpenChange={setRoleDialogOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="text-[15px]">{t('settings.changeRole')}</DialogTitle>
          </DialogHeader>
          {roleFormError && <p className="text-[12px] text-destructive">{roleFormError}</p>}
          <form onSubmit={handleRoleChange} className="space-y-3">
            <div className="space-y-1.5">
              <Label className="text-[11px]">{t('settings.role')}</Label>
              <Select
                value={roleValue}
                onValueChange={(v: string | null) =>
                  setRoleValue(v ?? 'viewer')
                }
              >
                <SelectTrigger className="w-full h-8">
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
              <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={updateRole.isPending}>
                {updateRole.isPending ? t('settings.saving') : t('settings.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={editOrgOpen} onOpenChange={setEditOrgOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle className="text-[15px]">{t('settings.editOrganization')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleEditOrg} className="space-y-3">
            <div className="space-y-1.5">
              <Label className="text-[11px]">{t('settings.name')}</Label>
              <Input
                className="h-8"
                value={editOrgName}
                onChange={(e) => setEditOrgName(e.target.value)}
                required
              />
              {updateOrganization.isError && (
                <p className="text-[12px] text-destructive">
                  {(updateOrganization.error as any)?.response?.data?.message ??
                    (updateOrganization.error as Error)?.message ??
                    t('common.error')}
                </p>
              )}
            </div>
            <DialogFooter>
              <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={updateOrganization.isPending}>
                {updateOrganization.isPending ? t('settings.saving') : t('settings.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
