import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { Card, CardContent } from '@/components/ui/card'
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

const FREQ_DAYS_LABELS: Record<string, string> = {
  '1': 'Ежедневно',
  '7': 'Еженедельно',
  '14': 'Раз в 2 недели',
  '30': 'Ежемесячно',
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
        is_active: true,
      })
      setCreateOpen(false)
      setProjectId('')
    } catch (err) {
      setFormError(parseApiError(err))
    }
  }, [projectId, freqDays, collectTrends, collectSuggestions, createSchedule])

  const handleToggle = useCallback((s: WordstatSchedule) => {
    updateSchedule.mutate({ id: s.id, is_active: !s.is_active })
  }, [updateSchedule])

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('wordstatSchedules.title')}</h1>
          <Button onClick={() => setCreateOpen(true)}>{t('wordstatSchedules.addSchedule')}</Button>
        </div>

        {isLoading ? (
          <TableSkeleton rows={5} />
        ) : schedules.length === 0 ? (
          <EmptyState title={t('wordstatSchedules.noSchedules')} />
        ) : (
          <Card>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Проект</TableHead>
                    <TableHead>{t('wordstatSchedules.frequencyDays')}</TableHead>
                    <TableHead>Тренды</TableHead>
                    <TableHead>Подсказки</TableHead>
                    <TableHead>{t('wordstatSchedules.lastRun')}</TableHead>
                    <TableHead>{t('wordstatSchedules.nextRun')}</TableHead>
                    <TableHead>{t('wordstatSchedules.status')}</TableHead>
                    <TableHead>{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {schedules.map((s) => (
                    <TableRow key={s.id}>
                      <TableCell className="text-sm">
                        {projects.find((p) => p.id === s.project_id)?.name ?? `#${s.project_id}`}
                      </TableCell>
                      <TableCell>{s.frequency_days} дн.</TableCell>
                      <TableCell>
                        <Badge variant={s.collect_trends ? 'default' : 'outline'}>
                          {s.collect_trends ? 'Да' : 'Нет'}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant={s.collect_suggestions ? 'default' : 'outline'}>
                          {s.collect_suggestions ? 'Да' : 'Нет'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {s.last_run_at ? new Date(s.last_run_at).toLocaleString() : '—'}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {s.next_run_at ? new Date(s.next_run_at).toLocaleString() : '—'}
                      </TableCell>
                      <TableCell>
                        <Badge variant={s.is_active ? 'default' : 'outline'} className="cursor-pointer" onClick={() => handleToggle(s)}>
                          {s.is_active ? 'Вкл' : 'Выкл'}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button variant="outline" size="sm" className="text-xs" onClick={() => runSchedule.mutate(s.id)} disabled={runSchedule.isPending}>
                            Запустить
                          </Button>
                          <Button variant="outline" size="sm" className="text-xs text-destructive" onClick={() => { if (confirm('Удалить расписание?')) deleteSchedule.mutate(s.id) }}>
                            ✕
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        )}
      </div>

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader><DialogTitle>{t('wordstatSchedules.addSchedule')}</DialogTitle></DialogHeader>
          {formError && <p className="text-sm text-destructive">{formError}</p>}
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">Проект</Label>
              <Select value={projectId} onValueChange={(v) => setProjectId(v ?? '')}>
                <SelectTrigger className="w-full"><SelectValue placeholder="Выберите проект" labels={projectLabels} /></SelectTrigger>
                <SelectContent>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)} label={p.name}>{p.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">{t('wordstatSchedules.frequencyDays')}</Label>
              <Select value={freqDays} onValueChange={(v) => setFreqDays(v ?? '7')}>
                <SelectTrigger className="w-full"><SelectValue labels={FREQ_DAYS_LABELS} /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="1" label="Ежедневно">Ежедневно</SelectItem>
                  <SelectItem value="7" label="Еженедельно">Еженедельно</SelectItem>
                  <SelectItem value="14" label="Раз в 2 недели">Раз в 2 недели</SelectItem>
                  <SelectItem value="30" label="Ежемесячно">Ежемесячно</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="flex gap-4">
              <label className="flex items-center gap-2 text-xs cursor-pointer">
                <input type="checkbox" checked={collectTrends} onChange={(e) => setCollectTrends(e.target.checked)} className="rounded" />
                Собирать тренды
              </label>
              <label className="flex items-center gap-2 text-xs cursor-pointer">
                <input type="checkbox" checked={collectSuggestions} onChange={(e) => setCollectSuggestions(e.target.checked)} className="rounded" />
                Собирать подсказки
              </label>
            </div>
            <DialogFooter>
              <Button type="submit" disabled={createSchedule.isPending || !projectId}>
                {createSchedule.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
