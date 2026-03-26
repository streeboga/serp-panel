import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { useSchedules, useCreateSchedule, useUpdateSchedule, useDeleteSchedule, useRunSchedule } from '@/hooks/useSchedules'
import { useProjects } from '@/hooks/useProjects'
import { useScrapers } from '@/hooks/useScrapers'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { parseApiError } from '@/lib/api'
import type { Schedule, Project, Scraper } from '@/types/api'

export const Route = createFileRoute('/schedules/')({
  beforeLoad: () => { if (!localStorage.getItem('token')) throw redirect({ to: '/login' }) },
  component: SchedulesPage,
})

function SchedulesPage() {
  const { t } = useTranslation()
  const { data: schedulesData, isLoading } = useSchedules()
  const { data: projectsData } = useProjects()
  const { data: scrapersData } = useScrapers()
  const createSchedule = useCreateSchedule()
  const updateSchedule = useUpdateSchedule()
  const deleteSchedule = useDeleteSchedule()
  const runSchedule = useRunSchedule()

  const schedules: Schedule[] = useMemo(() => {
    const d = schedulesData?.data ?? schedulesData
    return Array.isArray(d) ? d : []
  }, [schedulesData])

  const projects: Project[] = useMemo(() => {
    const d = projectsData?.data ?? projectsData
    return Array.isArray(d) ? d : []
  }, [projectsData])

  const scrapers: Scraper[] = useMemo(() => {
    const d = scrapersData?.data ?? scrapersData
    return Array.isArray(d) ? d : []
  }, [scrapersData])

  const [createOpen, setCreateOpen] = useState(false)
  const [projectId, setProjectId] = useState('')
  const [scraperId, setScraperId] = useState('')
  const [freqDays, setFreqDays] = useState('1')
  const [formError, setFormError] = useState<string | null>(null)

  const handleCreate = useCallback(async (e: React.FormEvent) => {
    e.preventDefault()
    setFormError(null)
    try {
      await createSchedule.mutateAsync({
        schedulable_type: 'project',
        schedulable_id: Number(projectId),
        frequency: freqDays,
        is_active: true,
      })
      setCreateOpen(false)
      setProjectId('')
    } catch (err) {
      setFormError(parseApiError(err))
    }
  }, [projectId, scraperId, freqDays, createSchedule])

  const handleToggle = useCallback((s: Schedule) => {
    updateSchedule.mutate({ id: s.id, is_active: !s.is_active })
  }, [updateSchedule])

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Расписания SERP</h1>
          <Button onClick={() => setCreateOpen(true)}>Добавить расписание</Button>
        </div>

        <div className="rounded-lg border p-4 bg-muted/30 text-sm space-y-2">
          <p className="font-medium">Как работает сбор SERP:</p>
          <ul className="list-disc list-inside text-xs text-muted-foreground space-y-1">
            <li>Расписание привязано к <b>проекту</b> — собирает данные для всех ключевиков проекта</li>
            <li>Поисковик (Яндекс/Google), устройство (Desktop/Mobile) и регион берутся из <b>настроек каждого ключевика</b></li>
            <li>При импорте ключей вы выбираете поисковик, устройство и регион — они привязываются к ключу</li>
            <li>Один ключевик может быть импортирован для разных комбинаций (Яндекс Desktop Москва + Google Desktop Москва)</li>
            <li>Парсер (XMLRiver) берётся из настроек → <b>Парсеры</b></li>
          </ul>
        </div>

        {isLoading ? (
          <TableSkeleton rows={4} />
        ) : schedules.length === 0 ? (
          <EmptyState title="Расписания не настроены" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Проект</TableHead>
                <TableHead>Частота</TableHead>
                <TableHead>Последний запуск</TableHead>
                <TableHead>Следующий запуск</TableHead>
                <TableHead>Статус</TableHead>
                <TableHead>Действия</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {schedules.map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="text-sm">
                    {s.schedulable_name ?? projects.find((p) => p.id === Number(s.schedulable_type === 'project' ? s.schedulable_type : ''))?.name ?? `#${s.id}`}
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">{s.frequency}</Badge>
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
        )}
      </div>

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader><DialogTitle>Добавить расписание SERP</DialogTitle></DialogHeader>
          {formError && <p className="text-sm text-destructive">{formError}</p>}
          <form onSubmit={handleCreate} className="space-y-3">
            <div className="space-y-1">
              <Label className="text-xs">Проект</Label>
              <Select value={projectId} onValueChange={(v) => setProjectId(v ?? '')}>
                <SelectTrigger className="w-full"><SelectValue placeholder="Выберите проект" /></SelectTrigger>
                <SelectContent>
                  {projects.map((p) => (
                    <SelectItem key={p.id} value={String(p.id)}>{p.name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Парсер</Label>
              <Select value={scraperId} onValueChange={(v) => setScraperId(v ?? '')}>
                <SelectTrigger className="w-full"><SelectValue placeholder="Выберите парсер" /></SelectTrigger>
                <SelectContent>
                  {scrapers.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>{s.name} ({s.type})</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Частота</Label>
              <Select value={freqDays} onValueChange={(v) => setFreqDays(v ?? '1')}>
                <SelectTrigger className="w-full"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="1">Ежедневно</SelectItem>
                  <SelectItem value="3">Раз в 3 дня</SelectItem>
                  <SelectItem value="7">Еженедельно</SelectItem>
                  <SelectItem value="14">Раз в 2 недели</SelectItem>
                  <SelectItem value="30">Ежемесячно</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="text-[11px] text-muted-foreground bg-muted/50 rounded p-2">
              Поисковик, устройство и регион берутся из настроек каждого ключевика.
              Если нужен сбор по другому поисковику — импортируйте ключи с нужными параметрами.
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
