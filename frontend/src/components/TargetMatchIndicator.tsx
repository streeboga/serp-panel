import { memo } from 'react'

type MatchStatus = 'top3' | 'top10' | 'cannibalization' | 'missing' | 'unset' | null

const STATUS_CONFIG: Record<string, { color: string; label: string; icon: string }> = {
  top3: { color: 'text-green-600', label: 'TOP-3', icon: '\u25CF' },
  top10: { color: 'text-green-400', label: 'TOP-10', icon: '\u25CB' },
  cannibalization: { color: 'text-yellow-500', label: '\u041A\u0430\u043D\u043D\u0438\u0431\u0430\u043B\u0438\u0437\u0430\u0446\u0438\u044F', icon: '\u26A0' },
  missing: { color: 'text-red-500', label: '\u041D\u0435 \u043D\u0430\u0439\u0434\u0435\u043D', icon: '\u2715' },
  unset: { color: 'text-muted-foreground', label: '\u2014', icon: '\u2014' },
}

export const TargetMatchIndicator = memo(function TargetMatchIndicator({
  status,
  targetUrl,
}: {
  status: MatchStatus
  targetUrl?: string | null
}) {
  const config = STATUS_CONFIG[status ?? 'unset'] ?? STATUS_CONFIG.unset
  return (
    <div className="flex items-center gap-1.5" title={targetUrl ?? undefined}>
      <span className={`text-sm font-bold ${config.color}`}>{config.icon}</span>
      {targetUrl ? (
        <span className="text-xs text-muted-foreground truncate max-w-[120px]">
          {new URL(targetUrl, 'https://x').pathname}
        </span>
      ) : null}
    </div>
  )
})
