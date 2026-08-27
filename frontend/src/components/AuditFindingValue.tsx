import { useState } from 'react'

/**
 * Значения находок приходят разной формы: список адресов, таблица битых ссылок,
 * группы дублей, замечания валидатора. JSON.stringify превращал всё это в одну
 * нечитаемую строку, поэтому каждая форма рисуется по-своему.
 */

const COLUMN_LABELS: Record<string, string> = {
  url: 'Адрес',
  status: 'Код',
  refs: 'Ссылок',
  kb: 'Размер',
  message: 'Замечание',
  line: 'Строка',
  extract: 'Фрагмент',
  fatal: 'Фатальная',
  selector: 'Элемент',
  text: 'Текст',
  ratio: 'Контраст',
  required: 'Нужно',
  color: 'Цвет',
  background: 'Фон',
  font_size: 'Кегль',
  element: 'Элемент',
  shift: 'Сдвиг',
  value: 'Значение',
  urls: 'Адреса',
  cls: 'CLS',
  ms: 'Миллисекунд',
}

const VISIBLE_BY_DEFAULT = 5

function label(key: string): string {
  return COLUMN_LABELS[key] ?? key
}

function isUrl(value: unknown): value is string {
  return typeof value === 'string' && /^https?:\/\//.test(value)
}

function Url({ href }: { href: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="font-mono text-xs break-all text-primary hover:underline"
    >
      {href.replace(/^https?:\/\//, '')}
    </a>
  )
}

function Cell({ column, value }: { column: string; value: unknown }) {
  if (value === null || value === undefined || value === '') return <span className="text-muted-foreground">—</span>
  if (isUrl(value)) return <Url href={value} />

  if (column === 'kb' && typeof value === 'number') {
    return <span className="tabular-nums">{value >= 1024 ? `${(value / 1024).toFixed(1)} МБ` : `${value} КБ`}</span>
  }

  if (column === 'status' && typeof value === 'number') {
    return <span className={value >= 400 ? 'font-semibold text-red-600 dark:text-red-400' : ''}>{value}</span>
  }

  if (typeof value === 'boolean') return <span>{value ? 'да' : 'нет'}</span>

  if (column === 'color' || column === 'background') {
    return (
      <span className="inline-flex items-center gap-1">
        <span className="inline-block size-3 rounded-sm border" style={{ background: String(value) }} />
        <span className="font-mono text-xs">{String(value)}</span>
      </span>
    )
  }

  if (typeof value === 'object') return <span className="font-mono text-xs">{JSON.stringify(value)}</span>

  return <span className={typeof value === 'number' ? 'tabular-nums' : ''}>{String(value)}</span>
}

/** Список с кнопкой «показать ещё»: находки бывают на два десятка строк. */
function Collapsible({ total, children }: { total: number; children: (limit: number) => React.ReactNode }) {
  const [expanded, setExpanded] = useState(false)
  const limit = expanded ? total : VISIBLE_BY_DEFAULT

  return (
    <>
      {children(limit)}
      {total > VISIBLE_BY_DEFAULT && (
        <button
          type="button"
          className="mt-1 text-xs text-primary hover:underline"
          onClick={() => setExpanded((v) => !v)}
        >
          {expanded ? 'свернуть' : `показать ещё ${total - VISIBLE_BY_DEFAULT}`}
        </button>
      )}
    </>
  )
}

/** Дубли: одно значение и список адресов, где оно повторяется. */
function DuplicateGroups({ groups }: { groups: Array<{ value: string; urls: string[] }> }) {
  return (
    <Collapsible total={groups.length}>
      {(limit) => (
        <div className="space-y-2">
          {groups.slice(0, limit).map((group, i) => (
            <div key={i} className="rounded border-l-2 border-muted-foreground/30 pl-2">
              <div className="text-sm">«{group.value}»</div>
              <div className="text-xs text-muted-foreground">на {group.urls.length} страницах:</div>
              <ul className="mt-0.5 space-y-0.5">
                {group.urls.map((url) => (
                  <li key={url}>
                    <Url href={url} />
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
      )}
    </Collapsible>
  )
}

function ObjectTable({ rows }: { rows: Array<Record<string, unknown>> }) {
  // Колонки — объединение ключей: у разных строк набор может отличаться.
  const columns = Array.from(new Set(rows.flatMap((row) => Object.keys(row))))

  return (
    <Collapsible total={rows.length}>
      {(limit) => (
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead>
              <tr className="text-left text-muted-foreground">
                {columns.map((column) => (
                  <th key={column} className="pr-3 pb-1 font-medium">
                    {label(column)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {rows.slice(0, limit).map((row, i) => (
                <tr key={i} className="border-t border-muted/40">
                  <>
                    {columns.map((column) => (
                      <td key={column} className="max-w-[28rem] py-1 pr-3 align-top">
                        <Cell column={column} value={row[column]} />
                      </td>
                    ))}
                  </>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Collapsible>
  )
}

export function AuditFindingValue({ value }: { value: unknown }) {
  if (value === null || value === undefined || value === '') return null

  if (Array.isArray(value)) {
    if (value.length === 0) return null

    const first = value[0]

    if (typeof first === 'object' && first !== null) {
      const rows = value as Array<Record<string, unknown>>

      // Дубли рисуются группами: таблица с вложенным списком адресов нечитаема.
      if ('urls' in first && 'value' in first) {
        return <DuplicateGroups groups={rows as Array<{ value: string; urls: string[] }>} />
      }

      return <ObjectTable rows={rows} />
    }

    return (
      <Collapsible total={value.length}>
        {(limit) => (
          <ul className="space-y-0.5">
            {value.slice(0, limit).map((item, i) => (
              <li key={i} className="font-mono text-xs break-all">
                {isUrl(item) ? <Url href={item} /> : String(item)}
              </li>
            ))}
          </ul>
        )}
      </Collapsible>
    )
  }

  if (typeof value === 'object') {
    return (
      <dl className="space-y-1">
        {Object.entries(value as Record<string, unknown>).map(([key, item]) => (
          <div key={key} className="flex flex-wrap items-baseline gap-2">
            <dt className="text-xs text-muted-foreground">{label(key)}:</dt>
            <dd className="min-w-0 flex-1">
              {Array.isArray(item) || (typeof item === 'object' && item !== null) ? (
                <AuditFindingValue value={item} />
              ) : (
                <Cell column={key} value={item} />
              )}
            </dd>
          </div>
        ))}
      </dl>
    )
  }

  return <span className="font-mono text-xs break-all">{String(value)}</span>
}
