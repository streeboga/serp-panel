import { memo, useMemo } from 'react'
import { Badge } from '@/components/ui/badge'
import type { SiteType } from '@/types/api'

interface SiteTypeBadgeProps {
  type: SiteType | null | undefined
}

/**
 * Colored badge for site classification type.
 * Uses the color from the SiteType entity.
 */
export const SiteTypeBadge = memo(function SiteTypeBadge({ type }: SiteTypeBadgeProps) {
  const style = useMemo(
    () => ({ backgroundColor: type?.color, color: 'white' as const }),
    [type?.color],
  )

  if (!type) return null
  return (
    <Badge style={style}>
      {type.name}
    </Badge>
  )
})
