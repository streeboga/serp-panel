import { describe, it, expect, vi } from 'vitest'
import { render } from '@testing-library/react'

// Mock recharts — it doesn't render properly in jsdom
vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: any) => <div data-testid="responsive-container">{children}</div>,
  LineChart: ({ children }: any) => <div data-testid="line-chart">{children}</div>,
  BarChart: ({ children }: any) => <div data-testid="bar-chart">{children}</div>,
  Line: () => null,
  Bar: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
}))

import { PositionChart } from '@/components/charts/PositionChart'
import { TrendChart } from '@/components/charts/TrendChart'
import type { SerpHistoryItem } from '@/types/api'
import type { WordstatTrend } from '@/types/api'

// ── PositionChart ────────────────────────────────────────────────────

describe('PositionChart', () => {
  const validData: SerpHistoryItem[] = [
    { date: '2025-01-15', position: 3 },
    { date: '2025-01-14', position: 5 },
    { date: '2025-01-13', position: 7 },
  ]

  it('renders without crashing with valid data', () => {
    const { container } = render(<PositionChart data={validData} />)
    expect(container.innerHTML).not.toBe('')
  })

  it('renders chart elements with valid data', () => {
    const { getByTestId } = render(<PositionChart data={validData} />)
    expect(getByTestId('responsive-container')).toBeInTheDocument()
    expect(getByTestId('line-chart')).toBeInTheDocument()
  })

  it('handles empty data array — renders nothing', () => {
    const { container } = render(<PositionChart data={[]} />)
    expect(container.innerHTML).toBe('')
  })

  it('handles data with all null positions — renders nothing', () => {
    const nullData: SerpHistoryItem[] = [
      { date: '2025-01-15', position: null },
      { date: '2025-01-14', position: null },
    ]
    const { container } = render(<PositionChart data={nullData} />)
    // All positions are null, so chartData is empty after filtering → returns null
    expect(container.innerHTML).toBe('')
  })

  it('filters out null positions from mixed data', () => {
    const mixedData: SerpHistoryItem[] = [
      { date: '2025-01-15', position: 3 },
      { date: '2025-01-14', position: null },
      { date: '2025-01-13', position: 7 },
    ]
    const { container } = render(<PositionChart data={mixedData} />)
    // Should still render because some positions are valid
    expect(container.innerHTML).not.toBe('')
  })
})

// ── TrendChart ───────────────────────────────────────────────────────

describe('TrendChart', () => {
  const validData: WordstatTrend[] = [
    { month: '2025-01', value: 1200 },
    { month: '2025-02', value: 1500 },
    { month: '2025-03', value: 900 },
  ]

  it('renders without crashing with valid data', () => {
    const { container } = render(<TrendChart data={validData} />)
    expect(container.innerHTML).not.toBe('')
  })

  it('renders chart elements with valid data', () => {
    const { getByTestId } = render(<TrendChart data={validData} />)
    expect(getByTestId('responsive-container')).toBeInTheDocument()
    expect(getByTestId('bar-chart')).toBeInTheDocument()
  })

  it('handles empty data array — renders nothing', () => {
    const { container } = render(<TrendChart data={[]} />)
    expect(container.innerHTML).toBe('')
  })

  it('handles undefined data — renders nothing', () => {
    const { container } = render(<TrendChart data={undefined as any} />)
    expect(container.innerHTML).toBe('')
  })
})
