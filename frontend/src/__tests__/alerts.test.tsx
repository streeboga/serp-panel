import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { TableSkeleton } from '@/components/PageSkeleton'
import { EmptyState } from '@/components/EmptyState'
import type { PositionAlert } from '@/types/api'

vi.mock('@/hooks/useAlerts', () => ({
  useAlerts: vi.fn(),
  useCreateAlert: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useUpdateAlert: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useDeleteAlert: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
}))

import { useAlerts } from '@/hooks/useAlerts'
const mockedUseAlerts = vi.mocked(useAlerts)

// ── Test harness ─────────────────────────────────────────────────────

function AlertListHarness() {
  const { data, isLoading } = useAlerts()
  const alerts: PositionAlert[] = data?.data ?? data ?? []

  if (isLoading) return <TableSkeleton />

  if (alerts.length === 0) return <EmptyState title="No alerts" />

  return (
    <table data-testid="alert-table">
      <thead>
        <tr>
          <th>Keyword</th>
          <th>Threshold</th>
          <th>Direction</th>
          <th>Channel</th>
          <th>Recipient</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        {alerts.map((a) => (
          <tr key={a.id} data-testid={`alert-row-${a.id}`}>
            <td>{a.keyword?.keyword ?? '-'}</td>
            <td>{a.threshold_position}</td>
            <td>{a.direction}</td>
            <td data-testid={`channel-${a.id}`}>{a.channel}</td>
            <td data-testid={`recipient-${a.id}`}>{a.recipient}</td>
            <td>
              <span data-testid={`alert-status-${a.id}`}>
                {a.is_active ? 'Active' : 'Inactive'}
              </span>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

// ── Mock data ────────────────────────────────────────────────────────

const mockAlerts: PositionAlert[] = [
  {
    id: 1,
    organization_id: 1,
    keyword_id: 1,
    threshold_position: 10,
    direction: 'drops_below',
    channel: 'telegram',
    recipient: '@seo_alerts_bot',
    is_active: true,
    last_triggered_at: '2025-03-18T12:00:00Z',
    keyword: { id: 1, keyword: 'seo tools', engine: 'google', device: 'desktop', latest_position: 5, position_change: 3, frequency: 1200, our_url: 'https://example.com/seo' },
  },
  {
    id: 2,
    organization_id: 1,
    keyword_id: 2,
    threshold_position: 5,
    direction: 'rises_above',
    channel: 'email',
    recipient: 'alerts@company.com',
    is_active: false,
    last_triggered_at: null,
    keyword: { id: 2, keyword: 'web analytics', engine: 'yandex', device: 'desktop', latest_position: 12, position_change: -2, frequency: 800, our_url: 'https://example.com/analytics' },
  },
]

// ── Tests ────────────────────────────────────────────────────────────

describe('Alert list', () => {
  it('shows skeleton during loading', () => {
    mockedUseAlerts.mockReturnValue({
      data: undefined,
      isLoading: true,
    } as ReturnType<typeof useAlerts>)

    const { container } = render(<AlertListHarness />)
    expect(container.querySelector('.animate-pulse')).toBeInTheDocument()
    expect(screen.queryByTestId('alert-table')).not.toBeInTheDocument()
  })

  it('renders table with alert data', () => {
    mockedUseAlerts.mockReturnValue({
      data: mockAlerts,
      isLoading: false,
    } as unknown as ReturnType<typeof useAlerts>)

    render(<AlertListHarness />)

    expect(screen.getByTestId('alert-table')).toBeInTheDocument()

    // Keyword text
    expect(screen.getByText('seo tools')).toBeInTheDocument()
    expect(screen.getByText('web analytics')).toBeInTheDocument()

    // Threshold
    expect(screen.getByText('10')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()

    // Direction
    expect(screen.getByText('drops_below')).toBeInTheDocument()
    expect(screen.getByText('rises_above')).toBeInTheDocument()
  })

  it('shows telegram channel info', () => {
    mockedUseAlerts.mockReturnValue({
      data: mockAlerts,
      isLoading: false,
    } as unknown as ReturnType<typeof useAlerts>)

    render(<AlertListHarness />)

    const telegramChannel = screen.getByTestId('channel-1')
    expect(telegramChannel).toHaveTextContent('telegram')
  })

  it('shows email channel info', () => {
    mockedUseAlerts.mockReturnValue({
      data: mockAlerts,
      isLoading: false,
    } as unknown as ReturnType<typeof useAlerts>)

    render(<AlertListHarness />)

    const emailChannel = screen.getByTestId('channel-2')
    expect(emailChannel).toHaveTextContent('email')
  })

  it('displays recipient value', () => {
    mockedUseAlerts.mockReturnValue({
      data: mockAlerts,
      isLoading: false,
    } as unknown as ReturnType<typeof useAlerts>)

    render(<AlertListHarness />)

    expect(screen.getByTestId('recipient-1')).toHaveTextContent('@seo_alerts_bot')
    expect(screen.getByTestId('recipient-2')).toHaveTextContent('alerts@company.com')
  })
})
