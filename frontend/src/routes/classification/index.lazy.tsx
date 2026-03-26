import { createLazyFileRoute, Link } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  useClassificationRules,
  useCreateClassificationRule,
  useUpdateClassificationRule,
  useDeleteClassificationRule,
  useSiteTypes,
} from '@/hooks/useClassification'
import { parseApiError } from '@/lib/api'
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

export const Route = createLazyFileRoute('/classification/')({
  component: ClassificationPage,
})

function ClassificationPage() {
  const { t } = useTranslation()
  const { data: rulesData, isLoading } = useClassificationRules()
  const { data: siteTypesData } = useSiteTypes()
  const createRule = useCreateClassificationRule()
  const updateRule = useUpdateClassificationRule()
  const deleteRule = useDeleteClassificationRule()

  const rules: ClassificationRule[] = useMemo(
    () => rulesData?.data ?? rulesData ?? [],
    [rulesData],
  )
  const siteTypes: SiteType[] = useMemo(
    () => siteTypesData?.data ?? siteTypesData ?? [],
    [siteTypesData],
  )
  const siteTypeLabels = useMemo(
    () => Object.fromEntries(siteTypes.map((st) => [String(st.id), st.name])),
    [siteTypes],
  )

  const [open, setOpen] = useState(false)
  const [ruleType, setRuleType] = useState('domain')
  const [pattern, setPattern] = useState('')
  const [siteTypeId, setSiteTypeId] = useState<string>('')
  const [priority, setPriority] = useState('0')

  const [formError, setFormError] = useState<string | null>(null)
  const [editFormError, setEditFormError] = useState<string | null>(null)

  const [editDialogOpen, setEditDialogOpen] = useState(false)
  const [editRule, setEditRule] = useState<{
    id: number
    rule_type: string
    pattern: string
    site_type_id: string
    priority: string
  }>({ id: 0, rule_type: 'domain', pattern: '', site_type_id: '', priority: '0' })

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setFormError(null)
      try {
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
      } catch (err) {
        setFormError(parseApiError(err))
      }
    },
    [ruleType, pattern, siteTypeId, priority, createRule],
  )

  const handleOpenEdit = useCallback((rule: ClassificationRule) => {
    setEditRule({
      id: rule.id,
      rule_type: rule.rule_type,
      pattern: rule.pattern,
      site_type_id: String(rule.siteType?.id ?? rule.site_type?.id ?? ''),
      priority: String(rule.priority),
    })
    setEditDialogOpen(true)
  }, [])

  const handleEdit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setEditFormError(null)
      try {
        await updateRule.mutateAsync({
          id: editRule.id,
          rule_type: editRule.rule_type,
          pattern: editRule.pattern,
          site_type_id: Number(editRule.site_type_id),
          priority: Number(editRule.priority),
        })
        setEditDialogOpen(false)
      } catch (err) {
        setEditFormError(parseApiError(err))
      }
    },
    [editRule, updateRule],
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
                {formError && <p className="text-sm text-destructive">{formError}</p>}
                <form onSubmit={handleCreate} className="space-y-4">
                  <div className="space-y-2">
                    <Label>{t('classification.ruleType')}</Label>
                    <Select
                      value={ruleType}
                      onValueChange={(v: string | null) =>
                        setRuleType(v ?? 'domain')
                      }
                    >
                      <SelectTrigger className="w-full">
                        <SelectValue labels={{ 'domain': t('classification.domain'), 'url_pattern': t('classification.urlPattern'), 'regex': t('classification.regex') }} />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="domain" label={t('classification.domain')}>{t('classification.domain')}</SelectItem>
                        <SelectItem value="url_pattern" label={t('classification.urlPattern')}>
                          {t('classification.urlPattern')}
                        </SelectItem>
                        <SelectItem value="regex" label={t('classification.regex')}>{t('classification.regex')}</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>{t('classification.pattern')}</Label>
                    <Input
                      value={pattern}
                      onChange={(e) => setPattern(e.target.value)}
                      placeholder="ozon.ru"
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
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={t('classification.selectType')} labels={siteTypeLabels} />
                      </SelectTrigger>
                      <SelectContent>
                        {siteTypes.map((st) => (
                          <SelectItem key={st.id} value={String(st.id)} label={st.name}>
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
                    {(rule.siteType ?? rule.site_type) ? (
                      <SiteTypeBadge type={rule.siteType ?? rule.site_type ?? null} />
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
                      <div className="flex gap-2">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => handleOpenEdit(rule)}
                        >
                          {t('common.edit')}
                        </Button>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => deleteRule.mutate(rule.id)}
                          disabled={deleteRule.isPending}
                        >
                          {t('classification.delete')}
                        </Button>
                      </div>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}

        <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t('classification.editClassificationRule')}</DialogTitle>
            </DialogHeader>
            {editFormError && <p className="text-sm text-destructive">{editFormError}</p>}
            <form onSubmit={handleEdit} className="space-y-4">
              <div className="space-y-2">
                <Label>{t('classification.ruleType')}</Label>
                <Select
                  value={editRule.rule_type}
                  onValueChange={(v: string | null) =>
                    setEditRule((prev) => ({ ...prev, rule_type: v ?? 'domain' }))
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue labels={{ 'domain': t('classification.domain'), 'url_pattern': t('classification.urlPattern'), 'regex': t('classification.regex') }} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="domain" label={t('classification.domain')}>{t('classification.domain')}</SelectItem>
                    <SelectItem value="url_pattern" label={t('classification.urlPattern')}>
                      {t('classification.urlPattern')}
                    </SelectItem>
                    <SelectItem value="regex" label={t('classification.regex')}>{t('classification.regex')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label>{t('classification.pattern')}</Label>
                <Input
                  value={editRule.pattern}
                  onChange={(e) =>
                    setEditRule((prev) => ({ ...prev, pattern: e.target.value }))
                  }
                  placeholder="ozon.ru"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label>{t('classification.siteType')}</Label>
                <Select
                  value={editRule.site_type_id}
                  onValueChange={(v: string | null) =>
                    setEditRule((prev) => ({ ...prev, site_type_id: v ?? '' }))
                  }
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder={t('classification.selectType')} labels={siteTypeLabels} />
                  </SelectTrigger>
                  <SelectContent>
                    {siteTypes.map((st) => (
                      <SelectItem key={st.id} value={String(st.id)} label={st.name}>
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
                  value={editRule.priority}
                  onChange={(e) =>
                    setEditRule((prev) => ({ ...prev, priority: e.target.value }))
                  }
                />
              </div>
              <DialogFooter>
                <Button type="submit" disabled={updateRule.isPending}>
                  {updateRule.isPending ? t('common.saving') : t('common.save')}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </AppLayout>
  )
}
