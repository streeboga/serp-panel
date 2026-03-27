import { cn } from '@/lib/utils'

interface PageSkeletonProps {
  className?: string
}

export function PageSkeleton({ className }: PageSkeletonProps) {
  return (
    <div className={cn('space-y-4 animate-pulse', className)}>
      <div className="flex items-center justify-between">
        <div className="h-7 w-44 bg-muted rounded-lg" />
        <div className="h-8 w-24 bg-muted rounded-lg" />
      </div>
      <div className="space-y-2">
        <div className="h-9 w-full bg-muted/60 rounded-lg" />
        <div className="h-9 w-full bg-muted/40 rounded-lg" />
        <div className="h-9 w-full bg-muted/30 rounded-lg" />
        <div className="h-9 w-3/4 bg-muted/20 rounded-lg" />
      </div>
    </div>
  )
}

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-1.5 animate-pulse">
      <div className="h-8 w-full bg-muted/60 rounded-lg" />
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="h-9 w-full bg-muted/30 rounded-lg" style={{ opacity: 1 - i * 0.12 }} />
      ))}
    </div>
  )
}

export function CardGridSkeleton({ count = 4 }: { count?: number }) {
  return (
    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 animate-pulse">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="h-24 glass-card rounded-lg" />
      ))}
    </div>
  )
}
