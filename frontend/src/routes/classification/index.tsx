import { createFileRoute, redirect, Link } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  useClassificationRules,
  useCreateClassificationRule,
  useDeleteClassificationRule,
  useSiteTypes,
} from '@/hooks/useClassification'
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
import type { ClassificationRule, SiteType } from '@/types/api'

export const Route = createFileRoute('/classification/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ClassificationPage,
})

function ClassificationPage() {
  const { t } = useTranslation()
  const { data: rulesData, isLoading } = useClassificationRules()
  const { data: siteTypesData } = useSiteTypes()
  const createRule = useCreateClassificationRule()
  const deleteRule = useDeleteClassificationRule()

  const rules: ClassificationRule[] = useMemo(
    () => rulesData?.data ?? rulesData ?? [],
    [rulesData],
  )
  const siteTypes: SiteType[] = useMemo(
    () => siteTypesData?.data ?? siteTypesData ?? [],
    [siteTypesData],
  )

  const [open, setOpen] = useState(false)
  const [ruleType, setRuleType] = useState('domain')
  const [pattern, setPattern] = useState('')
  const [siteTypeId, setSiteTypeId] = useState<string>('')
  const [priority, setPriority] = useState('0')

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      await createRule.mutateAsync({
        rule_type: ruleType,
        pattern,
        site_type_id: Number(siteTypeId),
        priority: Number(priority),
      })
      setPattern('')
      setSiteTypeId('')
      setPriority('0')
      setOpen(false)
    },
    [ruleType, pattern, siteTypeId, priority, createRule],
  )

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('classification.title')}</h1>
          <div className="flex gap-2">
            <Link to="/classification/domains">
              <Button variant="outline">{t('classification.domains')}</Button>
            </Link>
            <Dialog open={open} onOpenChange={setOpen}>
              <DialogTrigger render={<Button />}>{t('classification.addRule')}</DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>{t('classification.addClassificationRule')}</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleCreate} className="space-y-4">
                  <div className="space-y-2">
                    <Label>{t('classification.ruleType')}</Label>
                    <Select
                      value={ruleType}
                      onValueChange={(v: string | null) =>
                        setRuleType(v ?? 'domain')
                      }
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="domain">{t('classification.domain')}</SelectItem>
                        <SelectItem value="url_pattern">
                          {t('classification.urlPattern')}
                        </SelectItem>
                        <SelectItem value="regex">{t('classification.regex')}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t('classification.pattern')}</Label>
                    <Input
                      value={pattern}
                      onChange={(e) => setPattern(e.target.value)}
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>{t('classification.siteType')}</Label>
                    <Select
                      value={siteTypeId}
                      onValueChange={(v: string | null) =>
                        setSiteTypeId(v ?? '')
                      }
                    >
                      <SelectTrigger>
                        <SelectValue placeholder={t('classification.selectType')} />
                      </SelectTrigger>
                      <SelectContent>
                        {siteTypes.map((st) => (
                          <SelectItem key={st.id} value={String(st.id)}>
                            {st.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t('classification.priority')}</Label>
                    <Input
                      type="number"
                      value={priority}
                      onChange={(e) => setPriority(e.target.value)}
                    />
                  </div>
                  <DialogFooter>
                    <Button type="submit" disabled={createRule.isPending}>
                      {createRule.isPending ? t('classification.creating') : t('classification.create')}
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {isLoading ? (
          <TableSkeleton rows={6} />
        ) : rules.length === 0 ? (
          <EmptyState title={t('classification.noRules')} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('classification.ruleType')}</TableHead>
                <TableHead>{t('classification.pattern')}</TableHead>
                <TableHead>{t('classification.siteType')}</TableHead>
                <TableHead>{t('classification.priority')}</TableHead>
                <TableHead>{t('classification.system')}</TableHead>
                <TableHead>{t('common.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rules.map((rule) => (
                <TableRow key={rule.id}>
                  <TableCell>
                    <Badge variant="outline">{rule.rule_type}</Badge>
                  </TableCell>
                  <TableCell className="font-mono text-sm">
                    {rule.pattern}
                  </TableCell>
                  <TableCell>
                    {rule.site_type ? (
                      <SiteTypeBadge type={rule.site_type} />
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>{rule.priority}</TableCell>
                  <TableCell>
                    {rule.is_system ? (
                      <Badge variant="secondary">{t('classification.system')}</Badge>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>
                    {!rule.is_system && (
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteRule.mutate(rule.id)}
                        disabled={deleteRule.isPending}
                      >
                        {t('classification.delete')}
                      </Button>
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
