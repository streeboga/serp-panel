import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { PositionBadge } from '@/components/PositionBadge'
import { EngineBadge } from '@/components/EngineBadge'
import { SiteTypeBadge } from '@/components/SiteTypeBadge'
import { SummaryCard } from '@/components/SummaryCard'
import { FilterBar } from '@/components/FilterBar'
import { EmptyState } from '@/components/EmptyState'
import { DataExportButton } from '@/components/DataExportButton'

// ── PositionBadge ──────────────────────────────────────────────────────

describe('PositionBadge', () => {
  it('renders dash when position is null', () => {
    render(<PositionBadge position={null} />)
    expect(screen.getByText('-')).toBeInTheDocument()
  })

  it('renders position number', () => {
    render(<PositionBadge position={5} />)
    expect(screen.getByText('5')).toBeInTheDocument()
  })

  it('renders positive change with + prefix and green color', () => {
    render(<PositionBadge position={3} change={5} />)
    const changeEl = screen.getByText('+5')
    expect(changeEl).toBeInTheDocument()
    expect(changeEl).toHaveClass('text-green-600')
  })

  it('renders negative change with red color', () => {
    render(<PositionBadge position={10} change={-3} />)
    const changeEl = screen.getByText('-3')
    expect(changeEl).toBeInTheDocument()
    expect(changeEl).toHaveClass('text-red-600')
  })

  it('does not render change indicator when change is 0', () => {
    render(<PositionBadge position={7} change={0} />)
    expect(screen.getByText('7')).toBeInTheDocument()
    expect(screen.queryByText('+0')).not.toBeInTheDocument()
    expect(screen.queryByText('0')).not.toBeInTheDocument()
  })

  it('does not render change indicator when change is null', () => {
    render(<PositionBadge position={7} change={null} />)
    expect(screen.getByText('7')).toBeInTheDocument()
    // Only the position "7" should be present, no change element
    const container = screen.getByText('7').parentElement!
    expect(container.children).toHaveLength(1)
  })
})

// ── EngineBadge ────────────────────────────────────────────────────────

describe('EngineBadge', () => {
  it('renders "Ya" badge for yandex with red background', () => {
    render(<EngineBadge engine="yandex" />)
    const badge = screen.getByText('Ya')
    expect(badge).toBeInTheDocument()
    expect(badge).toHaveClass('bg-red-500')
    expect(badge).toHaveClass('text-white')
  })

  it('renders "G" badge for google with blue background', () => {
    render(<EngineBadge engine="google" />)
    const badge = screen.getByText('G')
    expect(badge).toBeInTheDocument()
    expect(badge).toHaveClass('bg-blue-500')
    expect(badge).toHaveClass('text-white')
  })
})

// ── SiteTypeBadge ──────────────────────────────────────────────────────

