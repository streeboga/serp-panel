import { createFileRoute, redirect, Link } from '@tanstack/react-router'
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
import { Calendar, Plus, Play, Trash2, Info } from 'lucide-react'

const FREQ_DAYS_LABELS: Record<string, string> = {
  '1': 'Ежедневно',
  '3': 'Раз в 3 дня',
  '7': 'Еженедельно',
  '14': 'Раз в 2 недели',
  '30': 'Ежемесячно',
}

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

  const projectLabels = useMemo(
    () => Object.fromEntries(projects.map((p) => [String(p.id), p.name])),
    [projects],
  )
  const scraperLabels = useMemo(
    () => Object.fromEntries(scrapers.map((s) => [String(s.id), `${s.name} (${s.type})`])),
    [scrapers],
  )

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
        scraper_id: Number(scraperId),
        project_id: Number(projectId),
        frequency_days: Number(freqDays),
        is_active: true,
      })
      setCreateOpen(false)
      setProjectId('')
      setScraperId('')
    } catch (err) {
      setFormError(parseApiError(err))
    }
  }, [projectId, scraperId, freqDays, createSchedule])

  const handleToggle = useCallback((s: Schedule) => {
    updateSchedule.mutate({ id: s.id, is_active: !s.is_active })
  }, [updateSchedule])

  return (
    <AppLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
              <Calendar className="h-5 w-5 text-accent-blue" />
              Расписания SERP
            </h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Автоматический сбор поисковой выдачи по расписанию</p>
          </div>
          <Button size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" onClick={() => setCreateOpen(true)}>
            <Plus className="h-3.5 w-3.5 mr-1" />
            Добавить расписание
          </Button>
        </div>

        <div className="glass-card rounded-lg p-3 text-[12px] space-y-2">
          <p className="font-medium flex items-center gap-1.5">
            <Info className="h-3.5 w-3.5 text-accent-blue" />
            Как работает сбор SERP:
          </p>
          <ul className="list-disc list-inside text-[11px] text-muted-foreground space-y-1 ml-5">
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
          <div className="glass-card rounded-lg overflow-hidden">
            <Table className="compact-table">
              <TableHeader>
                <TableRow>
                  <TableHead className="text-[11px]">Проект</TableHead>
                  <TableHead className="text-[11px]">Парсер</TableHead>
                  <TableHead className="text-[11px]">Частота</TableHead>
                  <TableHead className="text-[11px]">Последний запуск</TableHead>
                  <TableHead className="text-[11px]">Следующий запуск</TableHead>
                  <TableHead className="text-[11px]">Статус</TableHead>
                  <TableHead className="text-[11px]">Действия</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {schedules.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell className="text-[12px]">
                      {s.project?.name ?? projects.find((p) => p.id === s.project_id)?.name ?? `#${s.id}`}
                    </TableCell>
                    <TableCell className="text-[12px]">
                      {s.scraper ? `${s.scraper.name} (${s.scraper.type})` : scrapers.find((sc) => sc.id === s.scraper_id)?.name ?? '—'}
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="text-[10px]">{s.frequency_days ? FREQ_DAYS_LABELS[String(s.frequency_days)] ?? `${s.frequency_days} дн.` : s.frequency}</Badge>
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
          <DialogHeader><DialogTitle className="text-[15px]">Добавить расписание SERP</DialogTitle></DialogHeader>
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
              <Label className="text-[11px]">Парсер</Label>
              <Select value={scraperId} onValueChange={(v) => setScraperId(v ?? '')}>
                <SelectTrigger className="w-full h-8"><SelectValue placeholder="Выберите парсер" labels={scraperLabels} /></SelectTrigger>
                <SelectContent>
                  {scrapers.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)} label={`${s.name} (${s.type})`}>{s.name} ({s.type})</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {scrapers.length <= 1 ? (
                <p className="text-[11px] text-muted-foreground mt-1">
                  Нет нужного парсера?{' '}
                  <Link to="/scrapers" className="text-accent-blue hover:underline">Создайте новый</Link>
                  {' '}(XMLRiver, Яндекс XML, Webhook)
                </p>
              ) : null}
            </div>
            <div className="space-y-1">
              <Label className="text-[11px]">Частота</Label>
              <Select value={freqDays} onValueChange={(v) => setFreqDays(v ?? '1')}>
                <SelectTrigger className="w-full h-8"><SelectValue labels={FREQ_DAYS_LABELS} /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="1" label="Ежедневно">Ежедневно</SelectItem>
                  <SelectItem value="3" label="Раз в 3 дня">Раз в 3 дня</SelectItem>
                  <SelectItem value="7" label="Еженедельно">Еженедельно</SelectItem>
                  <SelectItem value="14" label="Раз в 2 недели">Раз в 2 недели</SelectItem>
                  <SelectItem value="30" label="Ежемесячно">Ежемесячно</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="text-[11px] text-muted-foreground bg-muted/50 rounded p-2">
              Поисковик, устройство и регион берутся из настроек каждого ключевика.
              Если нужен сбор по другому поисковику — импортируйте ключи с нужными параметрами.
            </div>
            <DialogFooter>
              <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={createSchedule.isPending || !projectId || !scraperId}>
                {createSchedule.isPending ? 'Создание...' : 'Создать'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  )
}
