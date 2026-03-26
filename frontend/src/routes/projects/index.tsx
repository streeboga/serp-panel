import { createFileRoute, redirect, Link } from '@tanstack/react-router'
import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { AppLayout } from '@/components/AppLayout'
import { EmptyState } from '@/components/EmptyState'
import { CardGridSkeleton } from '@/components/PageSkeleton'
import { useProjects, useCreateProject } from '@/hooks/useProjects'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card'
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
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">{t('projects.title')}</h1>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>{t('projects.newProject')}</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>{t('projects.createProject')}</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="project-name">{t('projects.name')}</Label>
                  <Input
                    id="project-name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="project-desc">{t('projects.description')}</Label>
                  <Input
                    id="project-desc"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                  />
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={createProject.isPending}>
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
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {projectList.map((project) => (
              <Link
                key={project.id}
                to="/projects/$projectId"
                params={{ projectId: String(project.id) }}
              >
                <Card className="hover:ring-2 hover:ring-primary/20 transition-all cursor-pointer">
                  <CardHeader>
                    <CardTitle>{project.name}</CardTitle>
                    {project.description && (
                      <CardDescription>{project.description}</CardDescription>
                    )}
                  </CardHeader>
                  <CardContent>
                    <div className="flex gap-4 text-sm text-muted-foreground">
                      <span>{project.domains_count ?? 0} {t('projects.domains')}</span>
                      <span>{project.keywords_count ?? 0} {t('projects.keywords')}</span>
                    </div>
                  </CardContent>
                </Card>
              </Link>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  )
}