describe('SiteTypeBadge', () => {
  it('renders badge with site type name and color', () => {
    const siteType = { id: 1, name: 'Aggregator', color: '#3B82F6' }
    render(<SiteTypeBadge type={siteType} />)
    const badge = screen.getByText('Aggregator')
    expect(badge).toBeInTheDocument()
    // jsdom converts hex to rgb in inline styles
    expect(badge).toHaveAttribute('style', expect.stringContaining('color: white'))
    const style = badge.getAttribute('style') ?? ''
    expect(style).toMatch(/background-color:\s*(#3B82F6|rgb\(59,\s*130,\s*246\))/)
  })

  it('renders nothing when type is null', () => {
    const { container } = render(<SiteTypeBadge type={null} />)
    expect(container.innerHTML).toBe('')
  })

  it('renders nothing when type is undefined', () => {
    const { container } = render(<SiteTypeBadge type={undefined} />)
    expect(container.innerHTML).toBe('')
  })
})

// ── SummaryCard ────────────────────────────────────────────────────────

describe('SummaryCard', () => {
  it('renders title and numeric value', () => {
    render(<SummaryCard title="TOP-3" value={42} />)
    expect(screen.getByText('TOP-3')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
  })

  it('renders string value', () => {
    render(<SummaryCard title="Created" value="2025-01-01" />)
    expect(screen.getByText('Created')).toBeInTheDocument()
    expect(screen.getByText('2025-01-01')).toBeInTheDocument()
  })

  it('renders description when provided', () => {
    render(<SummaryCard title="TOP-10" value={18} description="+5 from last week" />)
    expect(screen.getByText('+5 from last week')).toBeInTheDocument()
  })

  it('does not render description when not provided', () => {
    const { container } = render(<SummaryCard title="TOP-10" value={18} />)
    const descriptions = container.querySelectorAll('.text-xs.text-muted-foreground.mt-1')
    expect(descriptions).toHaveLength(0)
  })
})

// ── FilterBar ──────────────────────────────────────────────────────────

describe('FilterBar', () => {
  it('renders children inside a flex container', () => {
    render(
      <FilterBar>
        <input data-testid="filter-input" />
        <button data-testid="filter-btn">Search</button>
      </FilterBar>,
    )
    expect(screen.getByTestId('filter-input')).toBeInTheDocument()
    expect(screen.getByTestId('filter-btn')).toBeInTheDocument()
  })

  it('applies custom className', () => {
    const { container } = render(
      <FilterBar className="mt-4">
        <span>child</span>
      </FilterBar>,
    )
    expect(container.firstChild).toHaveClass('mt-4')
  })
})

// ── EmptyState ─────────────────────────────────────────────────────────

describe('EmptyState', () => {
  it('renders title', () => {
    render(<EmptyState title="No data found" />)
    expect(screen.getByText('No data found')).toBeInTheDocument()
  })

  it('renders description when provided', () => {
    render(<EmptyState title="No data" description="Try adjusting your filters." />)
    expect(screen.getByText('Try adjusting your filters.')).toBeInTheDocument()
  })

  it('renders action element when provided', () => {
    render(
      <EmptyState
        title="No projects"
        action={<button data-testid="cta">Create Project</button>}
      />,
    )
    expect(screen.getByTestId('cta')).toBeInTheDocument()
    expect(screen.getByText('Create Project')).toBeInTheDocument()
  })

  it('does not render description or action sections when not provided', () => {
    const { container } = render(<EmptyState title="Empty" />)
    // Only the title h3 should be present, no description <p> or action <div>
    expect(container.querySelectorAll('p')).toHaveLength(0)
    expect(container.querySelector('.mt-4')).toBeNull()
  })
})

// ── DataExportButton ───────────────────────────────────────────────────

describe('DataExportButton', () => {
  it('renders with default label', () => {
    render(<DataExportButton getData={() => []} />)
    expect(screen.getByRole('button', { name: 'Export CSV' })).toBeInTheDocument()
  })

  it('renders with custom label', () => {
    render(<DataExportButton getData={() => []} label="Download" />)
    expect(screen.getByRole('button', { name: 'Download' })).toBeInTheDocument()
  })

  it('triggers download on click', async () => {
    const user = userEvent.setup()
    const createObjectURL = vi.fn(() => 'blob:mock')
    const revokeObjectURL = vi.fn()
    const clickSpy = vi.fn()

    Object.assign(URL, { createObjectURL, revokeObjectURL })

    const originalCreateElement = document.createElement.bind(document)
    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      if (tag === 'a') {
        const el = { href: '', download: '', click: clickSpy } as unknown as HTMLAnchorElement
        return el
      }
      return originalCreateElement(tag)
    })

    const data = [
      { name: 'test', value: 42 },
      { name: 'other', value: 100 },
    ]

    render(<DataExportButton getData={() => data} filename="test-export" />)
    await user.click(screen.getByRole('button', { name: 'Export CSV' }))

    expect(createObjectURL).toHaveBeenCalled()
    expect(clickSpy).toHaveBeenCalled()
    expect(revokeObjectURL).toHaveBeenCalled()

    vi.restoreAllMocks()
  })

  it('does nothing when data is empty', async () => {
    const user = userEvent.setup()
    const createObjectURL = vi.fn()
    Object.assign(URL, { createObjectURL })

    render(<DataExportButton getData={() => []} />)
    await user.click(screen.getByRole('button', { name: 'Export CSV' }))

    expect(createObjectURL).not.toHaveBeenCalled()

    vi.restoreAllMocks()
  })
})
