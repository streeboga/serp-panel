import { createFileRoute, Link, Outlet, useMatches } from '@tanstack/react-router'
import { useState, useMemo, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useImportKeywords, useDeleteKeywords, useUpdateKeyword, useRegions, useProjectClusters } from '@/hooks/useKeywords'
import { usePositionMatrix } from '@/hooks/usePositionMatrix'
import type { KeywordRow } from '@/hooks/usePositionMatrix'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { parseApiError } from '@/lib/api'
import type { Cluster, Region } from '@/types/api'

export const Route = createFileRoute('/projects/$projectId/keywords')({ component: KeywordsPage })

// ─── Types ───
type SortField = 'keyword' | 'frequency_exact' | 'frequency_phrase' | 'frequency_broad' | 'position'
type SortDir = 'asc' | 'desc'

interface FilterPreset {
  name: string
  engines: string[]
  devices: string[]
  categories: string[]
  clusters: string[]
  regions: string[]
  groupBy: string[]
  days: number
  sortField?: string
  sortDir?: string
}

type GroupByOption = 'category' | 'cluster' | 'engine' | 'device' | 'region'
const GROUP_OPTIONS: { value: GroupByOption; label: string }[] = [
  { value: 'category', label: 'Категория' },
  { value: 'cluster', label: 'Кластер' },
  { value: 'engine', label: 'Поисковик' },
  { value: 'device', label: 'Устройство' },
  { value: 'region', label: 'Регион' },
]

