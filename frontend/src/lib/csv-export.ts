import type { DataTableColumn } from '@/components/shared/DataTable'

export interface CsvColumn<T> {
  header: string
  accessor: (row: T) => string | number | null | undefined
}

/**
 * Derives CSV columns straight from a page's existing DataTable column config,
 * so exported headers always match what's on screen. Columns that only have a
 * `render` (e.g. status badges, row actions) are skipped since they don't carry
 * a clean scalar value — only columns with an `accessor` are exportable.
 */
export function csvColumnsFromDataTable<T>(columns: DataTableColumn<T>[]): CsvColumn<T>[] {
  return columns
    .filter((column): column is DataTableColumn<T> & { accessor: (row: T) => string | number | null } => !!column.accessor)
    .map((column) => ({ header: column.header, accessor: column.accessor }))
}

function escapeCsvCell(value: string | number | null | undefined): string {
  const text = value === null || value === undefined ? '' : String(value)

  if (/[",\r\n]/.test(text)) {
    return `"${text.replace(/"/g, '""')}"`
  }

  return text
}

export function exportToCsv<T>(filename: string, columns: CsvColumn<T>[], rows: T[]): void {
  const headerLine = columns.map((column) => escapeCsvCell(column.header)).join(',')
  const dataLines = rows.map((row) => columns.map((column) => escapeCsvCell(column.accessor(row))).join(','))
  const csvContent = [headerLine, ...dataLines].join('\r\n')

  // Prepend a UTF-8 BOM so Excel detects the encoding correctly instead of mangling special characters.
  const blob = new Blob(['﻿' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename.endsWith('.csv') ? filename : `${filename}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
