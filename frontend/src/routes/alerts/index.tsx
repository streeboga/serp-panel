import { createFileRoute, redirect } from '@tanstack/react-router'
import { useCallback } from 'react'
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
  useAlerts,
  useDeleteAlert,
  useUpdateAlert,
} from '@/hooks/useAlerts'
import type { PositionAlert } from '@/types/api'

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
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('alerts.title')}</h1>
        </div>

        {isLoading ? (
          <TableSkeleton rows={5} />
        ) : alerts.length === 0 ? (
          <EmptyState title={t('alerts.noAlerts')} />
        ) : (
          <Card>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('alerts.keyword')}</TableHead>
                    <TableHead>{t('alerts.threshold')}</TableHead>
                    <TableHead>{t('alerts.direction')}</TableHead>
                    <TableHead>{t('alerts.channel')}</TableHead>
                    <TableHead>{t('alerts.status')}</TableHead>
                    <TableHead>{t('alerts.lastTriggered')}</TableHead>
                    <TableHead>{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {alerts.map((alert) => (
                    <TableRow key={alert.id}>
                      <TableCell className="font-medium">
                        {alert.keyword?.keyword ?? `#${alert.keyword_id}`}
                      </TableCell>
                      <TableCell>{alert.threshold_position}</TableCell>
                      <TableCell>
                        <Badge variant="outline">
                          {alert.direction === 'drops_below'
                            ? t('alerts.dropsBelow')
                            : t('alerts.risesAbove')}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary">{alert.channel}</Badge>
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={alert.is_active ? 'default' : 'outline'}
                          className="cursor-pointer"
                          onClick={() => handleToggleActive(alert)}
                        >
                          {alert.is_active ? t('alerts.active') : t('alerts.inactive')}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-sm text-muted-foreground">
                        {alert.last_triggered_at
                          ? new Date(alert.last_triggered_at).toLocaleDateString()
                          : t('alerts.never')}
                      </TableCell>
                      <TableCell>
                        <Button
                          variant="outline"
                          size="sm"
                          className="text-destructive"
                          onClick={() => {
                            if (confirm(t('alerts.deleteConfirm'))) {
                              deleteAlert.mutate(alert.id)
                            }
                          }}
                        >
                          {t('alerts.delete')}
                        </Button>
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
