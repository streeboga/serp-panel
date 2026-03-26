import { createFileRoute, Link, Outlet, useMatches } from '@tanstack/react-router'
import { useState, useMemo, useCallback, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useImportKeywords, useDeleteKeywords, useRegions, useProjectClusters } from '@/hooks/useKeywords'
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
interface FilterPreset {
  name: string
  engines: string[]
  devices: string[]
  categories: string[]
  clusters: string[]
  regions: string[]
  groupBy: string
  days: number
}

type GroupByField = '' | 'category' | 'cluster' | 'engine' | 'device' | 'region'

// ─── Multi-select filter dropdown ───
function MultiFilter({ label, options, selected, onChange }: {
  label: string
  options: string[]
  selected: Set<string>
  onChange: (s: Set<string>) => void
}) {
  const [open, setOpen] = useState(false)
  const allSelected = selected.size === 0 || selected.size === options.length
  return (
    <div className="relative">
      <button onClick={() => setOpen(!open)} className="flex items-center gap-1 px-2 py-1 text-xs border rounded-md hover:bg-muted transition-colors">
        {label}
        {!allSelected && <Badge variant="secondary" className="ml-1 text-[10px] px-1 py-0">{selected.size}</Badge>}
        <span className="text-[10px]">▾</span>
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} />
          <div className="absolute top-full left-0 mt-1 bg-popover border rounded-md shadow-lg z-50 min-w-[150px] max-h-60 overflow-auto">
            <label className="flex items-center gap-2 px-2 py-1.5 text-xs hover:bg-muted cursor-pointer border-b">
              <input type="checkbox" checked={allSelected} onChange={() => onChange(new Set())} className="rounded" />
              Все
            </label>
            {options.map((opt) => (
              <label key={opt} className="flex items-center gap-2 px-2 py-1.5 text-xs hover:bg-muted cursor-pointer">
                <input
                  type="checkbox"
                  checked={selected.size === 0 || selected.has(opt)}
                  onChange={() => {
                    const next = new Set(selected.size === 0 ? options.filter((o) => o !== opt) : selected)
                    if (next.has(opt)) next.delete(opt); else next.add(opt)
                    if (next.size === options.length || next.size === 0) onChange(new Set())
                    else onChange(next)
                  }}
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
  const [groupBy, setGroupBy] = useState<GroupByField>('')
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

  // ── Grouping ──
  const groups = useMemo(() => {
    if (!groupBy) return [{ label: '', rows: mergedRows }]
    const map = new Map<string, MergedRow[]>()
    for (const row of mergedRows) {
      let key = ''
      if (groupBy === 'category') key = row.category ?? 'Без категории'
      else if (groupBy === 'cluster') key = row.cluster ?? 'Без кластера'
      else if (groupBy === 'region') key = row.region ?? 'Без региона'
      else if (groupBy === 'engine' || groupBy === 'device') {
        // For engine/device grouping, we need to use the raw data
        key = groupBy === 'engine' ? Object.keys(row.engines).join(', ') : 'all'
      }
      if (!map.has(key)) map.set(key, [])
      map.get(key)!.push(row)
    }
    return Array.from(map.entries()).map(([label, rows]) => ({ label, rows })).sort((a, b) => a.label.localeCompare(b.label))
  }, [mergedRows, groupBy])

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
      groupBy,
      days,
    }
    const updated = [...presets.filter((x) => x.name !== p.name), p]
    setPresets(updated)
    savePresets(updated)
    setPresetName('')
    setPresetDialogOpen(false)
  }, [presetName, filterEngines, filterDevices, filterCategories, filterClusters, filterRegions, groupBy, days, presets])

  const handleLoadPreset = useCallback((name: string) => {
    const p = presets.find((x) => x.name === name)
    if (!p) return
    setFilterEngines(new Set(p.engines))
    setFilterDevices(new Set(p.devices))
    setFilterCategories(new Set(p.categories))
    setFilterClusters(new Set(p.clusters))
    setFilterRegions(new Set(p.regions))
    setGroupBy(p.groupBy as GroupByField)
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
    if (!selectedIds.size || !confirm(`Delete ${selectedIds.size} keywords?`)) return
    await deleteKeywords.mutateAsync([...selectedIds]); setSelectedIds(new Set())
  }, [selectedIds, deleteKeywords])

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

        {uniqueEngines.length > 1 && <MultiFilter label="Engine" options={uniqueEngines} selected={filterEngines} onChange={setFilterEngines} />}
        {uniqueDevices.length > 1 && <MultiFilter label="Device" options={uniqueDevices} selected={filterDevices} onChange={setFilterDevices} />}
        {uniqueCategories.length > 1 && <MultiFilter label="Category" options={uniqueCategories} selected={filterCategories} onChange={setFilterCategories} />}
        {uniqueClusters.length > 1 && <MultiFilter label="Cluster" options={uniqueClusters} selected={filterClusters} onChange={setFilterClusters} />}
        {uniqueRegions.length > 1 && <MultiFilter label="Region" options={uniqueRegions} selected={filterRegions} onChange={setFilterRegions} />}

        <Select value={String(days)} onValueChange={(v) => setDays(Number(v ?? 14))}>
          <SelectTrigger className="w-20 h-8 text-xs"><SelectValue /></SelectTrigger>
          <SelectContent>
            <SelectItem value="7">7d</SelectItem>
            <SelectItem value="14">14d</SelectItem>
            <SelectItem value="30">30d</SelectItem>
          </SelectContent>
        </Select>

        <Select value={groupBy || '__none__'} onValueChange={(v) => setGroupBy((v === '__none__' ? '' : v) as GroupByField)}>
          <SelectTrigger className="w-28 h-8 text-xs"><SelectValue placeholder="Group by" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="__none__">No grouping</SelectItem>
            <SelectItem value="category">Category</SelectItem>
            <SelectItem value="cluster">Cluster</SelectItem>
            <SelectItem value="engine">Engine</SelectItem>
            <SelectItem value="device">Device</SelectItem>
            <SelectItem value="region">Region</SelectItem>
          </SelectContent>
        </Select>

        {/* Presets */}
        {presets.length > 0 && (
          <Select value="__load__" onValueChange={(v) => { if (v && v !== '__load__') handleLoadPreset(v) }}>
            <SelectTrigger className="w-28 h-8 text-xs"><SelectValue placeholder="Presets" /></SelectTrigger>
            <SelectContent>
              {presets.map((p) => (
                <SelectItem key={p.name} value={p.name}>
                  {p.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
        <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={() => setPresetDialogOpen(true)}>
          Save preset
        </Button>

        {selectedIds.size > 0 && (
          <Button variant="outline" size="sm" className="h-8 text-xs text-destructive" onClick={handleBulkDelete} disabled={deleteKeywords.isPending}>
            Delete ({selectedIds.size})
          </Button>
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
                  <Select value={importClusterId} onValueChange={(v) => setImportClusterId(v ?? '')}><SelectTrigger className="w-full"><SelectValue placeholder={t('keywords.selectCluster')} /></SelectTrigger><SelectContent>{clusters.map((c) => (<SelectItem key={c.id} value={String(c.id)}>{c.category?.name ? `${c.category.name} / ${c.name}` : c.name}</SelectItem>))}</SelectContent></Select>
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
          <DialogHeader><DialogTitle>Save Filter Preset</DialogTitle></DialogHeader>
          <div className="space-y-2">
            <Input placeholder="Preset name" value={presetName} onChange={(e) => setPresetName(e.target.value)} />
            {presets.length > 0 && (
              <div className="space-y-1">
                <p className="text-xs text-muted-foreground">Existing presets:</p>
                {presets.map((p) => (
                  <div key={p.name} className="flex items-center justify-between text-xs">
                    <span>{p.name}</span>
                    <Button variant="ghost" size="sm" className="h-5 text-[10px] text-destructive" onClick={() => handleDeletePreset(p.name)}>x</Button>
                  </div>
                ))}
              </div>
            )}
          </div>
          <DialogFooter><Button onClick={handleSavePreset} disabled={!presetName.trim()}>Save</Button></DialogFooter>
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
                <th className="sticky left-7 bg-muted/50 z-30 px-2 py-1.5 text-left font-medium text-xs min-w-[220px]">{t('keywords.keyword')}</th>
                <th className="px-0.5 py-1.5 text-center font-medium text-xs w-24" colSpan={3}>
                  <div className="flex text-[10px]">
                    <div className="flex-1" title="Exact">!</div>
                    <div className="flex-1" title="Phrase">&laquo;&raquo;</div>
                    <div className="flex-1" title="Broad">~</div>
                  </div>
                </th>
                {dates.map((date) => (
                  <th key={date} className="px-0.5 py-1 text-center font-medium border-l" colSpan={visibleEngines.length}>
                    <div className="text-[10px] leading-tight">{formatDate(date)}</div>
                    {visibleEngines.length > 1 && (
                      <div className="flex">{visibleEngines.map((e) => (<div key={e} className="flex-1 text-[9px] text-muted-foreground font-normal">{e === 'google' ? 'G' : 'Я'}</div>))}</div>
                    )}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {groups.map((group) => (
                <GroupSection key={group.label || '__all__'} label={group.label} rows={group.rows} dates={dates} engines={visibleEngines} totalCols={totalCols} projectId={projectId} selectedIds={selectedIds} setSelectedIds={setSelectedIds} showGroupHeader={!!groupBy} />
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
          <td className="px-1 py-1 text-right tabular-nums text-muted-foreground text-[10px]">{kw.frequency_exact?.toLocaleString() ?? '—'}</td>
          <td className="px-1 py-1 text-right tabular-nums text-muted-foreground text-[10px]">{kw.frequency_phrase?.toLocaleString() ?? '—'}</td>
          <td className="px-1 py-1 text-right tabular-nums text-muted-foreground text-[10px]">{kw.frequency_broad?.toLocaleString() ?? '—'}</td>
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
