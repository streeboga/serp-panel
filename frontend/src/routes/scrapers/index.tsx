import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  useScrapers,
  useCreateScraper,
  useUpdateScraper,
  useDeleteScraper,
  useTestScraper,
} from '@/hooks/useScrapers'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
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
import { parseApiError } from '@/lib/api'
import type { Scraper } from '@/types/api'
import { Bot, Plus, Pencil, FlaskConical, Trash2 } from 'lucide-react'

// Credential fields required per scraper type (key + label + secret flag).
const CREDENTIAL_FIELDS: Record<string, Array<{ key: string; label: string; secret?: boolean }>> = {
  xmlriver: [
    { key: 'user', label: 'User ID' },
    { key: 'key', label: 'API Key', secret: true },
  ],
  yandex_xml: [
    { key: 'user', label: 'User (логин)' },
    { key: 'key', label: 'API Key', secret: true },
  ],
  yandex_cloud: [
    { key: 'folder_id', label: 'Folder ID' },
    { key: 'api_key', label: 'API Key (Api-Key)', secret: true },
  ],
  webhook: [{ key: 'webhook_secret', label: 'Webhook Secret', secret: true }],
}

// Suggested base_url per type, prefilled when the type is selected.
const DEFAULT_BASE_URL: Record<string, string> = {
  xmlriver: 'https://xmlriver.com/search/xml',
  yandex_xml: 'https://yandex.ru/search/xml',
  yandex_cloud: 'https://searchapi.api.cloud.yandex.net/v2/web/searchAsync',
  webhook: 'https://api-serp.equity.su/api/v1/webhook',
}

export const Route = createFileRoute('/scrapers/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ScrapersPage,
})

