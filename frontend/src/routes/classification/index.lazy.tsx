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
import { Tags, Plus, Pencil, Trash2, Globe } from 'lucide-react'

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
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight flex items-center gap-2">
              <Tags className="h-5 w-5 text-accent-blue" />
              {t('classification.title')}
            </h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Правила классификации доменов по типам сайтов</p>
          </div>
          <div className="flex gap-2">
            <Link to="/classification/domains">
              <Button variant="outline" size="sm" className="text-[12px] hover:border-accent-blue hover:text-accent-blue">
                <Globe className="h-3.5 w-3.5 mr-1" />
                {t('classification.domains')}
              </Button>
            </Link>
            <Dialog open={open} onOpenChange={setOpen}>
              <DialogTrigger render={<Button size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" />}>
                <Plus className="h-3.5 w-3.5 mr-1" />
                {t('classification.addRule')}
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle className="text-[15px]">{t('classification.addClassificationRule')}</DialogTitle>
                </DialogHeader>
                {formError && <p className="text-[12px] text-destructive">{formError}</p>}
                <form onSubmit={handleCreate} className="space-y-3">
                  <div className="space-y-1">
                    <Label className="text-[11px]">{t('classification.ruleType')}</Label>
                    <Select
                      value={ruleType}
                      onValueChange={(v: string | null) =>
                        setRuleType(v ?? 'domain')
                      }
                    >
                      <SelectTrigger className="w-full h-8">
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
                  <div className="space-y-1">
                    <Label className="text-[11px]">{t('classification.pattern')}</Label>
                    <Input
                      className="h-8"
                      value={pattern}
                      onChange={(e) => setPattern(e.target.value)}
                      placeholder="ozon.ru"
                      required
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-[11px]">{t('classification.siteType')}</Label>
                    <Select
                      value={siteTypeId}
                      onValueChange={(v: string | null) =>
                        setSiteTypeId(v ?? '')
                      }
                    >
                      <SelectTrigger className="w-full h-8">
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
                  <div className="space-y-1">
                    <Label className="text-[11px]">{t('classification.priority')}</Label>
                    <Input
                      className="h-8"
                      type="number"
                      value={priority}
                      onChange={(e) => setPriority(e.target.value)}
                    />
                  </div>
                  <DialogFooter>
                    <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={createRule.isPending}>
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
          <div className="glass-card rounded-lg overflow-hidden">
            <Table className="compact-table">
              <TableHeader>
                <TableRow>
                  <TableHead className="text-[11px]">{t('classification.ruleType')}</TableHead>
                  <TableHead className="text-[11px]">{t('classification.pattern')}</TableHead>
                  <TableHead className="text-[11px]">{t('classification.siteType')}</TableHead>
                  <TableHead className="text-[11px]">{t('classification.priority')}</TableHead>
                  <TableHead className="text-[11px]">{t('classification.system')}</TableHead>
                  <TableHead className="text-[11px]">{t('common.actions')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rules.map((rule) => (
                  <TableRow key={rule.id}>
                    <TableCell>
                      <Badge variant="outline" className="text-[10px]">{rule.rule_type}</Badge>
                    </TableCell>
                    <TableCell className="font-mono text-[12px]">
                      {rule.pattern}
                    </TableCell>
                    <TableCell>
                      {(rule.siteType ?? rule.site_type) ? (
                        <SiteTypeBadge type={rule.siteType ?? rule.site_type ?? null} />
                      ) : (
                        '-'
                      )}
                    </TableCell>
                    <TableCell className="text-[12px]">{rule.priority}</TableCell>
                    <TableCell>
                      {rule.is_system ? (
                        <Badge variant="secondary" className="text-[10px]">{t('classification.system')}</Badge>
                      ) : (
                        '-'
                      )}
                    </TableCell>
                    <TableCell>
                      {!rule.is_system && (
                        <div className="flex gap-1">
                          <Button
                            variant="outline"
                            size="xs"
                            className="text-[11px] hover:border-accent-blue hover:text-accent-blue"
                            onClick={() => handleOpenEdit(rule)}
                          >
                            <Pencil className="h-3 w-3 mr-1" />
                            {t('common.edit')}
                          </Button>
                          <Button
                            variant="outline"
                            size="xs"
                            className="text-[11px] text-destructive hover:text-destructive"
                            onClick={() => deleteRule.mutate(rule.id)}
                            disabled={deleteRule.isPending}
                          >
                            <Trash2 className="h-3 w-3" />
                          </Button>
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        )}

        <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle className="text-[15px]">{t('classification.editClassificationRule')}</DialogTitle>
            </DialogHeader>
            {editFormError && <p className="text-[12px] text-destructive">{editFormError}</p>}
            <form onSubmit={handleEdit} className="space-y-3">
              <div className="space-y-1">
                <Label className="text-[11px]">{t('classification.ruleType')}</Label>
                <Select
                  value={editRule.rule_type}
                  onValueChange={(v: string | null) =>
                    setEditRule((prev) => ({ ...prev, rule_type: v ?? 'domain' }))
                  }
                >
                  <SelectTrigger className="w-full h-8">
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
              <div className="space-y-1">
                <Label className="text-[11px]">{t('classification.pattern')}</Label>
                <Input
                  className="h-8"
                  value={editRule.pattern}
                  onChange={(e) =>
                    setEditRule((prev) => ({ ...prev, pattern: e.target.value }))
                  }
                  placeholder="ozon.ru"
                  required
                />
              </div>
              <div className="space-y-1">
                <Label className="text-[11px]">{t('classification.siteType')}</Label>
                <Select
                  value={editRule.site_type_id}
                  onValueChange={(v: string | null) =>
                    setEditRule((prev) => ({ ...prev, site_type_id: v ?? '' }))
                  }
                >
                  <SelectTrigger className="w-full h-8">
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
              <div className="space-y-1">
                <Label className="text-[11px]">{t('classification.priority')}</Label>
                <Input
                  className="h-8"
                  type="number"
                  value={editRule.priority}
                  onChange={(e) =>
                    setEditRule((prev) => ({ ...prev, priority: e.target.value }))
                  }
                />
              </div>
              <DialogFooter>
                <Button type="submit" size="sm" className="bg-[#155dfc] hover:bg-[#1249d6]" disabled={updateRule.isPending}>
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
