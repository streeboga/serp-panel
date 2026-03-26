import { createFileRoute, redirect } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  useWordstatSchedules,
  useDeleteWordstatSchedule,
  useRunWordstatSchedule,
} from '@/hooks/useWordstatSchedules'
import type { WordstatSchedule } from '@/types/api'

export const Route = createFileRoute('/wordstat-schedules/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) throw redirect({ to: '/login' })
  },
  component: WordstatSchedulesPage,
})

function WordstatSchedulesPage() {
  const { t } = useTranslation()
  const { data: schedulesData, isLoading } = useWordstatSchedules()
  const deleteSchedule = useDeleteWordstatSchedule()
  const runSchedule = useRunWordstatSchedule()

  const schedules: WordstatSchedule[] = Array.isArray(schedulesData?.data)
    ? schedulesData.data
    : Array.isArray(schedulesData)
      ? schedulesData
      : []

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('wordstatSchedules.title')}</h1>
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
                    <TableHead>{t('wordstatSchedules.frequencyDays')}</TableHead>
                    <TableHead>{t('wordstatSchedules.collectTrends')}</TableHead>
                    <TableHead>{t('wordstatSchedules.collectSuggestions')}</TableHead>
                    <TableHead>{t('wordstatSchedules.lastRun')}</TableHead>
                    <TableHead>{t('wordstatSchedules.nextRun')}</TableHead>
                    <TableHead>{t('wordstatSchedules.status')}</TableHead>
                    <TableHead>{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {schedules.map((schedule) => (
                    <TableRow key={schedule.id}>
                      <TableCell>{schedule.frequency_days}d</TableCell>
                      <TableCell>
                        <Badge variant={schedule.collect_trends ? 'default' : 'outline'}>
                          {schedule.collect_trends ? t('common.yes') : t('common.no')}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant={schedule.collect_suggestions ? 'default' : 'outline'}>
                          {schedule.collect_suggestions ? t('common.yes') : t('common.no')}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {schedule.last_run_at
                          ? new Date(schedule.last_run_at).toLocaleString()
                          : '—'}
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {schedule.next_run_at
                          ? new Date(schedule.next_run_at).toLocaleString()
                          : '—'}
                      </TableCell>
                      <TableCell>
                        <Badge variant={schedule.is_active ? 'default' : 'outline'}>
                          {schedule.is_active
                            ? t('wordstatSchedules.active')
                            : t('wordstatSchedules.inactive')}
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
                            {t('wordstatSchedules.runNow')}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            className="text-destructive"
                            onClick={() => deleteSchedule.mutate(schedule.id)}
                          >
                            {t('wordstatSchedules.delete')}
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
    </AppLayout>
  )
}
