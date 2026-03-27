import { memo } from 'react'
import { cn } from '@/lib/utils'

interface SummaryCardProps {
  title: string
  value: number | string
  description?: string
  className?: string
  icon?: React.ReactNode
  accent?: 'blue' | 'cyan' | 'green' | 'amber' | 'red'
}

const accentStyles: Record<string, string> = {
  blue: 'before:bg-accent-blue/20',
  cyan: 'before:bg-[oklch(0.75_0.15_195_/_20%)]',
  green: 'before:bg-success/20',
  amber: 'before:bg-warning/20',
  red: 'before:bg-destructive/20',
}

export const SummaryCard = memo(function SummaryCard({
  title,
  value,
  description,
  className,
  icon,
  accent,
}: SummaryCardProps) {
  return (
    <div
      className={cn(
        'glass-card relative overflow-hidden rounded-lg p-3.5 transition-all duration-200',
        accent && `before:absolute before:inset-0 before:opacity-50 before:blur-[60px] before:rounded-full before:w-24 before:h-24 before:-top-6 before:-right-6 ${accentStyles[accent]}`,
        className,
      )}
    >
      <div className="relative z-10">
        <div className="flex items-center gap-2 mb-2">
          {icon && <span className="text-muted-foreground">{icon}</span>}
          <p className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">{title}</p>
        </div>
        <p className="text-2xl font-bold tabular-nums tracking-tight">{value}</p>
        {description && (
          <p className="text-[11px] text-muted-foreground mt-1">{description}</p>
        )}
      </div>
    </div>
  )
})
