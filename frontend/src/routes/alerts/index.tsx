import { createFileRoute, redirect } from '@tanstack/react-router'
import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
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
  useAlerts,
  useDeleteAlert,
  useUpdateAlert,
} from '@/hooks/useAlerts'
import type { PositionAlert } from '@/types/api'
import { Bell, Trash2 } from 'lucide-react'

export const Route = createFileRoute('/alerts/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) throw redirect({ to: '/login' })
  },
  component: AlertsPage,
})

function AlertsPage() {
  const { t } = useTranslation()
  const { data: alertsData, isLoading } = useAlerts()
  const deleteAlert = useDeleteAlert()
  const updateAlert = useUpdateAlert()

  const alerts: PositionAlert[] = Array.isArray(alertsData?.data)
    ? alertsData.data
    : Array.isArray(alertsData)
      ? alertsData
      : []

  const handleToggleActive = useCallback(
    (alert: PositionAlert) => {
      updateAlert.mutate({ id: alert.id, is_active: !alert.is_active })
    },
    [updateAlert],
  )

  return (
    <AppLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
              <Bell className="h-5 w-5 text-accent-blue" />
              {t('alerts.title')}
            </h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Мониторинг изменений позиций и уведомления</p>
          </div>
        </div>

        {isLoading ? (
          <TableSkeleton rows={5} />
        ) : alerts.length === 0 ? (
          <EmptyState title={t('alerts.noAlerts')} />
        ) : (
          <div className="glass-card rounded-lg overflow-hidden">
            <Table className="compact-table">
              <TableHeader>
                <TableRow>
                  <TableHead className="text-[11px]">{t('alerts.keyword')}</TableHead>
                  <TableHead className="text-[11px]">{t('alerts.threshold')}</TableHead>
                  <TableHead className="text-[11px]">{t('alerts.direction')}</TableHead>
                  <TableHead className="text-[11px]">{t('alerts.channel')}</TableHead>
                  <TableHead className="text-[11px]">Получатель</TableHead>
                  <TableHead className="text-[11px]">{t('alerts.status')}</TableHead>
                  <TableHead className="text-[11px]">{t('alerts.lastTriggered')}</TableHead>
                  <TableHead className="text-[11px]">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {alerts.map((alert) => (
                  <TableRow key={alert.id}>
                    <TableCell className="text-[12px] font-medium">
                      {alert.keyword?.keyword ?? `#${alert.keyword_id}`}
                    </TableCell>
                    <TableCell className="text-[12px]">{alert.threshold_position}</TableCell>
                    <TableCell>
                      <Badge variant="outline" className="text-[10px]">
                        {alert.direction === 'drops_below'
                          ? t('alerts.dropsBelow')
                          : t('alerts.risesAbove')}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="text-[10px]">{alert.channel}</Badge>
                    </TableCell>
                    <TableCell className="text-[12px] text-muted-foreground">
                      {alert.recipient || '—'}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={alert.is_active ? 'default' : 'outline'}
                        className="cursor-pointer text-[10px]"
                        onClick={() => handleToggleActive(alert)}
                      >
                        {alert.is_active ? t('alerts.active') : t('alerts.inactive')}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-[11px] text-muted-foreground">
                      {alert.last_triggered_at
                        ? new Date(alert.last_triggered_at).toLocaleDateString()
                        : t('alerts.never')}
                    </TableCell>
                    <TableCell>
                      <Button
                        variant="outline"
                        size="xs"
                        className="text-[11px] text-destructive hover:text-destructive"
                        onClick={() => {
                          if (confirm(t('alerts.deleteConfirm'))) {
                            deleteAlert.mutate(alert.id)
                          }
                        }}
                      >
                        <Trash2 className="h-3 w-3 mr-1" />
                        {t('alerts.delete')}
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}
      </div>
    </AppLayout>
  )
}
