import { memo } from 'react'
import { Badge } from '@/components/ui/badge'

const TYPE_CONFIG: Record<string, { label: string; variant: 'default' | 'secondary' | 'outline' }> = {
  commercial: { label: '\u041A\u043E\u043C\u043C.', variant: 'default' },
  informational: { label: '\u0418\u043D\u0444\u043E', variant: 'secondary' },
  navigational: { label: '\u041D\u0430\u0432\u0438\u0433.', variant: 'outline' },
  transactional: { label: '\u0422\u0440\u0430\u043D\u0437.', variant: 'default' },
}

export const PageTypeBadge = memo(function PageTypeBadge({ type }: { type: string | null }) {
  if (!type) return null
  const config = TYPE_CONFIG[type]
  if (!config) return null
  return <Badge variant={config.variant} className="text-[10px] px-1 py-0">{config.label}</Badge>
})
