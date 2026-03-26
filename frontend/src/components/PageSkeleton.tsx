import { cn } from '@/lib/utils'

interface PageSkeletonProps {
  className?: string
}

/**
 * Full-page skeleton loader used as Suspense fallback for lazy-loaded routes.
 */
export function PageSkeleton({ className }: PageSkeletonProps) {
  return (
    <div className={cn('space-y-6 animate-pulse', className)}>
      {/* Header skeleton */}
      <div className="flex items-center justify-between">
        <div className="h-8 w-48 bg-muted rounded" />
        <div className="h-9 w-28 bg-muted rounded" />
      </div>
      {/* Content skeleton */}
      <div className="space-y-3">
        <div className="h-10 w-full bg-muted rounded" />
        <div className="h-10 w-full bg-muted rounded" />
        <div className="h-10 w-full bg-muted rounded" />
        <div className="h-10 w-3/4 bg-muted rounded" />
      </div>
    </div>
  )
}

/**
 * Table skeleton with header and rows.
 */
export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-2 animate-pulse">
      <div className="h-10 w-full bg-muted rounded" />
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="h-12 w-full bg-muted/60 rounded" />
      ))}
    </div>
  )
}

/**
 * Card grid skeleton for dashboard.
 */
export function CardGridSkeleton({ count = 4 }: { count?: number }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 animate-pulse">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="h-28 bg-muted rounded-lg" />
      ))}
    </div>
  )
}
