import { describe, it, expect, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { TargetMatchIndicator } from '@/components/TargetMatchIndicator'
import { PageTypeBadge } from '@/components/PageTypeBadge'
import { renderWithProviders } from '@/test/test-utils'
import { usePages } from '@/hooks/usePages'
import { EmptyState } from '@/components/EmptyState'
import { TableSkeleton } from '@/components/PageSkeleton'
import type { Page } from '@/types/api'

// ── TargetMatchIndicator ────────────────────────────────────────────

describe('TargetMatchIndicator', () => {
  it('shows green dot for top3', () => {
    render(<TargetMatchIndicator status="top3" targetUrl="/budget" />)
    const icon = screen.getByText('●')
    expect(icon).toHaveClass('text-green-600')
  })

  it('shows light green circle for top10', () => {
    render(<TargetMatchIndicator status="top10" targetUrl="/budget" />)
    const icon = screen.getByText('○')
    expect(icon).toHaveClass('text-green-400')
  })

  it('shows yellow warning for cannibalization', () => {
    render(<TargetMatchIndicator status="cannibalization" targetUrl="/budget" />)
    const icon = screen.getByText('⚠')
    expect(icon).toHaveClass('text-yellow-500')
  })

  it('shows red x for missing', () => {
    render(<TargetMatchIndicator status="missing" targetUrl="/budget" />)
    const icon = screen.getByText('✕')
    expect(icon).toHaveClass('text-red-500')
  })

  it('shows dash for unset', () => {
    render(<TargetMatchIndicator status="unset" />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('shows dash for null status', () => {
    render(<TargetMatchIndicator status={null} />)
    expect(screen.getByText('—')).toBeInTheDocument()
  })

  it('displays truncated target URL path', () => {
    render(<TargetMatchIndicator status="top3" targetUrl="https://example.com/budget" />)
    expect(screen.getByText('/budget')).toBeInTheDocument()
  })

  it('does not display URL when targetUrl is null', () => {
    const { container } = render(<TargetMatchIndicator status="top3" />)
    // Only the icon span should be present, no path text
    const spans = container.querySelectorAll('span')
    expect(spans).toHaveLength(1)
  })
})

// ── PageTypeBadge ───────────────────────────────────────────────────

describe('PageTypeBadge', () => {
  it('renders commercial badge', () => {
    render(<PageTypeBadge type="commercial" />)
    expect(screen.getByText('Комм.')).toBeInTheDocument()
  })

  it('renders informational badge', () => {
    render(<PageTypeBadge type="informational" />)
    expect(screen.getByText('Инфо')).toBeInTheDocument()
  })

  it('renders navigational badge', () => {
    render(<PageTypeBadge type="navigational" />)
    expect(screen.getByText('Навиг.')).toBeInTheDocument()
  })

  it('renders transactional badge', () => {
    render(<PageTypeBadge type="transactional" />)
    expect(screen.getByText('Транз.')).toBeInTheDocument()
  })

  it('returns null for null type', () => {
    const { container } = render(<PageTypeBadge type={null} />)
    expect(container.firstChild).toBeNull()
  })

  it('returns null for unknown type', () => {
    const { container } = render(<PageTypeBadge type="unknown_type" />)
    expect(container.firstChild).toBeNull()
  })
})

// ── Pages hook integration (MSW) ───────────────────────────────────

function PagesListHarness() {
  const { data, isLoading } = usePages({ projectId: '1' })
  const pages: Page[] = (() => {
    const d = data?.data ?? data
    return Array.isArray(d) ? d : []
  })()

  if (isLoading) return <TableSkeleton />
  if (pages.length === 0) return <EmptyState title="Нет страниц" />

  return (
    <table data-testid="pages-table">
      <thead>
        <tr>
          <th>URL</th>
          <th>Тип</th>
          <th>Title</th>
        </tr>
      </thead>
      <tbody>
        {pages.map((p) => (
          <tr key={p.id} data-testid={`page-row-${p.id}`}>
            <td>{p.url}</td>
            <td data-testid={`page-type-${p.id}`}>
              <PageTypeBadge type={p.page_type} />
            </td>
            <td>{p.title}</td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}

describe('Pages hook integration', () => {
  it('shows skeleton during loading', () => {
    renderWithProviders(<PagesListHarness />, { authenticated: true })
    expect(document.querySelector('.animate-pulse')).toBeInTheDocument()
  })

  it('renders pages from mock data', async () => {
    renderWithProviders(<PagesListHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('pages-table')).toBeInTheDocument()
    })

    expect(screen.getByTestId('page-row-1')).toBeInTheDocument()
    expect(screen.getByTestId('page-row-2')).toBeInTheDocument()
    expect(screen.getByTestId('page-row-3')).toBeInTheDocument()
    expect(screen.getByTestId('page-row-4')).toBeInTheDocument()
    expect(screen.getByTestId('page-row-5')).toBeInTheDocument()
  })

  it('renders page URLs', async () => {
    renderWithProviders(<PagesListHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('pages-table')).toBeInTheDocument()
    })

    expect(screen.getByText('https://example.com/seo')).toBeInTheDocument()
    expect(screen.getByText('https://example.com/blog')).toBeInTheDocument()
    expect(screen.getByText('https://competitor.com/landing')).toBeInTheDocument()
  })

  it('renders correct page type badges', async () => {
    renderWithProviders(<PagesListHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('pages-table')).toBeInTheDocument()
    })

    expect(screen.getByTestId('page-type-1')).toHaveTextContent('Комм.')
    expect(screen.getByTestId('page-type-2')).toHaveTextContent('Инфо')
    expect(screen.getByTestId('page-type-3')).toHaveTextContent('Транз.')
    expect(screen.getByTestId('page-type-4')).toHaveTextContent('Навиг.')
  })

  it('renders empty for null page_type', async () => {
    renderWithProviders(<PagesListHarness />, { authenticated: true })

    await waitFor(() => {
      expect(screen.getByTestId('pages-table')).toBeInTheDocument()
    })

    // page 5 has null page_type
    expect(screen.getByTestId('page-type-5')).toHaveTextContent('')
  })
})

// ── SERP enrichment with page data ─────────────────────────────────

describe('SERP enrichment with pages', () => {
  it('matches pages to SERP results by domain', () => {
    const pages: Page[] = [
      { id: 1, project_id: 1, domain_id: 1, url: 'https://example.com/seo', path: '/seo', title: 'SEO Page', page_type: 'commercial', notes: null, tags: [], created_at: '', updated_at: '' },
      { id: 2, project_id: 1, domain_id: 2, url: 'https://competitor.com/landing', path: '/landing', title: 'Landing', page_type: 'transactional', notes: null, tags: [], created_at: '', updated_at: '' },
    ]

    const serpResults = [
      { position: 1, domain: 'competitor.com', url: 'https://competitor.com/seo', title: 'Competitor', site_type: null, is_own: false },
      { position: 2, domain: 'example.com', url: 'https://example.com/seo', title: 'Our Page', site_type: null, is_own: true },
      { position: 3, domain: 'another.com', url: 'https://another.com/guide', title: 'Other', site_type: null, is_own: false },
    ]

    // Build pagesByDomain map (same logic as in SerpTab)
    const pagesByDomain = new Map<string, Page>()
    for (const page of pages) {
      try {
        const hostname = new URL(page.url).hostname.replace(/^www\./, '')
        if (!pagesByDomain.has(hostname)) pagesByDomain.set(hostname, page)
      } catch { /* skip */ }
      if (page.path) pagesByDomain.set(page.path, page)
    }

    // Match SERP results
    const enriched = serpResults.map((item) => {
      const domain = item.domain?.replace(/^www\./, '') ?? ''
      const matchedPage = pagesByDomain.get(domain)
      return { ...item, page_type: matchedPage?.page_type ?? null }
    })

    expect(enriched[0].page_type).toBe('transactional')  // competitor.com matched
    expect(enriched[1].page_type).toBe('commercial')      // example.com matched
    expect(enriched[2].page_type).toBeNull()               // another.com not in pages
  })

  it('strips www prefix when matching domains', () => {
    const pages: Page[] = [
      { id: 1, project_id: 1, domain_id: 1, url: 'https://www.example.com/seo', path: '/seo', title: 'SEO', page_type: 'informational', notes: null, tags: [], created_at: '', updated_at: '' },
    ]

    const pagesByDomain = new Map<string, Page>()
    for (const page of pages) {
      try {
        const hostname = new URL(page.url).hostname.replace(/^www\./, '')
        if (!pagesByDomain.has(hostname)) pagesByDomain.set(hostname, page)
      } catch { /* skip */ }
    }

    // SERP result without www
    const domain = 'example.com'
    const matchedPage = pagesByDomain.get(domain)
    expect(matchedPage).toBeDefined()
    expect(matchedPage!.page_type).toBe('informational')
  })

  it('matches by path as fallback', () => {
    const pages: Page[] = [
      { id: 1, project_id: 1, domain_id: 1, url: 'https://example.com/seo', path: '/seo', title: 'SEO', page_type: 'commercial', notes: null, tags: [], created_at: '', updated_at: '' },
    ]

    const pagesByDomain = new Map<string, Page>()
    for (const page of pages) {
      try {
        const hostname = new URL(page.url).hostname.replace(/^www\./, '')
        if (!pagesByDomain.has(hostname)) pagesByDomain.set(hostname, page)
      } catch { /* skip */ }
      if (page.path) pagesByDomain.set(page.path, page)
    }

    // Path-based lookup
    const matchedByPath = pagesByDomain.get('/seo')
    expect(matchedByPath).toBeDefined()
    expect(matchedByPath!.page_type).toBe('commercial')
  })

  it('renders PageTypeBadge for matched domains', () => {
    const { container } = render(
      <table>
        <tbody>
          <tr>
            <td data-testid="type-cell">
              <PageTypeBadge type="commercial" />
            </td>
          </tr>
        </tbody>
      </table>,
    )
    expect(screen.getByText('Комм.')).toBeInTheDocument()
  })

  it('renders nothing for unmatched domains', () => {
    const { container } = render(
      <table>
        <tbody>
          <tr>
            <td data-testid="type-cell">
              <PageTypeBadge type={null} />
            </td>
          </tr>
        </tbody>
      </table>,
    )
    expect(screen.getByTestId('type-cell')).toHaveTextContent('')
  })
})
