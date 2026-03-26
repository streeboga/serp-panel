import { describe, it, expect } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderWithProviders } from '@/test/test-utils'
import { useKeywords } from '@/hooks/useKeywords'
import { EngineBadge } from '@/components/EngineBadge'
import { PositionBadge } from '@/components/PositionBadge'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import type { Keyword } from '@/types/api'

// Harness that uses the real useKeywords hook against MSW
function KeywordsTableHarness({ engine }: { engine?: string }) {
  const { data, isLoading } = useKeywords({
    projectId: '1',
    page: 1,
    per_page: 20,
    engine,
  })

  const keywords: Keyword[] = data?.data ?? []
  const meta = data?.meta ?? { current_page: 1, last_page: 1, total: 0 }

  if (isLoading) return <TableSkeleton rows={5} />

  if (keywords.length === 0) {
    return <EmptyState title="No keywords found" />
  }

  return (
    <div>
      <table data-testid="keywords-table">
        <thead>
          <tr>
            <th>Keyword</th>
            <th>Engine</th>
            <th>Position</th>
            <th>Frequency</th>
          </tr>
        </thead>
        <tbody>
          {keywords.map((kw) => (
            <tr key={kw.id} data-testid={`keyword-row-${kw.id}`}>
              <td>{kw.keyword}</td>
              <td>
                <EngineBadge engine={kw.engine} />
              </td>
              <td>
                <PositionBadge
                  position={kw.latest_position}
                  change={kw.position_change}
                />
              </td>
              <td>{kw.frequency ?? '-'}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <p data-testid="total">Total: {meta.total}</p>
      <p data-testid="page">Page {meta.current_page} of {meta.last_page}</p>
    </div>
  )
}

describe('Keywords table', () => {
  it('shows loading skeleton initially', () => {
    renderWithProviders(<KeywordsTableHarness />, { authenticated: true })
    expect(document.querySelector('.animate-pulse')).toBeInTheDocument()
  })

  it('renders keyword rows from mock data', async () => {
    renderWithProviders(<KeywordsTableHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('keywords-table')).toBeInTheDocument()
    })

    // 3 keywords from mock
    expect(screen.getByTestId('keyword-row-1')).toBeInTheDocument()
    expect(screen.getByTestId('keyword-row-2')).toBeInTheDocument()
    expect(screen.getByTestId('keyword-row-3')).toBeInTheDocument()

    expect(screen.getByText('seo tools')).toBeInTheDocument()
    expect(screen.getByText('web analytics')).toBeInTheDocument()
    expect(screen.getByText('rank tracker')).toBeInTheDocument()
  })

  it('renders engine badges correctly', async () => {
    renderWithProviders(<KeywordsTableHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('keywords-table')).toBeInTheDocument()
    })

    // "seo tools" is google -> "G", "web analytics" is yandex -> "Ya"
    const gBadges = screen.getAllByText('G')
    const yaBadges = screen.getAllByText('Ya')
    expect(gBadges.length).toBeGreaterThanOrEqual(1)
    expect(yaBadges.length).toBeGreaterThanOrEqual(1)
  })

  it('renders position badges with correct colors', async () => {
    renderWithProviders(<KeywordsTableHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('keywords-table')).toBeInTheDocument()
    })

    // seo tools: position 5, change +3 (green)
    const greenChange = screen.getByText('+3')
    expect(greenChange).toHaveClass('text-green-600')

    // web analytics: position 12, change -2 (red)
    const redChange = screen.getByText('-2')
    expect(redChange).toHaveClass('text-red-600')
  })

  it('renders dash for null position', async () => {
    renderWithProviders(<KeywordsTableHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('keywords-table')).toBeInTheDocument()
    })

    // rank tracker has null position
    const row3 = screen.getByTestId('keyword-row-3')
    expect(row3).toHaveTextContent('-')
  })

  it('shows total count', async () => {
    renderWithProviders(<KeywordsTableHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('total')).toBeInTheDocument()
    })

    expect(screen.getByTestId('total')).toHaveTextContent('Total: 3')
  })

  it('filters by engine via MSW handler', async () => {
    renderWithProviders(<KeywordsTableHarness engine="google" />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('keywords-table')).toBeInTheDocument()
    })

    // Only google keywords: "seo tools" and "rank tracker"
    expect(screen.getByText('seo tools')).toBeInTheDocument()
    expect(screen.getByText('rank tracker')).toBeInTheDocument()
    expect(screen.queryByText('web analytics')).not.toBeInTheDocument()
  })

  it('filters yandex engine via MSW handler', async () => {
    renderWithProviders(<KeywordsTableHarness engine="yandex" />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('keywords-table')).toBeInTheDocument()
    })

    // Only yandex keyword: "web analytics"
    expect(screen.getByText('web analytics')).toBeInTheDocument()
    expect(screen.queryByText('seo tools')).not.toBeInTheDocument()
  })
})
