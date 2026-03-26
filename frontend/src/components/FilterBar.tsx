import { memo, type ReactNode } from 'react'
import { cn } from '@/lib/utils'

interface FilterBarProps {
  children: ReactNode
  className?: string
}

/**
 * Horizontal filter panel container. Wraps filter controls
 * with consistent spacing and alignment.
 */
export const FilterBar = memo(function FilterBar({
  children,
  className,
}: FilterBarProps) {
  return (
    <div className={cn('flex flex-wrap items-end gap-3', className)}>
      {children}
    </div>
  )
})
