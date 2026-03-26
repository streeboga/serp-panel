import { describe, it, expect } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderWithProviders } from '@/test/test-utils'
import { SummaryCard } from '@/components/SummaryCard'
import { CardGridSkeleton } from '@/components/PageSkeleton'
import { useDashboardSummary } from '@/hooks/useDashboard'

// A test component that uses the actual hook to fetch dashboard data
function DashboardSummaryHarness() {
  const { data: summary, isLoading } = useDashboardSummary()

  if (isLoading) return <CardGridSkeleton count={7} />

  if (!summary) return <p>No data</p>

  return (
    <div data-testid="dashboard-grid">
      <SummaryCard title="TOP-3" value={summary.top3 ?? 0} />
      <SummaryCard title="TOP-10" value={summary.top10 ?? 0} />
      <SummaryCard title="TOP-20" value={summary.top20 ?? 0} />
      <SummaryCard title="TOP-100" value={summary.top100 ?? 0} />
      <SummaryCard title="Total Keywords" value={summary.total_keywords ?? 0} />
      <SummaryCard title="Google Keywords" value={summary.google_keywords ?? 0} />
      <SummaryCard title="Yandex Keywords" value={summary.yandex_keywords ?? 0} />
    </div>
  )
}

describe('Dashboard', () => {
  it('shows loading skeleton while fetching', () => {
    renderWithProviders(<DashboardSummaryHarness />, { authenticated: true })
    // The CardGridSkeleton renders animated pulse divs
    const skeletons = document.querySelectorAll('.animate-pulse')
    expect(skeletons.length).toBeGreaterThan(0)
  })

  it('renders summary cards with mock data', async () => {
    renderWithProviders(<DashboardSummaryHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('dashboard-grid')).toBeInTheDocument()
    })

    expect(screen.getByText('TOP-3')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
    expect(screen.getByText('TOP-10')).toBeInTheDocument()
    expect(screen.getByText('18')).toBeInTheDocument()
    expect(screen.getByText('TOP-20')).toBeInTheDocument()
    expect(screen.getByText('32')).toBeInTheDocument()
    expect(screen.getByText('TOP-100')).toBeInTheDocument()
    expect(screen.getByText('45')).toBeInTheDocument()
    expect(screen.getByText('Total Keywords')).toBeInTheDocument()
    expect(screen.getByText('60')).toBeInTheDocument()
    expect(screen.getByText('Google Keywords')).toBeInTheDocument()
    expect(screen.getByText('35')).toBeInTheDocument()
    expect(screen.getByText('Yandex Keywords')).toBeInTheDocument()
    expect(screen.getByText('25')).toBeInTheDocument()
  })
})
