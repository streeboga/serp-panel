import { memo } from 'react'
import { Badge } from '@/components/ui/badge'

interface EngineBadgeProps {
  engine: string
}

/**
 * Yandex = red "Ya" badge, Google = blue "G" badge.
 */
export const EngineBadge = memo(function EngineBadge({ engine }: EngineBadgeProps) {
  const isYandex = engine === 'yandex'
  return (
    <Badge
      variant={isYandex ? 'default' : 'secondary'}
      className={isYandex ? 'bg-red-500 text-white' : 'bg-blue-500 text-white'}
    >
      {isYandex ? 'Ya' : 'G'}
    </Badge>
  )
})