function ScrapersPage() {
  const { t } = useTranslation()
  const { data: scrapersData, isLoading } = useScrapers()
  const createScraper = useCreateScraper()
  const updateScraper = useUpdateScraper()
  const deleteScraper = useDeleteScraper()
  const testScraper = useTestScraper()

  const scrapers: Scraper[] = useMemo(
    () => scrapersData?.data ?? scrapersData ?? [],
    [scrapersData],
  )

  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [type, setType] = useState('xmlriver')
  const [baseUrl, setBaseUrl] = useState('')
  const [engines, setEngines] = useState('google')
  const [rateLimit, setRateLimit] = useState('10')
  const [creds, setCreds] = useState<Record<string, string>>({})

  // Selecting a type prefills its base_url (if blank) and resets credential inputs.
  const handleTypeChange = useCallback((v: string | null) => {
    const next = v ?? 'xmlriver'
    setType(next)
    setBaseUrl((prev) => (prev ? prev : (DEFAULT_BASE_URL[next] ?? '')))
    setCreds({})
  }, [])

  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [editScraper, setEditScraper] = useState<Scraper | null>(null)
  const [editName, setEditName] = useState('')
  const [editType, setEditType] = useState('xml_river')
  const [editBaseUrl, setEditBaseUrl] = useState('')
  const [editEngines, setEditEngines] = useState('google')
  const [editRateLimit, setEditRateLimit] = useState('10')
  const [editIsActive, setEditIsActive] = useState(true)
  const [editCreds, setEditCreds] = useState<Record<string, string>>({})

  const [formError, setFormError] = useState<string | null>(null)
  const [editFormError, setEditFormError] = useState<string | null>(null)

  const [testResults, setTestResults] = useState<
    Record<number, { status: string; message?: string }>
  >({})

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setFormError(null)
      try {
        const credentials = Object.fromEntries(
          Object.entries(creds).filter(([, v]) => v.trim() !== ''),
        )
        await createScraper.mutateAsync({
          name,
          type,
          base_url: baseUrl,
          supported_engines: engines.split(',').map((e) => e.trim()).filter(Boolean),
          ...(Object.keys(credentials).length > 0 ? { credentials } : {}),
          rate_limit: Number(rateLimit),
          is_active: true,
        })
        setName('')
        setBaseUrl('')
        setCreds({})
        setOpen(false)
      } catch (err) {
        setFormError(parseApiError(err))
      }
    },
    [name, type, baseUrl, engines, rateLimit, creds, createScraper],
  )

  const handleTest = useCallback(
    async (id: number) => {
      setTestResults((prev) => ({ ...prev, [id]: { status: 'testing' } }))
      try {
        const result = await testScraper.mutateAsync(id)
        setTestResults((prev) => ({
          ...prev,
          [id]: { status: 'success', message: result.message ?? 'OK' },
        }))
      } catch (err: unknown) {
        const msg = err instanceof Error ? err.message : 'Test failed'
        setTestResults((prev) => ({
          ...prev,
          [id]: { status: 'error', message: msg },
        }))
      }
    },
    [testScraper],
  )

  const openEditDialog = useCallback((scraper: Scraper) => {
    setEditScraper(scraper)
    setEditName(scraper.name)
    setEditType(scraper.type)
    setEditBaseUrl(scraper.base_url)
    setEditEngines((scraper.supported_engines ?? scraper.engines ?? []).join(', '))
    setEditRateLimit(String(scraper.rate_limit ?? 10))
    setEditIsActive(scraper.is_active)
    setEditCreds({})
    setEditDialogOpen(true)
  }, [])

  const handleEditTypeChange = useCallback((v: string | null) => {
    const next = v ?? 'xmlriver'
    setEditType(next)
    setEditCreds({})
  }, [])

  const handleEdit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!editScraper) return
      setEditFormError(null)
      try {
        const credentials = Object.fromEntries(
          Object.entries(editCreds).filter(([, v]) => v.trim() !== ''),
        )
        await updateScraper.mutateAsync({
          id: editScraper.id,
          name: editName,
          type: editType,
          base_url: editBaseUrl,
          supported_engines: editEngines.split(',').map((e) => e.trim()).filter(Boolean),
          // Only send credentials when the user actually entered values (avoids wiping stored secrets).
          ...(Object.keys(credentials).length > 0 ? { credentials } : {}),
          rate_limit: Number(editRateLimit),
          is_active: editIsActive,
        })
        setEditDialogOpen(false)
        setEditScraper(null)
        setEditCreds({})
      } catch (err) {
        setEditFormError(parseApiError(err))
      }
    },
    [editScraper, editName, editType, editBaseUrl, editEngines, editRateLimit, editIsActive, editCreds, updateScraper],
  )

  const handleToggleActive = useCallback(
    async (scraper: Scraper) => {
      await updateScraper.mutateAsync({
        id: scraper.id,
        is_active: !scraper.is_active,
      })
    },
    [updateScraper],
  )

  return (
    <AppLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
              <Bot className="h-5 w-5 text-accent-blue" />
              {t('scrapers.title')}
            </h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Настройка парсеров для сбора поисковой выдачи</p>
          </div>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" />}>
              <Plus className="h-3.5 w-3.5 mr-1" />
              {t('scrapers.addScraper')}
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle className="text-[15px]">{t('scrapers.addScraper')}</DialogTitle>
              </DialogHeader>
              {formError && <p className="text-[12px] text-destructive">{formError}</p>}
              <form onSubmit={handleCreate} className="space-y-3">
                <div className="space-y-1">
                  <Label className="text-[11px]">{t('scrapers.name')}</Label>
                  <Input
                    className="h-8"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-[11px]">{t('scrapers.type')}</Label>
                  <Select value={type} onValueChange={handleTypeChange}>
                    <SelectTrigger className="w-full h-8">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="xmlriver" label="XMLRiver (Яндекс + Google)">XMLRiver (Яндекс + Google)</SelectItem>
                      <SelectItem value="yandex_xml" label="Яндекс XML (прямой)">Яндекс XML (прямой)</SelectItem>
                      <SelectItem value="yandex_cloud" label="Яндекс Облако (Search API)">Яндекс Облако (Search API)</SelectItem>
                      <SelectItem value="webhook" label="Webhook / API (входящий)">Webhook / API (входящий)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <Label className="text-[11px]">{t('scrapers.baseUrl')}</Label>
                  <Input
                    className="h-8"
                    value={baseUrl}
                    onChange={(e) => setBaseUrl(e.target.value)}
                    placeholder="https://xmlriver.com/api"
                    required
                  />
                </div>
                {(CREDENTIAL_FIELDS[type] ?? []).map((field) => (
                  <div className="space-y-1" key={field.key}>
                    <Label className="text-[11px]">{field.label}</Label>
                    <Input
                      className="h-8"
                      type={field.secret ? 'password' : 'text'}
                      autoComplete="off"
                      value={creds[field.key] ?? ''}
                      onChange={(e) =>
                        setCreds((prev) => ({ ...prev, [field.key]: e.target.value }))
                      }
                    />
                  </div>
                ))}
                <div className="space-y-1">
                  <Label className="text-[11px]">{t('scrapers.enginesComma')}</Label>
                  <Input
                    className="h-8"
                    value={engines}
                    onChange={(e) => setEngines(e.target.value)}
                  />
                </div>
                <div className="space-y-1">
                  <Label className="text-[11px]">{t('scrapers.rateLimit')} ({t('scrapers.rateLimitUnit')})</Label>
                  <Input
                    className="h-8"
                    type="number"
                    value={rateLimit}
                    onChange={(e) => setRateLimit(e.target.value)}
                  />
                </div>
                <DialogFooter>
                  <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={createScraper.isPending}>
                    {createScraper.isPending ? t('scrapers.creating') : t('scrapers.create')}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {isLoading ? (
          <TableSkeleton rows={4} />
        ) : scrapers.length === 0 ? (
          <EmptyState title={t('scrapers.noScrapers')} />
        ) : (
          <div className="glass-card rounded-lg overflow-hidden">
            <Table className="compact-table">
              <TableHeader>
                <TableRow>
                  <TableHead className="text-[11px]">{t('scrapers.name')}</TableHead>
                  <TableHead className="text-[11px]">{t('scrapers.type')}</TableHead>
                  <TableHead className="text-[11px]">{t('scrapers.baseUrl')}</TableHead>
                  <TableHead className="text-[11px]">{t('scrapers.engines')}</TableHead>
                  <TableHead className="text-[11px]">{t('scrapers.rateLimit')}</TableHead>
                  <TableHead className="text-[11px]">{t('scrapers.status')}</TableHead>
                  <TableHead className="text-[11px]">{t('scrapers.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {scrapers.map((scraper) => (
                  <TableRow key={scraper.id}>
                    <TableCell className="text-[12px] font-medium">
                      {scraper.name}
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline" className="text-[10px]">{scraper.type}</Badge>
                    </TableCell>
                    <TableCell className="max-w-xs truncate text-[12px]">
                      {scraper.base_url}
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        {(scraper.supported_engines ?? scraper.engines ?? []).map((eng) => (
                          <Badge key={eng} variant="secondary" className="text-[10px]">
                            {eng}
                          </Badge>
                        ))}
                      </div>
                    </TableCell>
                    <TableCell className="text-[12px]">{scraper.rate_limit}/{t('scrapers.rateLimitUnit').split('/')[0] === 'req' ? 'min' : t('scrapers.rateLimitUnit').split('/')[1]}</TableCell>
                    <TableCell>
                      <Badge
                        variant={scraper.is_active ? 'default' : 'secondary'}
                        className="cursor-pointer text-[10px]"
                        onClick={() => handleToggleActive(scraper)}
                      >
                        {scraper.is_active ? t('scrapers.active') : t('scrapers.inactive')}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button
                          variant="outline"
                          size="xs"
                          className="text-[11px] hover:border-accent-blue hover:text-accent-blue"
                          onClick={() => openEditDialog(scraper)}
                        >
                          <Pencil className="h-3 w-3 mr-1" />
                          {t('scrapers.edit', 'Edit')}
                        </Button>
                        <Button
                          variant="outline"
                          size="xs"
                          className="text-[11px] hover:border-accent-blue hover:text-accent-blue"
                          onClick={() => handleTest(scraper.id)}
                          disabled={
                            testResults[scraper.id]?.status === 'testing'
                          }
                        >
                          <FlaskConical className="h-3 w-3 mr-1" />
                          {testResults[scraper.id]?.status === 'testing'
                            ? t('scrapers.testing')
                            : t('scrapers.test')}
                        </Button>
                        <Button
                          variant="outline"
                          size="xs"
                          className="text-[11px] text-destructive hover:text-destructive"
                          onClick={() => deleteScraper.mutate(scraper.id)}
                        >
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      </div>
                      {testResults[scraper.id] &&
                        testResults[scraper.id].status !== 'testing' && (
                          <p
                            className={`text-[11px] mt-1 ${
                              testResults[scraper.id].status === 'success'
                                ? 'text-green-600'
                                : 'text-destructive'
                            }`}
                          >
                            {testResults[scraper.id].message}
                          </p>
                        )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
        <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="text-[15px]">{t('scrapers.editScraper', 'Edit Scraper')}</DialogTitle>
            </DialogHeader>
            {editFormError && <p className="text-[12px] text-destructive">{editFormError}</p>}
            <form onSubmit={handleEdit} className="space-y-3">
              <div className="space-y-1">
                <Label className="text-[11px]">{t('scrapers.name')}</Label>
                <Input
                  className="h-8"
                  value={editName}
                  onChange={(e) => setEditName(e.target.value)}
                  required
                />
              </div>
              <div className="space-y-1">
                <Label className="text-[11px]">{t('scrapers.type')}</Label>
                <Select value={editType} onValueChange={handleEditTypeChange}>
                  <SelectTrigger className="w-full h-8">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="xmlriver" label="XMLRiver (Яндекс + Google)">XMLRiver (Яндекс + Google)</SelectItem>
                    <SelectItem value="yandex_xml" label="Яндекс XML (прямой)">Яндекс XML (прямой)</SelectItem>
                    <SelectItem value="yandex_cloud" label="Яндекс Облако (Search API)">Яндекс Облако (Search API)</SelectItem>
                    <SelectItem value="webhook" label="Webhook / API (входящий)">Webhook / API (входящий)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1">
                <Label className="text-[11px]">{t('scrapers.baseUrl')}</Label>
                <Input
                  className="h-8"
                  value={editBaseUrl}
                  onChange={(e) => setEditBaseUrl(e.target.value)}
                  placeholder="https://xmlriver.com/api"
                  required
                />
              </div>
              {(CREDENTIAL_FIELDS[editType] ?? []).map((field) => (
                <div className="space-y-1" key={field.key}>
                  <Label className="text-[11px]">{field.label}</Label>
                  <Input
                    className="h-8"
                    type={field.secret ? 'password' : 'text'}
                    autoComplete="off"
                    placeholder={field.secret ? '•••••• (оставьте пустым, чтобы не менять)' : ''}
                    value={editCreds[field.key] ?? ''}
                    onChange={(e) =>
                      setEditCreds((prev) => ({ ...prev, [field.key]: e.target.value }))
                    }
                  />
                </div>
              ))}
              <div className="space-y-1">
                <Label className="text-[11px]">{t('scrapers.enginesComma')}</Label>
                <Input
                  className="h-8"
                  value={editEngines}
                  onChange={(e) => setEditEngines(e.target.value)}
                />
              </div>
              <div className="space-y-1">
                <Label className="text-[11px]">{t('scrapers.rateLimit')} ({t('scrapers.rateLimitUnit')})</Label>
                <Input
                  className="h-8"
                  type="number"
                  value={editRateLimit}
                  onChange={(e) => setEditRateLimit(e.target.value)}
                />
              </div>
              <DialogFooter>
                <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={updateScraper.isPending}>
                  {updateScraper.isPending ? t('scrapers.saving', 'Saving...') : t('scrapers.save', 'Save')}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  )
}
