import { createFileRoute, redirect, Link } from '@tanstack/react-router'
import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { CardGridSkeleton } from '@/components/PageSkeleton'
import { useProjects, useCreateProject } from '@/hooks/useProjects'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
  DialogFooter,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Plus, Globe, KeyRound, ChevronRight } from 'lucide-react'
import type { Project } from '@/types/api'

export const Route = createFileRoute('/projects/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ProjectsPage,
})

function ProjectsPage() {
  const { t } = useTranslation()
  const { data: projects, isLoading } = useProjects()
  const createProject = useCreateProject()
  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')

  const projectList: Project[] = Array.isArray(projects)
    ? projects
    : (projects?.data ?? [])

  const handleCreate = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      await createProject.mutateAsync({
        name,
        description: description || undefined,
      })
      setName('')
      setDescription('')
      setOpen(false)
    },
    [name, description, createProject],
  )

  return (
    <AppLayout>
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-xl font-bold tracking-tight">{t('projects.title')}</h1>
            <p className="text-[13px] text-muted-foreground mt-0.5">Управление проектами мониторинга</p>
          </div>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button size="sm" />}>
              <Plus className="size-3.5 mr-1" />{t('projects.newProject')}
            </DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{t('projects.createProject')}</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-3">
                <div className="space-y-1.5">
                  <Label htmlFor="project-name" className="text-[12px]">{t('projects.name')}</Label>
                  <Input
                    id="project-name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                    className="h-8"
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="project-desc" className="text-[12px]">{t('projects.description')}</Label>
                  <Input
                    id="project-desc"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    className="h-8"
                  />
                </div>
                <DialogFooter>
                  <Button type="submit" size="sm" disabled={createProject.isPending}>
                    {createProject.isPending ? t('projects.creating') : t('projects.create')}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {isLoading ? (
          <CardGridSkeleton count={6} />
        ) : projectList.length === 0 ? (
          <EmptyState
            title={t('projects.noProjects')}
          />
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            {projectList.map((project) => (
              <Link
                key={project.id}
                to="/projects/$projectId"
                params={{ projectId: String(project.id) }}
              >
                <div className="bg-card rounded-lg p-4 transition-all hover:bg-accent-blue/5 cursor-pointer group">
                  <div className="flex items-start justify-between mb-3">
                    <h3 className="text-sm font-semibold group-hover:text-accent-blue transition-colors">{project.name}</h3>
                    <ChevronRight className="size-4 text-muted-foreground/40 group-hover:text-accent-blue transition-colors" />
                  </div>
                  {project.description && (
                    <p className="text-[12px] text-muted-foreground mb-3 line-clamp-2">{project.description}</p>
                  )}
                  <div className="flex gap-4 text-[11px] text-muted-foreground">
                    <span className="flex items-center gap-1"><Globe className="size-3" />{project.domains_count ?? 0} {t('projects.domains')}</span>
                    <span className="flex items-center gap-1"><KeyRound className="size-3" />{project.keywords_count ?? 0} {t('projects.keywords')}</span>
                  </div>
                </div>
              </Link>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  )
}