// ─── Group-by multi-select (different from filter — empty = no grouping) ───
function GroupBySelect({ selected, onChange }: {
  selected: Set<GroupByOption>
  onChange: (s: Set<GroupByOption>) => void
}) {
  const [open, setOpen] = useState(false)
  return (
    <div className="relative">
      <button onClick={() => setOpen(!open)} className="flex items-center gap-1 px-2 py-1 text-xs border rounded-md hover:bg-muted transition-colors">
        {selected.size > 0
          ? GROUP_OPTIONS.filter((g) => selected.has(g.value)).map((g) => g.label).join(' + ')
          : 'Группировка'}
        {selected.size > 0 && <Badge variant="secondary" className="ml-1 text-[10px] px-1 py-0">{selected.size}</Badge>}
        <span className="text-[10px]">▾</span>
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute top-full left-0 mt-1 bg-popover border rounded-md shadow-lg z-50 min-w-[160px]">
            {selected.size > 0 && (
              <button className="w-full text-left px-2 py-1.5 text-xs hover:bg-muted border-b text-muted-foreground" onClick={() => { onChange(new Set()); setOpen(false) }}>
                Сбросить
              </button>
            )}
            {GROUP_OPTIONS.map((g) => (
              <label key={g.value} className="flex items-center gap-2 px-2 py-1.5 text-xs hover:bg-muted cursor-pointer">
                <input
                  type="checkbox"
                  checked={selected.has(g.value)}
                  onChange={() => {
                    const next = new Set(selected)
                    if (next.has(g.value)) next.delete(g.value)
                    else next.add(g.value)
                    onChange(next)
                  }}
                  className="rounded"
                />
                {g.label}
              </label>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

// ─── Multi-select filter dropdown ───
// empty Set = no filter (show all). Non-empty = show only selected.
function MultiFilter({ label, options, selected, onChange }: {
  label: string
  options: string[]
  selected: Set<string>
  onChange: (s: Set<string>) => void
}) {
  const [open, setOpen] = useState(false)
  const isFiltering = selected.size > 0
  const isChecked = (opt: string) => !isFiltering || selected.has(opt)

  const toggle = (opt: string) => {
    if (!isFiltering) {
      // Currently showing all → uncheck one = select all EXCEPT this one
      onChange(new Set(options.filter((o) => o !== opt)))
    } else if (selected.has(opt)) {
      // Uncheck: remove from selection
      const next = new Set(selected)
      next.delete(opt)
      // If nothing left, reset to "all"
      onChange(next.size === 0 ? new Set() : next)
    } else {
      // Check: add to selection
      const next = new Set(selected)
      next.add(opt)
      // If all selected, reset to "all"
      onChange(next.size === options.length ? new Set() : next)
    }
  }

  return (
    <div className="relative">
      <button onClick={() => setOpen(!open)} className="flex items-center gap-1 px-2 py-1 text-xs border rounded-md hover:bg-muted transition-colors">
        {label}
        {isFiltering && <Badge variant="secondary" className="ml-1 text-[10px] px-1 py-0">{selected.size}</Badge>}
        <span className="text-[10px]">▾</span>
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute top-full left-0 mt-1 bg-popover border rounded-md shadow-lg z-50 min-w-[150px] max-h-60 overflow-auto">
            <button
              className="w-full flex items-center gap-2 px-2 py-1.5 text-xs hover:bg-muted cursor-pointer border-b"
              onClick={() => onChange(new Set())}
            >
              <input type="checkbox" checked={!isFiltering} readOnly className="rounded" />
              Все
            </button>
            {options.map((opt) => (
              <label key={opt} className="flex items-center gap-2 px-2 py-1.5 text-xs hover:bg-muted cursor-pointer">
                <input
                  type="checkbox"
                  checked={isChecked(opt)}
                  onChange={() => toggle(opt)}
                  className="rounded"
                />
                {opt}
              </label>
            ))}
          </div>
        </>
      )}
    </div>
  )
}

// ─── Position cell ───
function fmtFreq(n: number | null | undefined): string {
  if (n == null) return '—'
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(n % 1_000_000 === 0 ? 0 : 1)}кк`
  if (n >= 1_000) return `${(n / 1_000).toFixed(n % 1_000 === 0 ? 0 : 1)}к`
  return String(n)
}

function PositionCell({ position, delta }: { position: number | null; delta: number | null }) {
  if (position === null) return <span className="text-muted-foreground text-[11px]">—</span>
  return (
    <div className="text-center leading-tight">
      <span className="font-medium tabular-nums text-xs">{position}</span>
      {delta !== null && delta !== 0 && (
        <div className={`text-[10px] tabular-nums ${delta > 0 ? 'text-green-600' : 'text-red-500'}`}>
          {delta > 0 ? `+${delta}` : delta}
        </div>
      )}
    </div>
  )
}

// ─── Preset storage ───
const PRESETS_KEY = 'serp-filter-presets'
function loadPresets(): FilterPreset[] {
  try { return JSON.parse(localStorage.getItem(PRESETS_KEY) ?? '[]') } catch { return [] }
}
function savePresets(presets: FilterPreset[]) {
  localStorage.setItem(PRESETS_KEY, JSON.stringify(presets))
}

// ─── Main ───
function KeywordsPage() {
  // If a child route (keyword detail) is active, render it instead
  const matches = useMatches()
  const hasChildRoute = matches.some((m) => m.id.includes('keywordId'))
  if (hasChildRoute) return <Outlet />

  return <KeywordsTable />
}

function KeywordsTable() {
  const { t } = useTranslation()
  const { projectId } = Route.useParams()

  // ── State ──
  const [days, setDays] = useState(14)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [filterEngines, setFilterEngines] = useState<Set<string>>(new Set())
  const [filterDevices, setFilterDevices] = useState<Set<string>>(new Set())
  const [filterCategories, setFilterCategories] = useState<Set<string>>(new Set())
  const [filterClusters, setFilterClusters] = useState<Set<string>>(new Set())
  const [filterRegions, setFilterRegions] = useState<Set<string>>(new Set())
  const [groupByFields, setGroupByFields] = useState<Set<GroupByOption>>(new Set())
  const [sortField, setSortField] = useState<SortField | ''>('')
  const [sortDir, setSortDir] = useState<SortDir>('asc')
  const [presets, setPresets] = useState<FilterPreset[]>(loadPresets)
  const [presetName, setPresetName] = useState('')
  const [presetDialogOpen, setPresetDialogOpen] = useState(false)

  // ── Data ──
  const { data: matrixData, isLoading } = usePositionMatrix(projectId, days)
  const dates = matrixData?.dates ?? []
  const allKeywords: KeywordRow[] = matrixData?.data ?? []

  // ── Unique values for filters ──
  const uniqueEngines = useMemo(() => [...new Set(allKeywords.map((k) => k.engine))].sort(), [allKeywords])
  const uniqueDevices = useMemo(() => [...new Set(allKeywords.map((k) => k.device))].sort(), [allKeywords])
  const uniqueCategories = useMemo(() => [...new Set(allKeywords.map((k) => k.category).filter(Boolean) as string[])].sort(), [allKeywords])
  const uniqueClusters = useMemo(() => [...new Set(allKeywords.map((k) => k.cluster).filter(Boolean) as string[])].sort(), [allKeywords])
  const uniqueRegions = useMemo(() => [...new Set(allKeywords.map((k) => k.region).filter(Boolean) as string[])].sort(), [allKeywords])

  // ── Filter ──
  const filtered = useMemo(() => {
    let kws = allKeywords
    if (search) { const q = search.toLowerCase(); kws = kws.filter((k) => k.keyword.toLowerCase().includes(q)) }
    if (filterEngines.size > 0) kws = kws.filter((k) => filterEngines.has(k.engine))
    if (filterDevices.size > 0) kws = kws.filter((k) => filterDevices.has(k.device))
    if (filterCategories.size > 0) kws = kws.filter((k) => k.category && filterCategories.has(k.category))
    if (filterClusters.size > 0) kws = kws.filter((k) => k.cluster && filterClusters.has(k.cluster))
    if (filterRegions.size > 0) kws = kws.filter((k) => k.region && filterRegions.has(k.region))
    return kws
  }, [allKeywords, search, filterEngines, filterDevices, filterCategories, filterClusters, filterRegions])

  // ── Group + merge engines ──
  type MergedRow = { keyword: string; keyword_id: number; frequency: number | null; frequency_exact: number | null; frequency_broad: number | null; frequency_phrase: number | null; our_url: string | null; cluster: string | null; category: string | null; region: string | null; engines: Record<string, Record<string, { position: number | null; delta: number | null }>> }

  const mergedRows = useMemo(() => {
    const map = new Map<string, MergedRow>()
    for (const kw of filtered) {
      const key = kw.keyword
      if (!map.has(key)) {
        map.set(key, { keyword: kw.keyword, keyword_id: kw.keyword_id, frequency: kw.frequency, frequency_exact: kw.frequency_exact, frequency_broad: kw.frequency_broad, frequency_phrase: kw.frequency_phrase, our_url: kw.our_url, cluster: kw.cluster, category: kw.category, region: kw.region, engines: {} })
      }
      const e = map.get(key)!
      if (!e.our_url && kw.our_url) e.our_url = kw.our_url
      if (!e.frequency && kw.frequency) e.frequency = kw.frequency
      e.engines[kw.engine] = kw.positions
    }
    return Array.from(map.values())
  }, [filtered])

  const visibleEngines = useMemo(() => {
    const s = new Set<string>()
    for (const kw of filtered) s.add(kw.engine)
    return Array.from(s).sort()
  }, [filtered])

  // ── Sorting ──
  const toggleSort = useCallback((field: SortField) => {
    if (sortField === field) setSortDir((d) => d === 'asc' ? 'desc' : 'asc')
    else { setSortField(field); setSortDir(field === 'keyword' ? 'asc' : 'desc') }
  }, [sortField])

  const sortedRows = useMemo(() => {
    if (!sortField) return mergedRows
    const sorted = [...mergedRows]
    const latestDate = dates[0]
    sorted.sort((a, b) => {
      let va: number | string | null = null
      let vb: number | string | null = null
      if (sortField === 'keyword') { va = a.keyword; vb = b.keyword }
      else if (sortField === 'frequency_exact') { va = a.frequency_exact; vb = b.frequency_exact }
      else if (sortField === 'frequency_phrase') { va = a.frequency_phrase; vb = b.frequency_phrase }
      else if (sortField === 'frequency_broad') { va = a.frequency_broad; vb = b.frequency_broad }
      else if (sortField === 'position' && latestDate) {
        // Sort by best position on latest date across all engines
        const posA = Math.min(...Object.values(a.engines).map((e) => e[latestDate]?.position ?? 999))
        const posB = Math.min(...Object.values(b.engines).map((e) => e[latestDate]?.position ?? 999))
        va = posA; vb = posB
      }
      if (va == null && vb == null) return 0
      if (va == null) return 1
      if (vb == null) return -1
      if (typeof va === 'string' && typeof vb === 'string') return sortDir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va)
      return sortDir === 'asc' ? (va as number) - (vb as number) : (vb as number) - (va as number)
    })
    return sorted
  }, [mergedRows, sortField, sortDir, dates])

  const sortIcon = (field: SortField) => {
    if (sortField !== field) return <span className="text-[9px] opacity-30 ml-0.5">↕</span>
    return <span className="text-[9px] ml-0.5">{sortDir === 'asc' ? '↑' : '↓'}</span>
  }

  // ── Grouping (multi-field) ──
  const groups = useMemo(() => {
    if (groupByFields.size === 0) return [{ label: '', rows: sortedRows }]
    const fields = [...groupByFields]
    const getFieldValue = (row: MergedRow, field: GroupByOption): string => {
      if (field === 'category') return row.category ?? '—'
      if (field === 'cluster') return row.cluster ?? '—'
      if (field === 'region') return row.region ?? '—'
      if (field === 'engine') return Object.keys(row.engines).join('+')
      if (field === 'device') return 'all'
      return '—'
    }
    const map = new Map<string, MergedRow[]>()
    for (const row of sortedRows) {
      const key = fields.map((f) => getFieldValue(row, f)).join(' › ')
      if (!map.has(key)) map.set(key, [])
      map.get(key)!.push(row)
    }
    return Array.from(map.entries()).map(([label, rows]) => ({ label, rows })).sort((a, b) => a.label.localeCompare(b.label))
  }, [sortedRows, groupByFields])

  const formatDate = (d: string) => new Date(d).toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' })

  // ── Presets ──
  const handleSavePreset = useCallback(() => {
    if (!presetName.trim()) return
    const p: FilterPreset = {
      name: presetName.trim(),
      engines: [...filterEngines],
      devices: [...filterDevices],
      categories: [...filterCategories],
      clusters: [...filterClusters],
      regions: [...filterRegions],
      groupBy: [...groupByFields],
      days,
      sortField: sortField || undefined,
      sortDir,
    }
    const updated = [...presets.filter((x) => x.name !== p.name), p]
    setPresets(updated)
    savePresets(updated)
    setPresetName('')
    setPresetDialogOpen(false)
  }, [presetName, filterEngines, filterDevices, filterCategories, filterClusters, filterRegions, groupByFields, days, sortField, sortDir, presets])

  const handleLoadPreset = useCallback((name: string) => {
    const p = presets.find((x) => x.name === name)
    if (!p) return
    setFilterEngines(new Set(p.engines))
    setFilterDevices(new Set(p.devices))
    setFilterCategories(new Set(p.categories))
    setFilterClusters(new Set(p.clusters))
    setFilterRegions(new Set(p.regions))
    setGroupByFields(new Set(Array.isArray(p.groupBy) ? p.groupBy as GroupByOption[] : p.groupBy ? [p.groupBy as GroupByOption] : []))
    setSortField((p.sortField as SortField) || '')
    setSortDir((p.sortDir as SortDir) || 'asc')
    setDays(p.days)
  }, [presets])

  const handleDeletePreset = useCallback((name: string) => {
    const updated = presets.filter((x) => x.name !== name)
    setPresets(updated)
    savePresets(updated)
  }, [presets])

  // ── Import ──
  const importKeywords = useImportKeywords()
  const deleteKeywords = useDeleteKeywords()
  const updateKeyword = useUpdateKeyword()
  const [bulkMoveOpen, setBulkMoveOpen] = useState(false)
  const [bulkMoveClusterId, setBulkMoveClusterId] = useState('')
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [importOpen, setImportOpen] = useState(false)
  const [importText, setImportText] = useState('')
  const [importEngines, setImportEngines] = useState<Set<string>>(new Set(['google', 'yandex']))
  const [importDevices, setImportDevices] = useState<Set<string>>(new Set(['desktop']))
  const [importClusterId, setImportClusterId] = useState('')
  const [importRegionIds, setImportRegionIds] = useState<Set<number>>(new Set())
  const [importFormError, setImportFormError] = useState<string | null>(null)

  const { data: clustersRaw } = useProjectClusters(projectId)
  const { data: regionsRaw } = useRegions()
  const clusters: Cluster[] = useMemo(() => { const d = clustersRaw?.data ?? clustersRaw; return Array.isArray(d) ? d : [] }, [clustersRaw])
  const regionsList: Region[] = useMemo(() => { const d = regionsRaw?.data ?? regionsRaw; if (Array.isArray(d)) return d; if (d && typeof d === 'object') return Object.values(d).flat() as Region[]; return [] }, [regionsRaw])

  const handleSearch = useCallback((e: React.FormEvent) => { e.preventDefault(); setSearch(searchInput) }, [searchInput])

  const toggleImportRegion = useCallback((id: number) => {
    setImportRegionIds((prev) => { const n = new Set(prev); if (n.has(id)) n.delete(id); else n.add(id); return n })
  }, [])

  const handleImport = useCallback(async (e: React.FormEvent) => {
    e.preventDefault(); setImportFormError(null)
    const rawLines = importText.split('\n').map((l) => l.trim()).filter(Boolean)
    if (!rawLines.length) return

    // Parse CSV format: keyword;cluster
    const parsedLines = rawLines.map((line) => {
      if (line.includes(';')) {
        const [kw, clusterName] = line.split(';').map((s) => s.trim())
        const matchedCluster = clusters.find((c) => c.name === clusterName)
        return { keyword: kw, cluster_id: matchedCluster ? matchedCluster.id : Number(importClusterId) }
      }
      return { keyword: line, cluster_id: Number(importClusterId) }
    })

    // Group by cluster_id
    const byCluster = new Map<number, string[]>()
    for (const p of parsedLines) {
      if (!byCluster.has(p.cluster_id)) byCluster.set(p.cluster_id, [])
      byCluster.get(p.cluster_id)!.push(p.keyword)
    }

    try {
      for (const eng of importEngines)
        for (const dev of importDevices)
          for (const regionId of importRegionIds)
            for (const [clusterId, keywords] of byCluster)
              await importKeywords.mutateAsync({ keywords, engine: eng, device: dev, cluster_id: clusterId, region_id: regionId })
      setImportText(''); setImportOpen(false)
    } catch (err) { setImportFormError(parseApiError(err)) }
  }, [importText, importEngines, importDevices, importClusterId, importRegionIds, importKeywords, clusters])

  const handleBulkDelete = useCallback(async () => {
    if (!selectedIds.size || !confirm(`Удалить ${selectedIds.size} ключевых слов?`)) return
    await deleteKeywords.mutateAsync([...selectedIds]); setSelectedIds(new Set())
  }, [selectedIds, deleteKeywords])

  const handleBulkMove = useCallback(async () => {
    if (!selectedIds.size || !bulkMoveClusterId) return
    for (const id of selectedIds) {
      await updateKeyword.mutateAsync({ id, cluster_id: Number(bulkMoveClusterId) })
    }
    setSelectedIds(new Set())
    setBulkMoveOpen(false)
    setBulkMoveClusterId('')
  }, [selectedIds, bulkMoveClusterId, updateKeyword])

  // ── Render ──
  const totalCols = 5 + dates.length * visibleEngines.length // checkbox + keyword + 3 freq cols + date*engine cells

  return (
    <div className="space-y-3">
      {/* ── Filter bar ── */}
      <div className="flex flex-wrap items-center gap-2">
        <form onSubmit={handleSearch} className="flex gap-1">
          <Input placeholder={t('keywords.searchPlaceholder')} value={searchInput} onChange={(e) => setSearchInput(e.target.value)} className="w-48 h-8 text-xs" />
          <Button type="submit" variant="outline" size="sm" className="h-8 text-xs">{t('keywords.search')}</Button>
        </form>

        {uniqueEngines.length > 1 && <MultiFilter label="Поисковик" options={uniqueEngines} selected={filterEngines} onChange={setFilterEngines} />}
        {uniqueDevices.length > 1 && <MultiFilter label="Устройство" options={uniqueDevices} selected={filterDevices} onChange={setFilterDevices} />}
        {uniqueCategories.length > 1 && <MultiFilter label="Категория" options={uniqueCategories} selected={filterCategories} onChange={setFilterCategories} />}
        {uniqueClusters.length > 1 && <MultiFilter label="Кластер" options={uniqueClusters} selected={filterClusters} onChange={setFilterClusters} />}
        {uniqueRegions.length > 1 && <MultiFilter label="Регион" options={uniqueRegions} selected={filterRegions} onChange={setFilterRegions} />}

        <Select value={String(days)} onValueChange={(v) => setDays(Number(v ?? 14))}>
          <SelectTrigger className="w-20 h-8 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="7" label="7d">7d</SelectItem>
            <SelectItem value="14" label="14d">14d</SelectItem>
            <SelectItem value="30" label="30d">30d</SelectItem>
          </SelectContent>
        </Select>

        <GroupBySelect selected={groupByFields} onChange={setGroupByFields} />

        {/* Presets */}
        {presets.length > 0 && (
          <Select value="__load__" onValueChange={(v) => { if (v && v !== '__load__') handleLoadPreset(v) }}>
            <SelectTrigger className="w-28 h-8 text-xs"><SelectValue placeholder="Пресеты" /></SelectTrigger>
            <SelectContent>
              {presets.map((p) => (
                <SelectItem key={p.name} value={p.name} label={p.name}>
                  {p.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={() => setPresetDialogOpen(true)}>
          Сохранить пресет
        </Button>

        {selectedIds.size > 0 && (
          <div className="flex items-center gap-1 border-l pl-2 ml-1">
            <span className="text-xs text-muted-foreground">Выбрано: {selectedIds.size}</span>
            <Button variant="outline" size="sm" className="h-7 text-[11px]" onClick={() => setBulkMoveOpen(true)}>
              Переместить
            </Button>
            <Button variant="outline" size="sm" className="h-7 text-[11px] text-destructive" onClick={handleBulkDelete} disabled={deleteKeywords.isPending}>
              Удалить
            </Button>
          </div>
        )}

        <div className="ml-auto">
          <Dialog open={importOpen} onOpenChange={setImportOpen}>
            <DialogTrigger render={<Button className="h-8 text-xs" />}>{t('keywords.importKeywords')}</DialogTrigger>
            <DialogContent className="sm:max-w-md">
              <DialogHeader><DialogTitle>{t('keywords.importKeywords')}</DialogTitle></DialogHeader>
              {importFormError && <p className="text-sm text-destructive">{importFormError}</p>}
              <form onSubmit={handleImport} className="space-y-3">
                <div className="space-y-1">
                  <Label className="text-xs">{t('keywords.keywordsPerLine')}</Label>
                  <p className="text-[11px] text-muted-foreground whitespace-pre-line">{'Формат: один ключ на строку ИЛИ CSV: ключ;кластер\nПример CSV:\nкупить смартфон;Смартфоны\nноутбук для работы;Ноутбуки'}</p>
                  <textarea className="w-full min-h-24 rounded-lg border border-input bg-transparent px-3 py-2 text-sm" value={importText} onChange={(e) => setImportText(e.target.value)} required placeholder={"купить смартфон\nноутбук для работы\nили CSV:\nкупить смартфон;Смартфоны"} />
                </div>
                <div className="space-y-1">
                  <Label className="text-xs">{t('keywords.cluster')}</Label>
                  <Select value={importClusterId} onValueChange={(v) => setImportClusterId(v ?? '')}><SelectTrigger className="w-full"><SelectValue placeholder={t('keywords.selectCluster')} /></SelectTrigger><SelectContent>{clusters.map((c) => (<SelectItem key={c.id} value={String(c.id)} label={c.category?.name ? `${c.category.name} / ${c.name}` : c.name}>{c.category?.name ? `${c.category.name} / ${c.name}` : c.name}</SelectItem>))}</SelectContent></Select>
                </div>
                <div className="space-y-1">
                  <Label className="text-xs">{t('keywords.region')}</Label>
                  <div className="max-h-32 overflow-auto border rounded-md p-2 space-y-1">
                    {regionsList.map((r) => (
                      <label key={r.id} className="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" checked={importRegionIds.has(r.id)} onChange={() => toggleImportRegion(r.id)} className="rounded" />
                        {r.name}
                      </label>
                    ))}
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div><Label className="text-xs">{t('keywords.engine')}</Label><div className="flex gap-3 mt-1">{['google', 'yandex'].map((e) => (<label key={e} className="flex items-center gap-1 text-xs cursor-pointer"><input type="checkbox" checked={importEngines.has(e)} onChange={() => { const n = new Set(importEngines); if (n.has(e)) { if (n.size > 1) n.delete(e) } else n.add(e); setImportEngines(n) }} className="rounded" />{e === 'google' ? 'G' : 'Я'}</label>))}</div></div>
                  <div><Label className="text-xs">{t('keywords.device')}</Label><div className="flex gap-3 mt-1">{['desktop', 'mobile'].map((d) => (<label key={d} className="flex items-center gap-1 text-xs cursor-pointer"><input type="checkbox" checked={importDevices.has(d)} onChange={() => { const n = new Set(importDevices); if (n.has(d)) { if (n.size > 1) n.delete(d) } else n.add(d); setImportDevices(n) }} className="rounded" />{d === 'desktop' ? 'D' : 'M'}</label>))}</div></div>
                </div>
                <DialogFooter><Button type="submit" disabled={importKeywords.isPending || !importClusterId || importRegionIds.size === 0}>{importKeywords.isPending ? '...' : t('keywords.import')}</Button></DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {/* ── Preset save dialog ── */}
      <Dialog open={presetDialogOpen} onOpenChange={setPresetDialogOpen}>
        <DialogContent className="sm:max-w-xs">
          <DialogHeader><DialogTitle>Сохранить пресет фильтров</DialogTitle></DialogHeader>
          <div className="space-y-2">
            <Input placeholder="Название пресета" value={presetName} onChange={(e) => setPresetName(e.target.value)} />
            {presets.length > 0 && (
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Сохранённые:</p>
                {presets.map((p) => (
                  <div key={p.name} className="flex items-center justify-between text-xs">
                    <span>{p.name}</span>
                    <Button variant="ghost" size="sm" className="h-5 text-[10px] text-destructive" onClick={() => handleDeletePreset(p.name)}>x</Button>
                  </div>
                ))}
              </div>
            )}
          </div>
          <DialogFooter><Button onClick={handleSavePreset} disabled={!presetName.trim()}>Сохранить</Button></DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ── Bulk move dialog ── */}
      <Dialog open={bulkMoveOpen} onOpenChange={setBulkMoveOpen}>
        <DialogContent className="sm:max-w-xs">
          <DialogHeader><DialogTitle>Переместить в кластер</DialogTitle></DialogHeader>
          <p className="text-xs text-muted-foreground">Выбрано ключевых слов: {selectedIds.size}</p>
          <Select value={bulkMoveClusterId} onValueChange={(v) => setBulkMoveClusterId(v ?? '')}>
            <SelectTrigger className="w-full"><SelectValue placeholder="Выберите кластер" /></SelectTrigger>
            <SelectContent>
              {clusters.map((c) => (
                <SelectItem key={c.id} value={String(c.id)} label={c.category?.name ? `${c.category.name} / ${c.name}` : c.name}>
                  {c.category?.name ? `${c.category.name} / ${c.name}` : c.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <DialogFooter>
            <Button onClick={handleBulkMove} disabled={!bulkMoveClusterId || updateKeyword.isPending}>
              {updateKeyword.isPending ? 'Перемещение...' : 'Переместить'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* ── Table ── */}
      {isLoading ? <TableSkeleton rows={10} /> : mergedRows.length === 0 ? <EmptyState title={t('keywords.noKeywords')} /> : (
        <div className="overflow-x-auto border rounded-lg">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 sticky top-0 z-20">
              <tr>
                <th className="sticky left-0 bg-muted/50 z-30 px-1.5 py-1.5 w-7">
                  <input type="checkbox" checked={mergedRows.length > 0 && selectedIds.size === mergedRows.length} onChange={() => selectedIds.size === mergedRows.length ? setSelectedIds(new Set()) : setSelectedIds(new Set(mergedRows.map((k) => k.keyword_id)))} className="rounded" />
                </th>
                <th className="sticky left-7 bg-muted/50 z-30 px-2 py-1.5 text-left font-medium text-xs min-w-[220px] cursor-pointer select-none" onClick={() => toggleSort('keyword')}>
                  {t('keywords.keyword')}{sortIcon('keyword')}
                </th>
                <th className="px-0.5 py-1.5 text-center font-medium text-xs w-24" colSpan={3}>
                  <div className="flex text-[10px]">
                    <div className="flex-1 cursor-pointer select-none" title="Exact (точное)" onClick={() => toggleSort('frequency_exact')}>!{sortIcon('frequency_exact')}</div>
                    <div className="flex-1 cursor-pointer select-none" title="Phrase (фразовое)" onClick={() => toggleSort('frequency_phrase')}>&laquo;&raquo;{sortIcon('frequency_phrase')}</div>
                    <div className="flex-1 cursor-pointer select-none" title="Broad (широкое)" onClick={() => toggleSort('frequency_broad')}>~{sortIcon('frequency_broad')}</div>
                  </div>
                </th>
                {dates.map((date, idx) => (
                  <th key={date} className="px-0.5 py-1 text-center font-medium border-l" colSpan={visibleEngines.length}>
                    <div className={`text-[10px] leading-tight ${idx === 0 ? 'cursor-pointer select-none' : ''}`} onClick={idx === 0 ? () => toggleSort('position') : undefined}>
                      {formatDate(date)}{idx === 0 && sortIcon('position')}
                    </div>
                    {visibleEngines.length > 1 && (
                      <div className="flex">{visibleEngines.map((e) => (<div key={e} className="flex-1 text-[9px] text-muted-foreground font-normal">{e === 'google' ? 'G' : 'Я'}</div>))}</div>
                    )}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {groups.map((group) => (
                <GroupSection key={group.label || '__all__'} label={group.label} rows={group.rows} dates={dates} engines={visibleEngines} totalCols={totalCols} projectId={projectId} selectedIds={selectedIds} setSelectedIds={setSelectedIds} showGroupHeader={groupByFields.size > 0} />
              ))}
            </tbody>
          </table>
        </div>
      )}

      <p className="text-xs text-muted-foreground">{t('keywords.total')}: {mergedRows.length} rows ({filtered.length} kw)</p>
    </div>
  )
}

// ─── Group section ───
function GroupSection({ label, rows, dates, engines, totalCols, projectId, selectedIds, setSelectedIds, showGroupHeader }: {
  label: string; rows: any[]; dates: string[]; engines: string[]; totalCols: number; projectId: string
  selectedIds: Set<number>; setSelectedIds: React.Dispatch<React.SetStateAction<Set<number>>>; showGroupHeader: boolean
}) {
  const [collapsed, setCollapsed] = useState(false)
  return (
    <>
      {showGroupHeader && (
        <tr className="bg-muted/80">
          <td colSpan={totalCols} className="px-3 py-1.5 text-xs font-semibold cursor-pointer" onClick={() => setCollapsed(!collapsed)}>
            <span className="mr-1">{collapsed ? '▸' : '▾'}</span>
            {label} <span className="font-normal text-muted-foreground">({rows.length})</span>
          </td>
        </tr>
      )}
      {!collapsed && rows.map((kw) => (
        <tr key={kw.keyword_id} className="border-t hover:bg-muted/20">
          <td className="sticky left-0 bg-background z-10 px-1.5 py-1">
            <input type="checkbox" checked={selectedIds.has(kw.keyword_id)} onChange={() => setSelectedIds((p) => { const n = new Set(p); n.has(kw.keyword_id) ? n.delete(kw.keyword_id) : n.add(kw.keyword_id); return n })} className="rounded" />
          </td>
          <td className="sticky left-7 bg-background z-10 px-2 py-1">
            <div className="flex items-center gap-1">
              {kw.our_url && (
                <a href={kw.our_url} target="_blank" rel="noopener noreferrer" className="text-green-600 hover:text-green-800 shrink-0" title={kw.our_url}>
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                </a>
              )}
              <Link to="/projects/$projectId/keywords/$keywordId" params={{ projectId, keywordId: String(kw.keyword_id) }} className="text-primary hover:underline truncate text-xs">{kw.keyword}</Link>
            </div>
            {kw.cluster && <div className="text-[9px] text-muted-foreground truncate">{kw.category ? `${kw.category} / ` : ''}{kw.cluster}</div>}
          </td>
          <td className="px-1 py-1 text-right tabular-nums text-muted-foreground text-[10px]" title={kw.frequency_exact?.toLocaleString()}>{fmtFreq(kw.frequency_exact)}</td>
          <td className="px-1 py-1 text-right tabular-nums text-muted-foreground text-[10px]" title={kw.frequency_phrase?.toLocaleString()}>{fmtFreq(kw.frequency_phrase)}</td>
          <td className="px-1 py-1 text-right tabular-nums text-muted-foreground text-[10px]" title={kw.frequency_broad?.toLocaleString()}>{fmtFreq(kw.frequency_broad)}</td>
          {dates.map((date) => engines.map((eng) => (
            <td key={`${date}-${eng}`} className="px-0.5 py-1 border-l w-10">
              <PositionCell position={kw.engines[eng]?.[date]?.position ?? null} delta={kw.engines[eng]?.[date]?.delta ?? null} />
            </td>
          )))}
        </tr>
      ))}
    </>
  )
}
