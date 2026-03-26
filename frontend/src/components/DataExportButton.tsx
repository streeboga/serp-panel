import { memo, useCallback, useState } from 'react'
import { Button } from '@/components/ui/button'

interface DataExportButtonProps {
  /** Returns the data rows to export */
  getData: () => Record<string, unknown>[]
  /** Filename without extension */
  filename?: string
  /** Columns to export (keys from data objects). If omitted, all keys are used. */
  columns?: string[]
  label?: string
}

/**
 * Export table data as CSV. One click, no modal.
 */
export const DataExportButton = memo(function DataExportButton({
  getData,
  filename = 'export',
  columns,
  label = 'Export CSV',
}: DataExportButtonProps) {
  const [exporting, setExporting] = useState(false)

  const handleExport = useCallback(() => {
    setExporting(true)
    try {
      const data = getData()
      if (data.length === 0) return

      const cols = columns ?? Object.keys(data[0])
      const header = cols.join(',')
      const rows = data.map((row) =>
        cols
          .map((col) => {
            const val = row[col]
            if (val == null) return ''
            const str = String(val)
            // Escape CSV values that contain commas or quotes
            if (str.includes(',') || str.includes('"') || str.includes('\n')) {
              return `"${str.replace(/"/g, '""')}"`
            }
            return str
          })
          .join(','),
      )

      const csv = [header, ...rows].join('\n')
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `${filename}.csv`
      link.click()
      URL.revokeObjectURL(url)
    } finally {
      setExporting(false)
    }
  }, [getData, filename, columns])

  return (
    <Button variant="outline" size="sm" onClick={handleExport} disabled={exporting}>
      {exporting ? 'Exporting...' : label}
    </Button>
  )
})
