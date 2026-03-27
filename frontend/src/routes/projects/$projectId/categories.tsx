import { createFileRoute } from '@tanstack/react-router'
import { useState, useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
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
        <h2 className="text-base font-semibold tracking-tight">{t('projects.categoriesTab')}</h2>
        <Button size="sm" onClick={() => setCatDialogOpen(true)}>+ Категория</Button>
      </div>

      {categories.length === 0 ? (
        <EmptyState title="Категорий пока нет" />
      ) : (
        <div className="space-y-2">
          {categories.map((cat) => (
            <div key={cat.id} className="glass-card rounded-lg p-3">
              <div className="flex items-center justify-between mb-2">
                <h3 className="text-[13px] font-semibold">{cat.name}</h3>
                <div className="flex gap-1.5">
                  <Button
                    variant="outline"
                    size="xs"
                    onClick={() => {
                      setClusterCategoryId(cat.id)
                      setClusterDialogOpen(true)
                    }}
                  >
                    + Кластер
                  </Button>
                  <Button
                    variant="ghost"
                    size="xs"
                    className="text-destructive"
                    onClick={() => {
                      if (confirm('Удалить категорию и все кластеры?')) {
                        deleteCategory.mutate(cat.id)
                      }
                    }}
                  >
                    Удалить
                  </Button>
                </div>
              </div>
              {cat.clusters && cat.clusters.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  {cat.clusters.map((cluster: Cluster) => (
                    <Badge
                      key={cluster.id}
                      variant="secondary"
                      className="text-[11px] py-0.5 px-2 gap-1.5"
                    >
                      {cluster.name}
                      <button
                        className="text-destructive/60 hover:text-destructive"
                        onClick={() => {
                          if (confirm('Удалить кластер?')) {
                            deleteCluster.mutate(cluster.id)
                          }
                        }}
                      >
                        x
                      </button>
                    </Badge>
                  ))}
                </div>
              )}
            </div>
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
