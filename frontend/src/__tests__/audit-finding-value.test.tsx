import { describe, it, expect } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { AuditFindingValue } from '@/components/AuditFindingValue'

// Значения находок приходят восемью разными формами — каждая должна читаться
// человеком, а не выглядеть как JSON.stringify в одну строку.

describe('AuditFindingValue', () => {
  it('не рисует ничего для пустого значения', () => {
    const { container } = render(<AuditFindingValue value={null} />)
    expect(container).toBeEmptyDOMElement()
  })

  it('скаляр показывает как есть', () => {
    render(<AuditFindingValue value="4.2%" />)
    expect(screen.getByText('4.2%')).toBeInTheDocument()
  })

  it('список строк — по строке на пункт', () => {
    render(<AuditFindingValue value={['Host: https://eq.team', 'Clean-param: utm_source']} />)
    expect(screen.getByText('Host: https://eq.team')).toBeInTheDocument()
    expect(screen.getByText('Clean-param: utm_source')).toBeInTheDocument()
  })

  it('битые ссылки — таблицей, код 4xx выделен', () => {
    render(
      <AuditFindingValue
        value={[{ url: 'https://eq.team/dead/', status: 404, refs: 3 }]}
      />,
    )

    expect(screen.getByText('Адрес')).toBeInTheDocument()
    expect(screen.getByText('Код')).toBeInTheDocument()
    expect(screen.getByText('Ссылок')).toBeInTheDocument()

    // Адрес — кликабельная ссылка без схемы в тексте.
    const link = screen.getByRole('link', { name: 'eq.team/dead/' })
    expect(link).toHaveAttribute('href', 'https://eq.team/dead/')
    expect(link).toHaveAttribute('rel', 'noopener noreferrer')

    expect(screen.getByText('404')).toHaveClass('text-red-600')
  })

  it('вес картинок переводит килобайты в мегабайты', () => {
    render(<AuditFindingValue value={[{ url: 'https://eq.team/a.png', kb: 1686 }, { url: 'https://eq.team/b.png', kb: 786 }]} />)
    expect(screen.getByText('1.6 МБ')).toBeInTheDocument()
    expect(screen.getByText('786 КБ')).toBeInTheDocument()
  })

  it('дубли — группой: значение и список адресов', () => {
    render(
      <AuditFindingValue
        value={[{ value: 'Услуги — EQ.team', urls: ['https://eq.team/a/', 'https://eq.team/b/'] }]}
      />,
    )

    expect(screen.getByText('«Услуги — EQ.team»')).toBeInTheDocument()
    expect(screen.getByText('на 2 страницах:')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'eq.team/a/' })).toBeInTheDocument()
  })

  it('замечания валидатора — таблицей с номером строки', () => {
    render(
      <AuditFindingValue
        value={[{ message: 'End tag “h2” seen', line: 271, extract: '<h3 class="x">', fatal: false }]}
      />,
    )

    expect(screen.getByText('Строка')).toBeInTheDocument()
    expect(screen.getByText('271')).toBeInTheDocument()
    expect(screen.getByText('нет')).toBeInTheDocument()
  })

  it('контраст показывает образцы цвета', () => {
    const { container } = render(
      <AuditFindingValue
        value={[{ selector: 'p.muted', text: 'Серый', ratio: 2.85, required: 4.5, color: 'rgb(153, 153, 153)', background: 'rgb(255, 255, 255)', font_size: 16 }]}
      />,
    )

    expect(screen.getByText('Контраст')).toBeInTheDocument()
    expect(screen.getByText('2.85')).toBeInTheDocument()
    // Рядом с цветом — квадратик этого цвета.
    expect(container.querySelector('[style*="rgb(153, 153, 153)"]')).not.toBeNull()
  })

  it('вложенный объект CLS: число и таблица виновников', () => {
    render(
      <AuditFindingValue
        value={{ cls: 0.31, виновники: [{ element: 'img.hero', shift: 0.29 }] }}
      />,
    )

    expect(screen.getByText('CLS:')).toBeInTheDocument()
    expect(screen.getByText('0.31')).toBeInTheDocument()
    expect(screen.getByText('Элемент')).toBeInTheDocument()
    expect(screen.getByText('img.hero')).toBeInTheDocument()
  })

  it('длинный список сворачивается и разворачивается', () => {
    const rows = Array.from({ length: 8 }, (_, i) => ({ url: `https://eq.team/${i}/`, status: 404, refs: 1 }))
    render(<AuditFindingValue value={rows} />)

    // Показаны пять из восьми.
    expect(screen.getAllByRole('link')).toHaveLength(5)

    fireEvent.click(screen.getByRole('button', { name: 'показать ещё 3' }))
    expect(screen.getAllByRole('link')).toHaveLength(8)

    fireEvent.click(screen.getByRole('button', { name: 'свернуть' }))
    expect(screen.getAllByRole('link')).toHaveLength(5)
  })
})
