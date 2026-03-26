import { memo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'

interface SummaryCardProps {
  title: string
  value: number | string
  description?: string
  className?: string
}

/**
 * Dashboard metric card with title and large value display.
 */
export const SummaryCard = memo(function SummaryCard({
  title,
  value,
  description,
  className,
}: SummaryCardProps) {
  return (
    <Card className={cn(className)}>
      <CardHeader>
        <CardTitle className="text-sm font-medium text-muted-foreground">
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <p className="text-3xl font-bold tabular-nums">{value}</p>
        {description && (
          <p className="text-xs text-muted-foreground mt-1">{description}</p>
        )}
      </CardContent>
    </Card>
  )
})
