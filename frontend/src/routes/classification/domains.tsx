import { createFileRoute, redirect, Link } from '@tanstack/react-router'
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { useDomainClassifications } from '@/hooks/useClassification'
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
import type { DomainClassification } from '@/types/api'

export const Route = createFileRoute('/classification/domains')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: DomainsClassificationPage,
})

function DomainsClassificationPage() {
  const { t } = useTranslation()
  const { data, isLoading } = useDomainClassifications()
  const domains: DomainClassification[] = useMemo(
    () => {
      const d = data?.data ?? data
      return Array.isArray(d) ? d : []
    },
    [data],
  )

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Link to="/classification">
              <Button variant="outline" size="sm">
                {t('classification.backToRules')}
              </Button>
            </Link>
            <h1 className="text-2xl font-bold">{t('classification.domainClassifications')}</h1>
          </div>
        </div>

        {isLoading ? (
          <TableSkeleton rows={8} />
        ) : domains.length === 0 ? (
          <EmptyState title={t('classification.noDomainClassifications')} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('classification.domain')}</TableHead>
                <TableHead>{t('keywordDetail.type')}</TableHead>
                <TableHead>{t('classification.classifiedBy')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {domains.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="font-medium">{item.domain}</TableCell>
                  <TableCell>
                    {item.site_type ? (
                      <SiteTypeBadge type={item.site_type} />
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>
                    {item.classified_by ? (
                      <Badge variant="outline">{item.classified_by}</Badge>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>
    </AppLayout>
  )
}
