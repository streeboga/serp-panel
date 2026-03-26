import {
  ResponsiveContainer,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
} from 'recharts'
import type { SerpHistoryItem } from '@/types/api'

interface PositionChartProps {
  data: SerpHistoryItem[]
  height?: number
}

export function PositionChart({ data, height = 300 }: PositionChartProps) {
  const chartData = data
    .filter((d) => d.position !== null)
    .map((d) => ({
      date: new Date(d.date).toLocaleDateString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
      }),
      position: d.position,
    }))
    .reverse()

  if (chartData.length === 0) return null

  return (
    <ResponsiveContainer width="100%" height={height}>
      <LineChart data={chartData} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
        <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
        <XAxis
          dataKey="date"
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <YAxis
          reversed
          domain={[1, 'auto']}
          fontSize={12}
          tickLine={false}
          axisLine={false}
          width={40}
        />
        <Tooltip
          contentStyle={{
            borderRadius: '8px',
            border: '1px solid hsl(var(--border))',
            background: 'hsl(var(--card))',
            color: 'hsl(var(--card-foreground))',
          }}
          formatter={(value) => [`Position: ${value}`, '']}
        />
        <Line
          type="monotone"
          dataKey="position"
          stroke="hsl(var(--primary))"
          strokeWidth={2}
          dot={{ r: 3 }}
          activeDot={{ r: 5 }}
        />
      </LineChart>
    </ResponsiveContainer>
  )
}
