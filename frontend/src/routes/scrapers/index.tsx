import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import {
  useScrapers,
  useCreateScraper,
  useDeleteScraper,
  useTestScraper,
} from '@/hooks/useScrapers'
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
import type { Scraper } from '@/types/api'

export const Route = createFileRoute('/scrapers/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ScrapersPage,
})

function ScrapersPage() {
  const { t } = useTranslation()
  const { data: scrapersData, isLoading } = useScrapers()
  const createScraper = useCreateScraper()
  const deleteScraper = useDeleteScraper()
  const testScraper = useTestScraper()

  const scrapers: Scraper[] = useMemo(
    () => scrapersData?.data ?? scrapersData ?? [],
    [scrapersData],
  )

  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [type, setType] = useState('xml_river')
  const [baseUrl, setBaseUrl] = useState('')
  const [engines, setEngines] = useState('google')
  const [rateLimit, setRateLimit] = useState('10')

  const [testResults, setTestResults] = useState<
    Record<number, { status: string; message?: string }>
  >({})

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      await createScraper.mutateAsync({
        name,
        type,
        base_url: baseUrl,
        engines: engines.split(',').map((e) => e.trim()),
        rate_limit: Number(rateLimit),
        is_active: true,
      })
      setName('')
      setBaseUrl('')
      setOpen(false)
    },
    [name, type, baseUrl, engines, rateLimit, createScraper],
  )

  const handleTest = useCallback(
    async (id: number) => {
      setTestResults((prev) => ({ ...prev, [id]: { status: 'testing' } }))
      try {
        const result = await testScraper.mutateAsync(id)
        setTestResults((prev) => ({
          ...prev,
          [id]: { status: 'success', message: result.message ?? 'OK' },
        }))
      } catch (err: unknown) {
        const msg = err instanceof Error ? err.message : 'Test failed'
        setTestResults((prev) => ({
          ...prev,
          [id]: { status: 'error', message: msg },
        }))
      }
    },
    [testScraper],
  )

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('scrapers.title')}</h1>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>{t('scrapers.addScraper')}</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{t('scrapers.addScraper')}</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="space-y-2">
                  <Label>{t('scrapers.name')}</Label>
                  <Input
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('scrapers.type')}</Label>
                  <Select
                    value={type}
                    onValueChange={(v: string | null) =>
                      setType(v ?? 'xml_river')
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="xml_river">XML River</SelectItem>
                      <SelectItem value="serp_api">SERP API</SelectItem>
                      <SelectItem value="custom">Custom</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label>{t('scrapers.baseUrl')}</Label>
                  <Input
                    value={baseUrl}
                    onChange={(e) => setBaseUrl(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('scrapers.enginesComma')}</Label>
                  <Input
                    value={engines}
                    onChange={(e) => setEngines(e.target.value)}
                  />
                </div>
                <div className="space-y-2">
                  <Label>{t('scrapers.rateLimit')} ({t('scrapers.rateLimitUnit')})</Label>
                  <Input
                    type="number"
                    value={rateLimit}
                    onChange={(e) => setRateLimit(e.target.value)}
                  />
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={createScraper.isPending}>
                    {createScraper.isPending ? t('scrapers.creating') : t('scrapers.create')}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {isLoading ? (
          <TableSkeleton rows={4} />
        ) : scrapers.length === 0 ? (
          <EmptyState title={t('scrapers.noScrapers')} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('scrapers.name')}</TableHead>
                <TableHead>{t('scrapers.type')}</TableHead>
                <TableHead>{t('scrapers.baseUrl')}</TableHead>
                <TableHead>{t('scrapers.engines')}</TableHead>
                <TableHead>{t('scrapers.rateLimit')}</TableHead>
                <TableHead>{t('scrapers.status')}</TableHead>
                <TableHead>{t('scrapers.actions')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {scrapers.map((scraper) => (
                <TableRow key={scraper.id}>
                  <TableCell className="font-medium">
                    {scraper.name}
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{scraper.type}</Badge>
                  </TableCell>
                  <TableCell className="max-w-xs truncate">
                    {scraper.base_url}
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      {(scraper.engines ?? []).map((eng) => (
                        <Badge key={eng} variant="secondary">
                          {eng}
                        </Badge>
                      ))}
                    </div>
                  </TableCell>
                  <TableCell>{scraper.rate_limit}/{t('scrapers.rateLimitUnit').split('/')[0] === 'req' ? 'min' : t('scrapers.rateLimitUnit').split('/')[1]}</TableCell>
                  <TableCell>
                    <Badge
                      variant={scraper.is_active ? 'default' : 'secondary'}
                    >
                      {scraper.is_active ? t('scrapers.active') : t('scrapers.inactive')}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleTest(scraper.id)}
                        disabled={
                          testResults[scraper.id]?.status === 'testing'
                        }
                      >
                        {testResults[scraper.id]?.status === 'testing'
                          ? t('scrapers.testing')
                          : t('scrapers.test')}
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteScraper.mutate(scraper.id)}
                      >
                        {t('scrapers.delete')}
                      </Button>
                    </div>
                    {testResults[scraper.id] &&
                      testResults[scraper.id].status !== 'testing' && (
                        <p
                          className={`text-xs mt-1 ${
                            testResults[scraper.id].status === 'success'
                              ? 'text-green-600'
                              : 'text-destructive'
                          }`}
                        >
                          {testResults[scraper.id].message}
                        </p>
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
