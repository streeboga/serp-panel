import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  useSchedules,
  useCreateSchedule,
  useUpdateSchedule,
  useDeleteSchedule,
  useRunSchedule,
} from '@/hooks/useSchedules'
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
import type { Schedule } from '@/types/api'

export const Route = createFileRoute('/schedules/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: SchedulesPage,
})

function SchedulesPage() {
  const { t } = useTranslation()
  const { data: schedulesData, isLoading } = useSchedules()
  const createSchedule = useCreateSchedule()
  const updateSchedule = useUpdateSchedule()
  const deleteSchedule = useDeleteSchedule()
  const runSchedule = useRunSchedule()

  const schedules: Schedule[] = useMemo(
    () => schedulesData?.data ?? schedulesData ?? [],
    [schedulesData],
  )

  const [formError, setFormError] = useState<string | null>(null)
  const [editFormError, setEditFormError] = useState<string | null>(null)

  const [open, setOpen] = useState(false)
  const [schedulableType, setSchedulableType] = useState('project')
  const [schedulableId, setSchedulableId] = useState('')
  const [frequency, setFrequency] = useState('daily')

  const [editOpen, setEditOpen] = useState(false)
  const [editSchedule, setEditSchedule] = useState<Schedule | null>(null)
  const [editFrequency, setEditFrequency] = useState('daily')
  const [editIsActive, setEditIsActive] = useState(true)

  const openEditDialog = useCallback((schedule: Schedule) => {
    setEditSchedule(schedule)
    setEditFrequency(schedule.frequency)
    setEditIsActive(schedule.is_active)
    setEditOpen(true)
  }, [])

  const handleEdit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!editSchedule) return
      setEditFormError(null)
      try {
        await updateSchedule.mutateAsync({
          id: editSchedule.id,
          frequency: editFrequency,
          is_active: editIsActive,
        })
        setEditOpen(false)
        setEditSchedule(null)
      } catch (err) {
        setEditFormError(parseApiError(err))
      }
    },
    [editSchedule, editFrequency, editIsActive, updateSchedule],
  )

  const toggleActive = useCallback(
    async (schedule: Schedule) => {
      await updateSchedule.mutateAsync({
        id: schedule.id,
        is_active: !schedule.is_active,
      })
    },
    [updateSchedule],
  )

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setFormError(null)
      try {
        await createSchedule.mutateAsync({
          schedulable_type: schedulableType,
          schedulable_id: Number(schedulableId),
          frequency,
          is_active: true,
        })
        setSchedulableId('')
        setOpen(false)
      } catch (err) {
        setFormError(parseApiError(err))
      }
    },
    [schedulableType, schedulableId, frequency, createSchedule],
  )

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('schedules.title')}</h1>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>{t('schedules.addSchedule')}</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{t('schedules.addSchedule')}</DialogTitle>
              </DialogHeader>
              {formError && <p className="text-sm text-destructive">{formError}</p>}
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="space-y-2">
                  <Label>{t('schedules.scope')}</Label>
                  <Select
                    value={schedulableType}
                    onValueChange={(v: string | null) =>
                      setSchedulableType(v ?? 'project')
                    }
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="project">{t('schedules.project')}</SelectItem>
                      <SelectItem value="category">{t('schedules.category')}</SelectItem>
                      <SelectItem value="cluster">{t('schedules.cluster')}</SelectItem>
                      <SelectItem value="keyword">{t('schedules.keyword')}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t('schedules.id')}</Label>
                  <Input
                    type="number"
                    value={schedulableId}
                    onChange={(e) => setSchedulableId(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('schedules.frequency')}</Label>
                  <Select
                    value={frequency}
                    onValueChange={(v: string | null) =>
                      setFrequency(v ?? 'daily')
                    }
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="hourly">{t('schedules.hourly')}</SelectItem>
                      <SelectItem value="daily">{t('schedules.daily')}</SelectItem>
                      <SelectItem value="weekly">{t('schedules.weekly')}</SelectItem>
                      <SelectItem value="monthly">{t('schedules.monthly')}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={createSchedule.isPending}>
                    {createSchedule.isPending ? t('schedules.creating') : t('schedules.create')}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {isLoading ? (
          <TableSkeleton rows={4} />
        ) : schedules.length === 0 ? (
          <EmptyState title={t('schedules.noSchedules')} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('schedules.scope')}</TableHead>
                <TableHead>{t('schedules.frequency')}</TableHead>
                <TableHead>{t('schedules.lastRun')}</TableHead>
                <TableHead>{t('schedules.nextRun')}</TableHead>
                <TableHead>{t('schedules.status')}</TableHead>
                <TableHead>{t('schedules.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {schedules.map((schedule) => (
                <TableRow key={schedule.id}>
                  <TableCell>
                    <div>
                      <Badge variant="outline">
                        {schedule.schedulable_type}
                      </Badge>
                      {schedule.schedulable_name && (
                        <span className="ml-2 text-sm">
                          {schedule.schedulable_name}
                        </span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary">{schedule.frequency}</Badge>
                  </TableCell>
                  <TableCell>
                    {schedule.last_run_at
                      ? new Date(schedule.last_run_at).toLocaleString()
                      : '-'}
                  </TableCell>
                  <TableCell>
                    {schedule.next_run_at
                      ? new Date(schedule.next_run_at).toLocaleString()
                      : '-'}
                  </TableCell>
                  <TableCell>
                    <Badge
                      variant={schedule.is_active ? 'default' : 'secondary'}
                      className="cursor-pointer"
                      onClick={() => toggleActive(schedule)}
                    >
                      {schedule.is_active ? t('schedules.active') : t('schedules.inactive')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => openEditDialog(schedule)}
                      >
                        {t('schedules.edit', 'Edit')}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => runSchedule.mutate(schedule.id)}
                        disabled={runSchedule.isPending}
                      >
                        {t('schedules.runNow')}
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteSchedule.mutate(schedule.id)}
                      >
                        {t('schedules.delete')}
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
        <Dialog open={editOpen} onOpenChange={setEditOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t('schedules.editSchedule', 'Edit Schedule')}</DialogTitle>
            </DialogHeader>
            {editFormError && <p className="text-sm text-destructive">{editFormError}</p>}
            <form onSubmit={handleEdit} className="space-y-4">
              <div className="space-y-2">
                <Label>{t('schedules.frequency')}</Label>
                <Select
                  value={editFrequency}
                  onValueChange={(v: string | null) =>
                    setEditFrequency(v ?? 'daily')
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="hourly">{t('schedules.hourly')}</SelectItem>
                    <SelectItem value="daily">{t('schedules.daily')}</SelectItem>
                    <SelectItem value="weekly">{t('schedules.weekly')}</SelectItem>
                    <SelectItem value="monthly">{t('schedules.monthly')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="flex items-center justify-between">
                <Label>{t('schedules.status')}</Label>
                <Badge
                  variant={editIsActive ? 'default' : 'secondary'}
                  className="cursor-pointer"
                  onClick={() => setEditIsActive(!editIsActive)}
                >
                  {editIsActive ? t('schedules.active') : t('schedules.inactive')}
                </Badge>
              </div>
              <DialogFooter>
                <Button type="submit" disabled={updateSchedule.isPending}>
                  {updateSchedule.isPending ? t('schedules.saving', 'Saving...') : t('schedules.save', 'Save')}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  )
}
