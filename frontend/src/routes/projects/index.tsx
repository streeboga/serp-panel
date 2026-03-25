import { createFileRoute, redirect, Link } from '@tanstack/react-router'
import { useState } from 'react'
import { AppLayout } from '@/components/AppLayout'
import { useProjects, useCreateProject } from '@/hooks/useProjects'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger, DialogFooter,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export const Route = createFileRoute('/projects/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: ProjectsPage,
})

function ProjectsPage() {
  const { data: projects, isLoading } = useProjects()
  const createProject = useCreateProject()
  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')

  const projectList = Array.isArray(projects) ? projects : projects?.data ?? []

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault()
    await createProject.mutateAsync({ name, description: description || undefined })
    setName('')
    setDescription('')
    setOpen(false)
  }

  return (
    <AppLayout>
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h1 className="text-2xl font-bold">Projects</h1>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button />}>New Project</DialogTrigger>
            <DialogContent>
              <DialogHeader>
                <DialogTitle>Create Project</DialogTitle>
              </DialogHeader>
              <form onSubmit={handleCreate} className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="project-name">Name</Label>
                  <Input
                    id="project-name"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                  />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="project-desc">Description</Label>
                  <Input
                    id="project-desc"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                  />
                </div>
                <DialogFooter>
                  <Button type="submit" disabled={createProject.isPending}>
                    {createProject.isPending ? 'Creating...' : 'Create'}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>
        </div>

        {isLoading ? (
          <p className="text-muted-foreground">Loading...</p>
        ) : projectList.length === 0 ? (
          <p className="text-muted-foreground">No projects yet. Create one to get started.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {projectList.map((project: { id: number; name: string; description?: string; domains_count?: number; keywords_count?: number }) => (
              <Link key={project.id} to="/projects/$projectId" params={{ projectId: String(project.id) }}>
                <Card className="hover:ring-2 hover:ring-primary/20 transition-all cursor-pointer">
                  <CardHeader>
                    <CardTitle>{project.name}</CardTitle>
                    {project.description && (
                      <CardDescription>{project.description}</CardDescription>
                    )}
                  </CardHeader>
                  <CardContent>
                    <div className="flex gap-4 text-sm text-muted-foreground">
                      <span>{project.domains_count ?? 0} domains</span>
                      <span>{project.keywords_count ?? 0} keywords</span>
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
