import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import {
  useSchedules, useCreateSchedule, useDeleteSchedule, useRunSchedule,
} from '@/hooks/useSchedules'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter,
} from '@/components/ui/dialog'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

export const Route = createFileRoute('/schedules/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: SchedulesPage,
})

function SchedulesPage() {
  const { data: schedulesData, isLoading } = useSchedules()
  const createSchedule = useCreateSchedule()
  const deleteSchedule = useDeleteSchedule()
  const runSchedule = useRunSchedule()

  const schedules = schedulesData?.data ?? schedulesData ?? []

  const [open, setOpen] = useState(false)
  const [schedulableType, setSchedulableType] = useState('project')
  const [schedulableId, setSchedulableId] = useState('')
  const [frequency, setFrequency] = useState('daily')

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    await createSchedule.mutateAsync({
      schedulable_type: schedulableType,
      schedulable_id: Number(schedulableId),
      frequency,
      is_active: true,
    })
    setSchedulableId('')
    setOpen(false)
  }

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Schedules</h1>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>Add Schedule</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add Schedule</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="space-y-2">
                  <Label>Scope</Label>
                  <Select value={schedulableType} onValueChange={(v: string | null) => setSchedulableType(v ?? 'project')}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="project">Project</SelectItem>
                      <SelectItem value="category">Category</SelectItem>
                      <SelectItem value="cluster">Cluster</SelectItem>
                      <SelectItem value="keyword">Keyword</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>ID</Label>
                  <Input type="number" value={schedulableId} onChange={(e) => setSchedulableId(e.target.value)} required />
                </div>
                <div className="space-y-2">
                  <Label>Frequency</Label>
                  <Select value={frequency} onValueChange={(v: string | null) => setFrequency(v ?? 'daily')}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="hourly">Hourly</SelectItem>
                      <SelectItem value="daily">Daily</SelectItem>
                      <SelectItem value="weekly">Weekly</SelectItem>
                      <SelectItem value="monthly">Monthly</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={createSchedule.isPending}>
                    {createSchedule.isPending ? 'Creating...' : 'Create'}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Scope</TableHead>
                <TableHead>Frequency</TableHead>
                <TableHead>Last Run</TableHead>
                <TableHead>Next Run</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {Array.isArray(schedules) && schedules.length > 0 ? schedules.map((schedule: {
                id: number
                schedulable_type: string
                schedulable_name?: string
                frequency: string
                last_run_at: string | null
                next_run_at: string | null
                is_active: boolean
              }) => (
                <TableRow key={schedule.id}>
                  <TableCell>
                    <div>
                      <Badge variant="outline">{schedule.schedulable_type}</Badge>
                      {schedule.schedulable_name && (
                        <span className="ml-2 text-sm">{schedule.schedulable_name}</span>
                      )}
                    </div>
                  </TableCell>
                  <TableCell><Badge variant="secondary">{schedule.frequency}</Badge></TableCell>
                  <TableCell>
                    {schedule.last_run_at ? new Date(schedule.last_run_at).toLocaleString() : '-'}
                  </TableCell>
                  <TableCell>
                    {schedule.next_run_at ? new Date(schedule.next_run_at).toLocaleString() : '-'}
                  </TableCell>
                  <TableCell>
                    <Badge variant={schedule.is_active ? 'default' : 'secondary'}>
                      {schedule.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => runSchedule.mutate(schedule.id)}
                        disabled={runSchedule.isPending}
                      >
                        Run Now
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteSchedule.mutate(schedule.id)}
                      >
                        Delete
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              )) : (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-muted-foreground">
                    No schedules configured
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        )}
      </div>
    </AppLayout>
  )
}
