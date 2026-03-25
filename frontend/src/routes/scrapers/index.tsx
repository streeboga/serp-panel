import { createFileRoute, redirect } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import {
  useScrapers, useCreateScraper, useDeleteScraper, useTestScraper,
} from '@/hooks/useScrapers'
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

export const Route = createFileRoute('/scrapers/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ScrapersPage,
})

function ScrapersPage() {
  const { data: scrapersData, isLoading } = useScrapers()
  const createScraper = useCreateScraper()
  const deleteScraper = useDeleteScraper()
  const testScraper = useTestScraper()

  const scrapers = scrapersData?.data ?? scrapersData ?? []

  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [type, setType] = useState('xml_river')
  const [baseUrl, setBaseUrl] = useState('')
  const [engines, setEngines] = useState('google')
  const [rateLimit, setRateLimit] = useState('10')

  const [testResults, setTestResults] = useState<Record<number, { status: string; message?: string }>>({})

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    await createScraper.mutateAsync({
      name,
      type,
      base_url: baseUrl,
      engines: engines.split(',').map(e => e.trim()),
      rate_limit: Number(rateLimit),
      is_active: true,
    })
    setName('')
    setBaseUrl('')
    setOpen(false)
  }

  const handleTest = async (id: number) => {
    setTestResults(prev => ({ ...prev, [id]: { status: 'testing' } }))
    try {
      const result = await testScraper.mutateAsync(id)
      setTestResults(prev => ({ ...prev, [id]: { status: 'success', message: result.message ?? 'OK' } }))
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : 'Test failed'
      setTestResults(prev => ({ ...prev, [id]: { status: 'error', message: msg } }))
    }
  }

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Scrapers</h1>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>Add Scraper</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Add Scraper</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="space-y-2">
                  <Label>Name</Label>
                  <Input value={name} onChange={(e) => setName(e.target.value)} required />
                </div>
                <div className="space-y-2">
                  <Label>Type</Label>
                  <Select value={type} onValueChange={(v: string | null) => setType(v ?? 'xml_river')}>
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
                  <Label>Base URL</Label>
                  <Input value={baseUrl} onChange={(e) => setBaseUrl(e.target.value)} required />
                </div>
                <div className="space-y-2">
                  <Label>Engines (comma-separated)</Label>
                  <Input value={engines} onChange={(e) => setEngines(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label>Rate Limit (req/min)</Label>
                  <Input type="number" value={rateLimit} onChange={(e) => setRateLimit(e.target.value)} />
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={createScraper.isPending}>
                    {createScraper.isPending ? 'Creating...' : 'Create'}
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
                <TableHead>Name</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Base URL</TableHead>
                <TableHead>Engines</TableHead>
                <TableHead>Rate Limit</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {Array.isArray(scrapers) && scrapers.length > 0 ? scrapers.map((scraper: {
                id: number
                name: string
                type: string
                base_url: string
                engines: string[]
                rate_limit: number
                is_active: boolean
              }) => (
                <TableRow key={scraper.id}>
                  <TableCell className="font-medium">{scraper.name}</TableCell>
                  <TableCell><Badge variant="outline">{scraper.type}</Badge></TableCell>
                  <TableCell className="max-w-xs truncate">{scraper.base_url}</TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      {(scraper.engines ?? []).map((eng: string) => (
                        <Badge key={eng} variant="secondary">{eng}</Badge>
                      ))}
                    </div>
                  </TableCell>
                  <TableCell>{scraper.rate_limit}/min</TableCell>
                  <TableCell>
                    <Badge variant={scraper.is_active ? 'default' : 'secondary'}>
                      {scraper.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleTest(scraper.id)}
                        disabled={testResults[scraper.id]?.status === 'testing'}
                      >
                        {testResults[scraper.id]?.status === 'testing' ? 'Testing...' : 'Test'}
                      </Button>
                      <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => deleteScraper.mutate(scraper.id)}
                      >
                        Delete
                      </Button>
                    </div>
                    {testResults[scraper.id] && testResults[scraper.id].status !== 'testing' && (
                      <p className={`text-xs mt-1 ${testResults[scraper.id].status === 'success' ? 'text-green-600' : 'text-red-600'}`}>
                        {testResults[scraper.id].message}
                      </p>
                    )}
                  </TableCell>
                </TableRow>
              )) : (
                <TableRow>
                  <TableCell colSpan={7} className="text-center text-muted-foreground">
                    No scrapers configured
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
