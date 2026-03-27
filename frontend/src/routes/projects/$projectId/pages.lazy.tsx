import { createLazyFileRoute } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { usePages, useCreatePage, useUpdatePage, useDeletePage, useImportPages } from '@/hooks/usePages'
import { useDomains } from '@/hooks/useDomains'
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
import type { Domain } from '@/types/api'

export const Route = createLazyFileRoute('/projects/$projectId/pages')({
  component: PagesPage,
})

const PAGE_TYPE_LABELS: Record<string, string> = {
  commercial: 'Коммерческая',
  informational: 'Информационная',
  navigational: 'Навигационная',
  transactional: 'Транзакционная',
}

const PAGE_TYPE_BADGE_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
  commercial: 'default',
  informational: 'secondary',
  navigational: 'outline',
  transactional: 'default',
}

function truncateUrl(url: string, maxLen = 60): string {
  return url.length > maxLen ? `${url.slice(0, maxLen)}...` : url
}

function PagesPage() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()
  const { data: pagesData, isLoading } = usePages({ projectId })
  const { data: domainsData } = useDomains(projectId)
  const createPage = useCreatePage(projectId)
  const updatePage = useUpdatePage()
  const deletePage = useDeletePage()
  const importPages = useImportPages(projectId)

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const pages: any[] = useMemo(() => {
    const d = pagesData?.data ?? pagesData
    return Array.isArray(d) ? d : []
  }, [pagesData])

  const domains: Domain[] = useMemo(() => {
    const d = domainsData?.data ?? domainsData
    return Array.isArray(d) ? d : []
  }, [domainsData])

  const domainLabels = useMemo(
    () => Object.fromEntries(domains.map((d) => [String(d.id), d.name])),
    [domains],
  )

  // --- Filter state ---
  const [filterType, setFilterType] = useState('')
  const [searchUrl, setSearchUrl] = useState('')

  const filteredPages = useMemo(() => {
    let result = pages
    if (filterType) {
      result = result.filter((p) => p.page_type === filterType)
    }
    if (searchUrl.trim()) {
      const q = searchUrl.trim().toLowerCase()
      result = result.filter((p) => (p.url ?? '').toLowerCase().includes(q))
    }
    return result
  }, [pages, filterType, searchUrl])

  // --- Create dialog ---
  const [createOpen, setCreateOpen] = useState(false)
  const [createUrl, setCreateUrl] = useState('')
  const [createTitle, setCreateTitle] = useState('')
  const [createPageType, setCreatePageType] = useState('')
  const [createDomainId, setCreateDomainId] = useState('')
  const [createNotes, setCreateNotes] = useState('')
  const [createTags, setCreateTags] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)

  const resetCreateForm = useCallback(() => {
    setCreateUrl('')
    setCreateTitle('')
    setCreatePageType('')
    setCreateDomainId('')
    setCreateNotes('')
    setCreateTags('')
    setCreateError(null)
  }, [])

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setCreateError(null)
      try {
        await createPage.mutateAsync({
          url: createUrl.trim(),
          title: createTitle.trim() || undefined,
          page_type: createPageType || undefined,
          domain_id: createDomainId ? Number(createDomainId) : undefined,
          notes: createNotes.trim() || undefined,
          tags: createTags
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
        })
        resetCreateForm()
        setCreateOpen(false)
      } catch (err) {
        setCreateError(parseApiError(err))
      }
    },
    [createUrl, createTitle, createPageType, createDomainId, createNotes, createTags, createPage, resetCreateForm],
  )

  // --- Edit dialog ---
  const [editOpen, setEditOpen] = useState(false)
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [editPage, setEditPage] = useState<any>(null)
  const [editUrl, setEditUrl] = useState('')
  const [editTitle, setEditTitle] = useState('')
  const [editPageType, setEditPageType] = useState('')
  const [editDomainId, setEditDomainId] = useState('')
  const [editNotes, setEditNotes] = useState('')
  const [editTags, setEditTags] = useState('')
  const [editError, setEditError] = useState<string | null>(null)

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const openEdit = useCallback((page: any) => {
    setEditPage(page)
    setEditUrl(page.url ?? '')
    setEditTitle(page.title ?? '')
    setEditPageType(page.page_type ?? '')
    setEditDomainId(page.domain_id ? String(page.domain_id) : '')
    setEditNotes(page.notes ?? '')
    setEditTags(Array.isArray(page.tags) ? page.tags.map((t: any) => typeof t === 'string' ? t : t.name).join(', ') : '')
    setEditError(null)
    setEditOpen(true)
  }, [])

  const handleEdit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!editPage) return
      setEditError(null)
      try {
        await updatePage.mutateAsync({
          id: editPage.id,
          url: editUrl.trim(),
          title: editTitle.trim() || undefined,
          page_type: editPageType || undefined,
          domain_id: editDomainId ? Number(editDomainId) : undefined,
          notes: editNotes.trim() || undefined,
          tags: editTags
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
        })
        setEditOpen(false)
      } catch (err) {
        setEditError(parseApiError(err))
      }
    },
    [editPage, editUrl, editTitle, editPageType, editDomainId, editNotes, editTags, updatePage],
  )

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const handleDelete = useCallback((page: any) => {
    if (confirm(t('pages.deleteConfirm'))) {
      deletePage.mutate(page.id)
    }
  }, [deletePage, t])

  // --- Import dialog ---
  const [importOpen, setImportOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importDefaultType, setImportDefaultType] = useState('')
  const [importDefaultTags, setImportDefaultTags] = useState('')
  const [importError, setImportError] = useState<string | null>(null)
  const [importResult, setImportResult] = useState<string | null>(null)

  const resetImportForm = useCallback(() => {
    setImportText('')
    setImportDefaultType('')
    setImportDefaultTags('')
    setImportError(null)
    setImportResult(null)
  }, [])

  const handleImport = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setImportError(null)
      setImportResult(null)
      try {
        const lines = importText.trim().split('\n').filter(Boolean)
        const pagesPayload = lines.map((line) => {
          const parts = line.split(';').map((s) => s.trim())
          return {
            url: parts[0],
            cluster: parts[1] || undefined,
            page_type: parts[2] || importDefaultType || undefined,
            tags: importDefaultTags
              ? importDefaultTags.split(',').map((t) => t.trim()).filter(Boolean)
              : undefined,
          }
        })
        const res = await importPages.mutateAsync({ pages: pagesPayload })
        const data = res?.data ?? res
        setImportResult(
          `${t('pages.importSuccess')}: ${data.imported ?? 0} новых, ${data.attached ?? 0} привязок, ${data.total ?? 0} всего`,
        )
        setImportText('')
      } catch (err) {
        setImportError(parseApiError(err))
      }
    },
    [importText, importDefaultType, importDefaultTags, importPages, t],
  )

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold tracking-tight">{t('pages.title')}</h2>
        <div className="flex gap-1.5">
          <Button variant="outline" size="sm" onClick={() => { resetImportForm(); setImportOpen(true) }}>
            {t('pages.importPages')}
          </Button>
          <Button size="sm" onClick={() => { resetCreateForm(); setCreateOpen(true) }}>
            {t('pages.addPage')}
          </Button>
        </div>
      </div>

      {/* Filters */}
      <div className="glass-card rounded-lg p-2.5 flex gap-2.5 items-end">
        <div className="space-y-1">
          <Label className="text-[10px] uppercase tracking-wider text-muted-foreground">{t('pages.pageType')}</Label>
          <Select value={filterType} onValueChange={(v) => setFilterType(v ?? '')}>
            <SelectTrigger className="w-[160px] h-7 text-[11px]">
              <SelectValue placeholder="Все типы" labels={PAGE_TYPE_LABELS} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="" label="Все типы">Все типы</SelectItem>
              {Object.entries(PAGE_TYPE_LABELS).map(([value, label]) => (
                <SelectItem key={value} value={value} label={label}>{label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-1">
          <Label className="text-[10px] uppercase tracking-wider text-muted-foreground">{t('pages.url')}</Label>
          <Input
            placeholder="Поиск по URL..."
            value={searchUrl}
            onChange={(e) => setSearchUrl(e.target.value)}
            className="w-[220px] h-7 text-[11px]"
          />
        </div>
      </div>

      {/* Table */}
      {isLoading ? (
        <TableSkeleton />
      ) : filteredPages.length === 0 ? (
        <EmptyState title={t('pages.noPages')} />
      ) : (
        <div className="glass-card rounded-lg overflow-hidden">
        <Table className="compact-table">
          <TableHeader>
            <TableRow>
              <TableHead>{t('pages.url')}</TableHead>
              <TableHead>{t('pages.domain')}</TableHead>
              <TableHead>{t('pages.pageType')}</TableHead>
              <TableHead>{t('pages.tags')}</TableHead>
              <TableHead>{t('pages.attachments')}</TableHead>
              <TableHead>{t('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {filteredPages.map((page) => (
              <TableRow key={page.id}>
                <TableCell className="text-[12px] font-medium max-w-[300px]" title={page.url}>
                  {truncateUrl(page.url ?? '')}
                </TableCell>
                <TableCell className="text-[12px] text-muted-foreground">
                  {page.domain?.name ?? domainLabels[String(page.domain_id)] ?? '—'}
                </TableCell>
                <TableCell>
                  {page.page_type ? (
                    <Badge variant={PAGE_TYPE_BADGE_VARIANT[page.page_type] ?? 'default'}>
                      {PAGE_TYPE_LABELS[page.page_type] ?? page.page_type}
                    </Badge>
                  ) : (
                    '—'
                  )}
                </TableCell>
                <TableCell>
                  <div className="flex flex-wrap gap-1">
                    {Array.isArray(page.tags) && page.tags.length > 0
                      ? page.tags.map((tag: any) => {
                          const name = typeof tag === 'string' ? tag : tag.name
                          return (
                            <Badge key={name} variant="secondary" className="text-xs">
                              {name}
                            </Badge>
                          )
                        })
                      : '—'}
                  </div>
                </TableCell>
                <TableCell className="text-[12px] text-muted-foreground tabular-nums">
                  {page.keywords_count ?? page.attachments_count ?? 0}
                </TableCell>
                <TableCell>
                  <div className="flex gap-1">
                    <Button
                      variant="outline"
                      size="xs"
                      onClick={() => openEdit(page)}
                    >
                      {t('pages.editPage')}
                    </Button>
                    <Button
                      variant="ghost"
                      size="xs"
                      className="text-destructive hover:text-destructive"
                      onClick={() => handleDelete(page)}
                    >
                      {t('common.delete')}
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
        </div>
      )}

      {/* Create dialog */}
      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('pages.createPage')}</DialogTitle>
          </DialogHeader>
          {createError ? <p className="text-sm text-destructive">{createError}</p> : null}
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.url')}</Label>
              <Input
                placeholder="https://example.com/page"
                value={createUrl}
                onChange={(e) => setCreateUrl(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.title_field')}</Label>
              <Input
                placeholder="Название страницы"
                value={createTitle}
                onChange={(e) => setCreateTitle(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.pageType')}</Label>
              <Select value={createPageType} onValueChange={(v) => setCreatePageType(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Выберите тип" labels={PAGE_TYPE_LABELS} />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(PAGE_TYPE_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value} label={label}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.domain')}</Label>
              <Select value={createDomainId} onValueChange={(v) => setCreateDomainId(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Выберите домен" labels={domainLabels} />
                </SelectTrigger>
                <SelectContent>
                  {domains.map((d) => (
                    <SelectItem key={d.id} value={String(d.id)} label={d.name}>{d.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.notes')}</Label>
              <textarea
                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Заметки..."
                value={createNotes}
                onChange={(e) => setCreateNotes(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.tags')}</Label>
              <Input
                placeholder="тег1, тег2, тег3"
                value={createTags}
                onChange={(e) => setCreateTags(e.target.value)}
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={createPage.isPending || !createUrl.trim()}>
                {createPage.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit dialog */}
      <Dialog open={editOpen} onOpenChange={setEditOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>{t('pages.editPage')}</DialogTitle>
          </DialogHeader>
          {editError ? <p className="text-sm text-destructive">{editError}</p> : null}
          <form onSubmit={handleEdit} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.url')}</Label>
              <Input
                placeholder="https://example.com/page"
                value={editUrl}
                onChange={(e) => setEditUrl(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.title_field')}</Label>
              <Input
                placeholder="Название страницы"
                value={editTitle}
                onChange={(e) => setEditTitle(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.pageType')}</Label>
              <Select value={editPageType} onValueChange={(v) => setEditPageType(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Выберите тип" labels={PAGE_TYPE_LABELS} />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(PAGE_TYPE_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value} label={label}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.domain')}</Label>
              <Select value={editDomainId} onValueChange={(v) => setEditDomainId(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Выберите домен" labels={domainLabels} />
                </SelectTrigger>
                <SelectContent>
                  {domains.map((d) => (
                    <SelectItem key={d.id} value={String(d.id)} label={d.name}>{d.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.notes')}</Label>
              <textarea
                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder="Заметки..."
                value={editNotes}
                onChange={(e) => setEditNotes(e.target.value)}
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.tags')}</Label>
              <Input
                placeholder="тег1, тег2, тег3"
                value={editTags}
                onChange={(e) => setEditTags(e.target.value)}
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={updatePage.isPending || !editUrl.trim()}>
                {updatePage.isPending ? 'Сохранение...' : 'Сохранить'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Import dialog */}
      <Dialog open={importOpen} onOpenChange={setImportOpen}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>{t('pages.importPages')}</DialogTitle>
          </DialogHeader>
          <p className="text-[12px] text-muted-foreground">{t('pages.importPagesDesc')}</p>
          {importError ? <p className="text-sm text-destructive">{importError}</p> : null}
          {importResult ? <p className="text-sm text-green-600">{importResult}</p> : null}
          <form onSubmit={handleImport} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">URL</Label>
              <textarea
                className="flex min-h-[160px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-mono ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                placeholder={"https://example.com/page1\nhttps://example.com/page2;Кластер 1\nhttps://example.com/page3;Кластер 2;commercial"}
                value={importText}
                onChange={(e) => setImportText(e.target.value)}
                required
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.defaultType')}</Label>
              <Select value={importDefaultType} onValueChange={(v) => setImportDefaultType(v ?? '')}>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Не задан" labels={PAGE_TYPE_LABELS} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="" label="Не задан">Не задан</SelectItem>
                  {Object.entries(PAGE_TYPE_LABELS).map(([value, label]) => (
                    <SelectItem key={value} value={value} label={label}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('pages.defaultTags')}</Label>
              <Input
                placeholder="тег1, тег2, тег3"
                value={importDefaultTags}
                onChange={(e) => setImportDefaultTags(e.target.value)}
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={importPages.isPending || !importText.trim()}>
                {importPages.isPending ? 'Импорт...' : t('pages.importButton')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
