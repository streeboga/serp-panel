import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import {
  useClassificationRules, useCreateClassificationRule,
  useDeleteClassificationRule, useSiteTypes,
} from '@/hooks/useClassification'
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
import { Link } from '@tanstack/react-router'

export const Route = createFileRoute('/classification/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ClassificationPage,
})

function ClassificationPage() {
  const { data: rulesData, isLoading } = useClassificationRules()
  const { data: siteTypesData } = useSiteTypes()
  const createRule = useCreateClassificationRule()
  const deleteRule = useDeleteClassificationRule()

  const rules = rulesData?.data ?? rulesData ?? []
  const siteTypes = siteTypesData?.data ?? siteTypesData ?? []

  const [open, setOpen] = useState(false)
  const [ruleType, setRuleType] = useState('domain')
  const [pattern, setPattern] = useState('')
  const [siteTypeId, setSiteTypeId] = useState<string>('')
  const [priority, setPriority] = useState('0')

  const handleCreate = async (e: React.FormEvent) => {
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
  }

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Classification Rules</h1>
          <div className="flex gap-2">
            <Link to="/classification/domains">
              <Button variant="outline">Domains</Button>
            </Link>
            <Dialog open={open} onOpenChange={setOpen}>
              <DialogTrigger render={<Button />}>Add Rule</DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Add Classification Rule</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleCreate} className="space-y-4">
                  <div className="space-y-2">
                    <Label>Rule Type</Label>
                    <Select value={ruleType} onValueChange={(v: string | null) => setRuleType(v ?? 'domain')}>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="domain">Domain</SelectItem>
                        <SelectItem value="url_pattern">URL Pattern</SelectItem>
                        <SelectItem value="regex">Regex</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Pattern</Label>
                    <Input value={pattern} onChange={(e) => setPattern(e.target.value)} required />
                  </div>
                  <div className="space-y-2">
                    <Label>Site Type</Label>
                    <Select value={siteTypeId} onValueChange={(v: string | null) => setSiteTypeId(v ?? '')}>
                      <SelectTrigger>
                        <SelectValue placeholder="Select type" />
                      </SelectTrigger>
                      <SelectContent>
                        {Array.isArray(siteTypes) && siteTypes.map((t: { id: number; name: string }) => (
                          <SelectItem key={t.id} value={String(t.id)}>{t.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Priority</Label>
                    <Input type="number" value={priority} onChange={(e) => setPriority(e.target.value)} />
                  </div>
                  <DialogFooter>
                    <Button type="submit" disabled={createRule.isPending}>
                      {createRule.isPending ? 'Creating...' : 'Create'}
                    </Button>
                  </DialogFooter>
                </form>
              </DialogContent>
            </Dialog>
          </div>
        </div>

        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Rule Type</TableHead>
                <TableHead>Pattern</TableHead>
                <TableHead>Site Type</TableHead>
                <TableHead>Priority</TableHead>
                <TableHead>System</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {Array.isArray(rules) && rules.length > 0 ? rules.map((rule: {
                id: number
                rule_type: string
                pattern: string
                site_type?: { name: string; color: string }
                priority: number
                is_system: boolean
              }) => (
                <TableRow key={rule.id}>
                  <TableCell>
                    <Badge variant="outline">{rule.rule_type}</Badge>
                  </TableCell>
                  <TableCell className="font-mono text-sm">{rule.pattern}</TableCell>
                  <TableCell>
                    {rule.site_type ? (
                      <Badge style={{ backgroundColor: rule.site_type.color, color: 'white' }}>
                        {rule.site_type.name}
                      </Badge>
                    ) : '-'}
                  </TableCell>
                  <TableCell>{rule.priority}</TableCell>
                  <TableCell>
                    {rule.is_system ? <Badge variant="secondary">System</Badge> : '-'}
                  </TableCell>
                  <TableCell>
                    {!rule.is_system && (
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteRule.mutate(rule.id)}
                        disabled={deleteRule.isPending}
                      >
                        Delete
                      </Button>
                    )}
                  </TableCell>
                </TableRow>
              )) : (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-muted-foreground">
                    No classification rules
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
