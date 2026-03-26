import { createFileRoute } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/components/ui/dialog'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import { useDomains } from '@/hooks/useDomains'
import { useCategories, useCreateCategory, useDeleteCategory } from '@/hooks/useCategories'
import { useCreateCluster, useDeleteCluster } from '@/hooks/useClusters'
import type { Category, Cluster, Domain } from '@/types/api'

export const Route = createFileRoute('/projects/$projectId/categories')({
  component: CategoriesPage,
})

function CategoriesPage() {
  const { projectId } = Route.useParams()
  const { data: domainsData, isLoading: domainsLoading } = useDomains(projectId)
  const domains: Domain[] = useMemo(
    () => {
      const d = domainsData?.data ?? domainsData
      return Array.isArray(d) ? d : []
    },
    [domainsData],
  )

  const ownDomain = domains.find((d) => d.is_own)

  if (domainsLoading) return <TableSkeleton rows={5} />
  if (!ownDomain) {
    return <EmptyState title="Add an own domain first to manage categories" />
  }

  return <DomainCategories domainId={String(ownDomain.id)} />
}

function DomainCategories({ domainId }: { domainId: string }) {
  const { t } = useTranslation()
  const { data: categoriesData, isLoading } = useCategories(domainId)
  const createCategory = useCreateCategory()
  const deleteCategory = useDeleteCategory()
  const createCluster = useCreateCluster()
  const deleteCluster = useDeleteCluster()

  const categories: Category[] = useMemo(() => {
    const d = categoriesData?.data ?? categoriesData
    return Array.isArray(d) ? d : []
  }, [categoriesData])

  const [catDialogOpen, setCatDialogOpen] = useState(false)
  const [catName, setCatName] = useState('')
  const [clusterDialogOpen, setClusterDialogOpen] = useState(false)
  const [clusterCategoryId, setClusterCategoryId] = useState<number | null>(null)
  const [clusterName, setClusterName] = useState('')

  const handleCreateCategory = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!catName.trim()) return
      await createCategory.mutateAsync({
        domain_id: Number(domainId),
        name: catName.trim(),
      })
      setCatName('')
      setCatDialogOpen(false)
    },
    [catName, domainId, createCategory],
  )

  const handleCreateCluster = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!clusterName.trim() || !clusterCategoryId) return
      await createCluster.mutateAsync({
        category_id: clusterCategoryId,
        name: clusterName.trim(),
      })
      setClusterName('')
      setClusterDialogOpen(false)
    },
    [clusterName, clusterCategoryId, createCluster],
  )

  if (isLoading) return <TableSkeleton rows={5} />

  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h2 className="text-lg font-semibold">{t('projects.categoriesTab')}</h2>
        <Button onClick={() => setCatDialogOpen(true)}>Add Category</Button>
      </div>

      {categories.length === 0 ? (
        <EmptyState title="No categories yet" />
      ) : (
        <div className="space-y-3">
          {categories.map((cat) => (
            <Card key={cat.id}>
              <CardHeader className="py-3 px-4">
                <div className="flex items-center justify-between">
                  <CardTitle className="text-base">{cat.name}</CardTitle>
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setClusterCategoryId(cat.id)
                        setClusterDialogOpen(true)
                      }}
                    >
                      + Cluster
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      className="text-destructive"
                      onClick={() => {
                        if (confirm('Delete this category and all its clusters?')) {
                          deleteCategory.mutate(cat.id)
                        }
                      }}
                    >
                      Delete
                    </Button>
                  </div>
                </div>
              </CardHeader>
              {cat.clusters && cat.clusters.length > 0 && (
                <CardContent className="py-2 px-4">
                  <div className="flex flex-wrap gap-2">
                    {cat.clusters.map((cluster: Cluster) => (
                      <Badge
                        key={cluster.id}
                        variant="secondary"
                        className="text-sm py-1 px-3 gap-2"
                      >
                        {cluster.name}
                        <button
                          className="ml-1 text-destructive hover:text-destructive/80"
                          onClick={() => {
                            if (confirm('Delete this cluster?')) {
                              deleteCluster.mutate(cluster.id)
                            }
                          }}
                        >
                          x
                        </button>
                      </Badge>
                    ))}
                  </div>
                </CardContent>
              )}
            </Card>
          ))}
        </div>
      )}

      <Dialog open={catDialogOpen} onOpenChange={setCatDialogOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Add Category</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCreateCategory} className="space-y-4">
            <div className="space-y-2">
              <Label>Name</Label>
              <Input
                value={catName}
                onChange={(e) => setCatName(e.target.value)}
                required
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={createCategory.isPending}>
                {createCategory.isPending ? 'Creating...' : 'Create'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={clusterDialogOpen} onOpenChange={setClusterDialogOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Add Cluster</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleCreateCluster} className="space-y-4">
            <div className="space-y-2">
              <Label>Name</Label>
              <Input
                value={clusterName}
                onChange={(e) => setClusterName(e.target.value)}
                required
              />
            </div>
            <DialogFooter>
              <Button type="submit" disabled={createCluster.isPending}>
                {createCluster.isPending ? 'Creating...' : 'Create'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
