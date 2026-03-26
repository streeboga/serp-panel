import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
} from 'recharts'

interface TopDistributionData {
  label: string
  top3: number
  top10: number
  top20: number
  top100: number
}

interface TopDistributionChartProps {
  data: TopDistributionData[]
  height?: number
}

export function TopDistributionChart({
  data,
  height = 250,
}: TopDistributionChartProps) {
  if (!data || data.length === 0) return null

  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} margin={{ top: 5, right: 20, bottom: 5, left: 0 }}>
        <CartesianGrid strokeDasharray="3 3" className="opacity-30" />
        <XAxis
          dataKey="label"
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <YAxis
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
        />
        <Legend />
        <Bar dataKey="top3" name="TOP 3" fill="#22c55e" radius={[2, 2, 0, 0]} />
        <Bar dataKey="top10" name="TOP 10" fill="#3b82f6" radius={[2, 2, 0, 0]} />
        <Bar dataKey="top20" name="TOP 20" fill="#f59e0b" radius={[2, 2, 0, 0]} />
        <Bar dataKey="top100" name="TOP 100" fill="#6b7280" radius={[2, 2, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  )
}
