import { createFileRoute, redirect, Link } from '@tanstack/react-router'
import { AppLayout } from '@/components/AppLayout'
import { useDomainClassifications } from '@/hooks/useClassification'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'

export const Route = createFileRoute('/classification/domains')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: DomainsClassificationPage,
})

function DomainsClassificationPage() {
  const { data, isLoading } = useDomainClassifications()
  const domains = data?.data ?? data ?? []

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Link to="/classification">
              <Button variant="outline" size="sm">Back to Rules</Button>
            </Link>
            <h1 className="text-2xl font-bold">Domain Classifications</h1>
          </div>
        </div>

        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Domain</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Classified By</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {Array.isArray(domains) && domains.length > 0 ? domains.map((item: {
                id: number
                domain: string
                site_type?: { name: string; color: string } | null
                classified_by?: string
              }) => (
                <TableRow key={item.id}>
                  <TableCell className="font-medium">{item.domain}</TableCell>
                  <TableCell>
                    {item.site_type ? (
                      <Badge style={{ backgroundColor: item.site_type.color, color: 'white' }}>
                        {item.site_type.name}
                      </Badge>
                    ) : '-'}
                  </TableCell>
                  <TableCell>
                    {item.classified_by ? (
                      <Badge variant="outline">{item.classified_by}</Badge>
                    ) : '-'}
                  </TableCell>
                </TableRow>
              )) : (
                <TableRow>
                  <TableCell colSpan={3} className="text-center text-muted-foreground">
                    No domain classifications yet
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
