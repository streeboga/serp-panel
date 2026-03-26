import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { TableSkeleton } from '@/components/PageSkeleton'
import { EmptyState } from '@/components/EmptyState'
import type { Schedule } from '@/types/api'

vi.mock('@/hooks/useSchedules', () => ({
  useSchedules: vi.fn(),
  useCreateSchedule: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useUpdateSchedule: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useDeleteSchedule: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
}))

import { useSchedules } from '@/hooks/useSchedules'
const mockedUseSchedules = vi.mocked(useSchedules)

// ── Test harness ─────────────────────────────────────────────────────

function ScheduleListHarness() {
  const { data, isLoading } = useSchedules()
  const schedules: Schedule[] = data?.data ?? data ?? []

  if (isLoading) return <TableSkeleton />

  if (schedules.length === 0) return <EmptyState title="No schedules" />

  return (
    <table data-testid="schedule-table">
      <thead>
        <tr>
          <th>Project</th>
          <th>Scraper</th>
          <th>Frequency</th>
          <th>Status</th>
          <th>Last run</th>
          <th>Next run</th>
        </tr>
      </thead>
      <tbody>
        {schedules.map((s) => (
          <tr key={s.id} data-testid={`schedule-row-${s.id}`}>
            <td>{s.project?.name ?? '-'}</td>
            <td>{s.scraper?.name ?? '-'}</td>
            <td>{s.frequency ?? `${s.frequency_days}d`}</td>
            <td>
              <span data-testid={`status-${s.id}`} className={s.is_active ? 'badge-active' : 'badge-inactive'}>
                {s.is_active ? 'Active' : 'Inactive'}
              </span>
            </td>
            <td>{s.last_run_at ?? '-'}</td>
            <td>{s.next_run_at ?? '-'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

// ── Mock data ────────────────────────────────────────────────────────

const mockSchedules: Schedule[] = [
  {
    id: 1,
    project_id: 1,
    scraper_id: 1,
    frequency_days: 1,
    frequency: 'daily',
    last_run_at: '2025-03-20T10:00:00Z',
    next_run_at: '2025-03-21T10:00:00Z',
    is_active: true,
    project: { id: 1, name: 'Project Alpha' },
    scraper: { id: 1, name: 'XMLRiver Google', type: 'xmlriver' },
  },
  {
    id: 2,
    project_id: 2,
    scraper_id: 2,
    frequency_days: 7,
    frequency: 'weekly',
    last_run_at: null,
    next_run_at: '2025-03-25T08:00:00Z',
    is_active: false,
    project: { id: 2, name: 'Project Beta' },
    scraper: { id: 2, name: 'Yandex XML', type: 'yandex_xml' },
  },
]

// ── Tests ────────────────────────────────────────────────────────────

describe('Schedule list', () => {
  it('shows skeleton during loading', () => {
    mockedUseSchedules.mockReturnValue({
      data: undefined,
      isLoading: true,
    } as ReturnType<typeof useSchedules>)

    const { container } = render(<ScheduleListHarness />)
    // TableSkeleton renders animated placeholder rows
    expect(container.querySelector('.animate-pulse')).toBeInTheDocument()
    expect(screen.queryByTestId('schedule-table')).not.toBeInTheDocument()
  })

  it('renders table with schedule data', () => {
    mockedUseSchedules.mockReturnValue({
      data: mockSchedules,
      isLoading: false,
    } as unknown as ReturnType<typeof useSchedules>)

    render(<ScheduleListHarness />)

    expect(screen.getByTestId('schedule-table')).toBeInTheDocument()

    // Project names
    expect(screen.getByText('Project Alpha')).toBeInTheDocument()
    expect(screen.getByText('Project Beta')).toBeInTheDocument()

    // Scraper names
    expect(screen.getByText('XMLRiver Google')).toBeInTheDocument()
    expect(screen.getByText('Yandex XML')).toBeInTheDocument()

    // Frequency
    expect(screen.getByText('daily')).toBeInTheDocument()
    expect(screen.getByText('weekly')).toBeInTheDocument()

    // Last/next run
    expect(screen.getByText('2025-03-20T10:00:00Z')).toBeInTheDocument()
    expect(screen.getByText('2025-03-21T10:00:00Z')).toBeInTheDocument()
    expect(screen.getByText('2025-03-25T08:00:00Z')).toBeInTheDocument()
  })

  it('shows active badge when is_active is true', () => {
    mockedUseSchedules.mockReturnValue({
      data: mockSchedules,
      isLoading: false,
    } as unknown as ReturnType<typeof useSchedules>)

    render(<ScheduleListHarness />)

    const activeBadge = screen.getByTestId('status-1')
    expect(activeBadge).toHaveTextContent('Active')
    expect(activeBadge).toHaveClass('badge-active')
  })

  it('shows inactive badge when is_active is false', () => {
    mockedUseSchedules.mockReturnValue({
      data: mockSchedules,
      isLoading: false,
    } as unknown as ReturnType<typeof useSchedules>)

    render(<ScheduleListHarness />)

    const inactiveBadge = screen.getByTestId('status-2')
    expect(inactiveBadge).toHaveTextContent('Inactive')
    expect(inactiveBadge).toHaveClass('badge-inactive')
  })
})
