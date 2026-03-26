import { describe, it, expect } from 'vitest'
import { screen, waitFor } from '@testing-library/react'
import { renderWithProviders } from '@/test/test-utils'
import { useSerpResults, useSerpHistory } from '@/hooks/useSerp'
import { useWordstat, useWordstatTrends, useWordstatSuggestions } from '@/hooks/useWordstat'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { SummaryCard } from '@/components/SummaryCard'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import type { SerpResult, SerpHistoryItem, WordstatTrend, WordstatSuggestion } from '@/types/api'

// ── SERP Tab Harness ───────────────────────────────────────────────────

function SerpTabHarness() {
  const { data: serpData, isLoading } = useSerpResults('1', { top_n: 20 })
  const results: SerpResult[] = serpData?.data ?? serpData ?? []

  if (isLoading) return <TableSkeleton />

  if (results.length === 0) return <EmptyState title="No SERP data" />

  return (
    <table data-testid="serp-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Domain</th>
          <th>Type</th>
          <th>Title</th>
        </tr>
      </thead>
      <tbody>
        {results.map((item, idx) => (
          <tr
            key={idx}
            data-testid={`serp-row-${idx}`}
            className={item.is_own ? 'bg-green-50' : ''}
          >
            <td>{item.position}</td>
            <td>{item.domain}</td>
            <td>
              <SiteTypeBadge type={item.site_type ?? null} />
            </td>
            <td>{item.title}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

describe('Keyword Detail - SERP Tab', () => {
  it('renders SERP results table', async () => {
    renderWithProviders(<SerpTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('serp-table')).toBeInTheDocument()
    })

    expect(screen.getByText('competitor.com')).toBeInTheDocument()
    expect(screen.getByText('example.com')).toBeInTheDocument()
    expect(screen.getByText('another.com')).toBeInTheDocument()
  })

  it('highlights own domain row', async () => {
    renderWithProviders(<SerpTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('serp-table')).toBeInTheDocument()
    })

    // Row 1 (index 1) is own domain
    const ownRow = screen.getByTestId('serp-row-1')
    expect(ownRow).toHaveClass('bg-green-50')

    // Row 0 is not own
    const otherRow = screen.getByTestId('serp-row-0')
    expect(otherRow).not.toHaveClass('bg-green-50')
  })

  it('renders SiteTypeBadge with correct colors', async () => {
    renderWithProviders(<SerpTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('serp-table')).toBeInTheDocument()
    })

    const aggregatorBadge = screen.getByText('Aggregator')
    expect(aggregatorBadge).toHaveStyle({ backgroundColor: '#3B82F6' })

    const commercialBadge = screen.getByText('Commercial')
    expect(commercialBadge).toHaveStyle({ backgroundColor: '#10B981' })
  })
})

// ── History Tab Harness ────────────────────────────────────────────────

function HistoryTabHarness() {
  const { data, isLoading } = useSerpHistory('1')
  const history: SerpHistoryItem[] = data?.data ?? data ?? []

  if (isLoading) return <TableSkeleton />

  if (history.length === 0) return <EmptyState title="No history" />

  return (
    <table data-testid="history-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Position</th>
          <th>URL</th>
        </tr>
      </thead>
      <tbody>
        {history.map((item, idx) => (
          <tr key={idx} data-testid={`history-row-${idx}`}>
            <td>{item.date}</td>
            <td>{item.position ?? '-'}</td>
            <td>{item.url ?? '-'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

describe('Keyword Detail - History Tab', () => {
  it('renders history data', async () => {
    renderWithProviders(<HistoryTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('history-table')).toBeInTheDocument()
    })

    expect(screen.getByText('2025-03-01')).toBeInTheDocument()
    expect(screen.getByText('2025-03-08')).toBeInTheDocument()
    expect(screen.getByText('2025-03-15')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('2')).toBeInTheDocument()
  })
})

// ── Wordstat Tab Harness ───────────────────────────────────────────────

function WordstatTabHarness() {
  const { data: wordstat, isLoading: wLoading } = useWordstat('1')
  const { data: trends, isLoading: tLoading } = useWordstatTrends('1')

  const ws = wordstat?.data ?? wordstat
  const trendList: WordstatTrend[] = trends?.data ?? trends ?? []

  if (wLoading) return <div data-testid="ws-loading">Loading wordstat...</div>

  return (
    <div data-testid="wordstat-tab">
      {ws ? (
        <div data-testid="wordstat-cards">
          <SummaryCard title="Exact" value={ws.exact ?? '-'} />
          <SummaryCard title="Broad" value={ws.broad ?? '-'} />
          <SummaryCard title="Phrase" value={ws.phrase ?? '-'} />
        </div>
      ) : (
        <EmptyState title="No Wordstat data" />
      )}

      {tLoading ? (
        <TableSkeleton />
      ) : trendList.length > 0 ? (
        <table data-testid="trends-table">
          <tbody>
            {trendList.map((item, idx) => (
              <tr key={idx}>
                <td>{item.month}</td>
                <td>{item.value}</td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : null}
    </div>
  )
}

describe('Keyword Detail - Wordstat Tab', () => {
  it('renders frequency cards', async () => {
    renderWithProviders(<WordstatTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('wordstat-cards')).toBeInTheDocument()
    })

    const cards = screen.getByTestId('wordstat-cards')
    expect(cards).toHaveTextContent('Exact')
    expect(cards).toHaveTextContent('1200')
    expect(cards).toHaveTextContent('Broad')
    expect(cards).toHaveTextContent('5600')
    expect(cards).toHaveTextContent('Phrase')
    expect(cards).toHaveTextContent('3400')
  })

  it('renders trend data', async () => {
    renderWithProviders(<WordstatTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('trends-table')).toBeInTheDocument()
    })

    expect(screen.getByText('2025-01')).toBeInTheDocument()
    expect(screen.getByText('1100')).toBeInTheDocument()
    expect(screen.getByText('2025-02')).toBeInTheDocument()
    expect(screen.getByText('1250')).toBeInTheDocument()
  })
})

// ── Suggestions Tab Harness ────────────────────────────────────────────

function SuggestionsTabHarness() {
  const { data, isLoading } = useWordstatSuggestions('1')
  const suggestions: WordstatSuggestion[] = data?.data ?? data ?? []

  if (isLoading) return <TableSkeleton />

  if (suggestions.length === 0) return <EmptyState title="No suggestions" />

  return (
    <table data-testid="suggestions-table">
      <tbody>
        {suggestions.map((item, idx) => (
          <tr key={idx}>
            <td>{item.suggestion}</td>
            <td>{item.frequency}</td>
            <td>{item.type}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

describe('Keyword Detail - Suggestions Tab', () => {
  it('renders suggestions data', async () => {
    renderWithProviders(<SuggestionsTabHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('suggestions-table')).toBeInTheDocument()
    })

    expect(screen.getByText('seo tools free')).toBeInTheDocument()
    expect(screen.getByText('800')).toBeInTheDocument()
    expect(screen.getByText('best seo tools 2025')).toBeInTheDocument()
    expect(screen.getByText('600')).toBeInTheDocument()
  })
})
