import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import {
  useWordstatSchedules,
  useCreateWordstatSchedule,
  useDeleteWordstatSchedule,
  useRunWordstatSchedule,
  useUpdateWordstatSchedule,
} from '@/hooks/useWordstatSchedules'
import { useProjects } from '@/hooks/useProjects'
import { parseApiError } from '@/lib/api'
import type { WordstatSchedule, Project } from '@/types/api'
import { BarChart3, Plus, Play, Trash2 } from 'lucide-react'

const FREQ_DAYS_LABELS: Record<string, string> = {
  '1': 'Ежедневно',
  '7': 'Еженедельно',
  '14': 'Раз в 2 недели',
  '30': 'Ежемесячно',
}

const ADAPTER_LABELS: Record<string, string> = {
  yandex: 'Yandex API',
  xmlriver: 'XMLRiver',
}

const ADAPTER_SELECT_LABELS: Record<string, string> = {
  auto: 'Авто',
  yandex: 'Yandex Wordstat API',
  xmlriver: 'XMLRiver',
}

export const Route = createFileRoute('/wordstat-schedules/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) throw redirect({ to: '/login' })
  },
  component: WordstatSchedulesPage,
})

function WordstatSchedulesPage() {
  const { t } = useTranslation()
  const { data: schedulesData, isLoading } = useWordstatSchedules()
  const { data: projectsData } = useProjects()
  const createSchedule = useCreateWordstatSchedule()
  const updateSchedule = useUpdateWordstatSchedule()
  const deleteSchedule = useDeleteWordstatSchedule()
  const runSchedule = useRunWordstatSchedule()

  const schedules: WordstatSchedule[] = useMemo(() => {
    const d = schedulesData?.data ?? schedulesData
    return Array.isArray(d) ? d : []
  }, [schedulesData])

  const projects: Project[] = useMemo(() => {
    const d = projectsData?.data ?? projectsData
    return Array.isArray(d) ? d : []
  }, [projectsData])

  const projectLabels = useMemo(
    () => Object.fromEntries(projects.map((p) => [String(p.id), p.name])),
    [projects],
  )

  const [createOpen, setCreateOpen] = useState(false)
  const [projectId, setProjectId] = useState('')
  const [freqDays, setFreqDays] = useState('7')
  const [collectTrends, setCollectTrends] = useState(true)
  const [collectSuggestions, setCollectSuggestions] = useState(true)
  const [adapterType, setAdapterType] = useState('auto')
  const [formError, setFormError] = useState<string | null>(null)

  const handleCreate = useCallback(async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError(null)
    try {
      await createSchedule.mutateAsync({
        project_id: Number(projectId),
        frequency_days: Number(freqDays),
        collect_trends: collectTrends,
        collect_suggestions: collectSuggestions,
        adapter_type: adapterType === 'auto' ? null : adapterType,
        is_active: true,
      })
      setCreateOpen(false)
      setProjectId('')
      setAdapterType('auto')
    } catch (err) {
      setFormError(parseApiError(err))
    }
  }, [projectId, freqDays, collectTrends, collectSuggestions, adapterType, createSchedule])

  const handleToggle = useCallback((s: WordstatSchedule) => {
    updateSchedule.mutate({ id: s.id, is_active: !s.is_active })
  }, [updateSchedule])

  return (
    <AppLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
              <BarChart3 className="h-5 w-5 text-accent-blue" />
              {t('wordstatSchedules.title')}
            </h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Сбор частотности и трендов из Яндекс Wordstat</p>
          </div>
          <Button size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" onClick={() => setCreateOpen(true)}>
            <Plus className="h-3.5 w-3.5 mr-1" />
            {t('wordstatSchedules.addSchedule')}
          </Button>
        </div>

        {isLoading ? (
          <TableSkeleton rows={5} />
        ) : schedules.length === 0 ? (
          <EmptyState title={t('wordstatSchedules.noSchedules')} />
        ) : (
          <div className="glass-card rounded-lg overflow-hidden">
            <Table className="compact-table">
              <TableHeader>
                <TableRow>
                  <TableHead className="text-[11px]">Проект</TableHead>
                  <TableHead className="text-[11px]">Регионы</TableHead>
                  <TableHead className="text-[11px]">{t('wordstatSchedules.frequencyDays')}</TableHead>
                  <TableHead className="text-[11px]">Тренды</TableHead>
                  <TableHead className="text-[11px]">Подсказки</TableHead>
                  <TableHead className="text-[11px]">{t('wordstatSchedules.adapterType')}</TableHead>
                  <TableHead className="text-[11px]">{t('wordstatSchedules.lastRun')}</TableHead>
                  <TableHead className="text-[11px]">{t('wordstatSchedules.nextRun')}</TableHead>
                  <TableHead className="text-[11px]">{t('wordstatSchedules.status')}</TableHead>
                  <TableHead className="text-[11px]">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {schedules.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell className="text-[12px]">
                      {(s as any).project?.name ?? projects.find((p) => String(p.id) === String(s.project_id))?.name ?? `#${s.project_id}`}
                    </TableCell>
                    <TableCell>
                      {s.regions && s.regions.length > 0 ? (
                        <Badge variant="secondary" className="text-[10px]">{s.regions.length} {s.regions.length === 1 ? 'регион' : s.regions.length < 5 ? 'региона' : 'регионов'}</Badge>
                      ) : (
                        <span className="text-[11px] text-muted-foreground">Все</span>
                      )}
                    </TableCell>
                    <TableCell className="text-[12px]">{s.frequency_days} дн.</TableCell>
                    <TableCell>
                      <Badge variant={s.collect_trends ? 'default' : 'outline'} className="text-[10px]">
                        {s.collect_trends ? 'Да' : 'Нет'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={s.collect_suggestions ? 'default' : 'outline'} className="text-[10px]">
                        {s.collect_suggestions ? 'Да' : 'Нет'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-[12px]">
                      {ADAPTER_LABELS[s.adapter_type ?? ''] ?? 'Авто'}
                    </TableCell>
                    <TableCell className="text-[11px] text-muted-foreground">
                      {s.last_run_at ? new Date(s.last_run_at).toLocaleString() : '—'}
                    </TableCell>
                    <TableCell className="text-[11px] text-muted-foreground">
                      {s.next_run_at ? new Date(s.next_run_at).toLocaleString() : '—'}
                    </TableCell>
                    <TableCell>
                      <Badge variant={s.is_active ? 'default' : 'outline'} className="cursor-pointer text-[10px]" onClick={() => handleToggle(s)}>
                        {s.is_active ? 'Вкл' : 'Выкл'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-1">
                        <Button variant="outline" size="xs" className="text-[11px] hover:border-accent-blue hover:text-accent-blue" onClick={() => runSchedule.mutate(s.id)} disabled={runSchedule.isPending}>
                          <Play className="h-3 w-3 mr-1" />
                          Запустить
                        </Button>
                        <Button variant="outline" size="xs" className="text-[11px] text-destructive hover:text-destructive" onClick={() => { if (confirm('Удалить расписание?')) deleteSchedule.mutate(s.id) }}>
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

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader><DialogTitle className="text-[15px]">{t('wordstatSchedules.addSchedule')}</DialogTitle></DialogHeader>
          {formError && <p className="text-[12px] text-destructive">{formError}</p>}
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-[11px]">Проект</Label>
              <Select value={projectId} onValueChange={(v) => setProjectId(v ?? '')}>
                <SelectTrigger className="w-full h-8"><SelectValue placeholder="Выберите проект" labels={projectLabels} /></SelectTrigger>
                <SelectContent>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)} label={p.name}>{p.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-[11px]">{t('wordstatSchedules.frequencyDays')}</Label>
              <Select value={freqDays} onValueChange={(v) => setFreqDays(v ?? '7')}>
                <SelectTrigger className="w-full h-8"><SelectValue labels={FREQ_DAYS_LABELS} /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="1" label="Ежедневно">Ежедневно</SelectItem>
                  <SelectItem value="7" label="Еженедельно">Еженедельно</SelectItem>
                  <SelectItem value="14" label="Раз в 2 недели">Раз в 2 недели</SelectItem>
                  <SelectItem value="30" label="Ежемесячно">Ежемесячно</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-[11px]">{t('wordstatSchedules.adapterType')}</Label>
              <Select value={adapterType} onValueChange={(v) => setAdapterType(v ?? 'auto')}>
                <SelectTrigger className="w-full h-8"><SelectValue labels={ADAPTER_SELECT_LABELS} /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="auto" label="Авто">Авто</SelectItem>
                  <SelectItem value="yandex" label="Yandex Wordstat API">Yandex Wordstat API</SelectItem>
                  <SelectItem value="xmlriver" label="XMLRiver">XMLRiver</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="flex gap-3">
              <label className="flex items-center gap-2 text-[11px] cursor-pointer">
                <input type="checkbox" checked={collectTrends} onChange={(e) => setCollectTrends(e.target.checked)} className="rounded" />
                Собирать тренды
              </label>
              <label className="flex items-center gap-2 text-[11px] cursor-pointer">
                <input type="checkbox" checked={collectSuggestions} onChange={(e) => setCollectSuggestions(e.target.checked)} className="rounded" />
                Собирать подсказки
              </label>
            </div>
            <DialogFooter>
              <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={createSchedule.isPending || !projectId}>
                {createSchedule.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
