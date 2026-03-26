import { memo } from 'react'
import { cn } from '@/lib/utils'

interface PositionBadgeProps {
  position: number | null
  change?: number | null
  className?: string
}

/**
 * Displays a keyword position with optional delta indicator.
 * Colors follow the UX spec:
 *   - Green for position improvement (negative change = moved up)
 *   - Red for position decline (positive change = moved down)
 *   - Muted for no change
 */
export const PositionBadge = memo(function PositionBadge({
  position,
  change,
  className,
}: PositionBadgeProps) {
  if (position == null) {
    return <span className={cn('text-muted-foreground', className)}>-</span>
  }

  return (
    <span className={cn('inline-flex items-center gap-1.5', className)}>
      <span className="font-medium tabular-nums">{position}</span>
      {change != null && change !== 0 && (
        <span
          className={cn(
            'text-xs font-medium tabular-nums',
            change > 0
              ? 'text-green-600'  // position_change > 0 means improvement
              : 'text-red-600'    // position_change < 0 means decline
          )}
        >
          {change > 0 ? '+' : ''}{change}
        </span>
      )}
    </span>
  )
})
