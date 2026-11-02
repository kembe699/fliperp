import { useEffect, useMemo, useState, type ReactNode } from 'react'
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Columns3,
  Download,
  FileSpreadsheet,
  MoreVertical,
  Search,
  X,
} from 'lucide-react'
import { toast } from 'sonner'

import { cn } from '@/lib/utils'
import { formatDate } from '@/lib/format'
import { useAuthStore } from '@/lib/auth-store'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { EmptyState } from '@/components/shared/EmptyState'

export interface DataTableColumn<T> {
  key: string
  header: string
  render?: (row: T) => ReactNode
  accessor?: (row: T) => string | number | null
  sortable?: boolean
  className?: string
  /**
   * Adds an "All <header>" dropdown to the toolbar listing this column's
   * values. Status/type-like columns get one automatically; false suppresses it.
   */
  filterable?: boolean
}

export interface DataTableRowAction<T> {
  label: string
  onClick: (row: T) => void
  destructive?: boolean
}

interface DataTableProps<T> {
  columns: DataTableColumn<T>[]
  data: T[]
  rowKey: (row: T, index: number) => string | number
  isLoading?: boolean
  emptyTitle?: string
  emptySubtext?: string
  rowActions?: (row: T) => DataTableRowAction<T>[]
  onRowClick?: (row: T) => void
  // Server-side paging: the page owns the rows of the current page.
  page?: number
  pageCount?: number
  totalRows?: number
  onPageChange?: (page: number) => void
  /** Title at the left of the toolbar, next to the row count. */
  title?: ReactNode
  /** The page's own filters, shown in the toolbar. When given, the table adds no automatic date or column filters. */
  toolbarExtra?: ReactNode
  /** The page's own (server-side) search, shown where the built-in search box would be. */
  searchSlot?: ReactNode
  /** The page filters by the search text itself (on fields the columns may not show); the table then only reports it. */
  onSearchChange?: (query: string) => void
  /** false hides the toolbar (title, filters, search, CSV/PDF, columns). */
  toolbar?: boolean
  searchable?: boolean
  exportable?: boolean
  exportFileName?: string
  /** Column key the From/To filter applies to; detected from the first date column, false hides it. */
  dateFilterKey?: string | false
  pageSize?: number
}

type SortDirection = 'asc' | 'desc'

/**
 * The one list table: a toolbar (title and count; date range, column filters,
 * the page's own filters, CSV, PDF, columns and search), sortable headers,
 * striped rows and paging at 20 rows — the same list every IntelliCare page uses.
 */
export function DataTable<T>({
  columns: allColumns,
  data,
  rowKey,
  isLoading,
  emptyTitle,
  emptySubtext,
  rowActions,
  onRowClick,
  page,
  pageCount,
  totalRows,
  onPageChange,
  title,
  toolbarExtra,
  searchSlot,
  onSearchChange,
  toolbar = true,
  searchable = true,
  exportable = true,
  exportFileName,
  dateFilterKey,
  pageSize: initialPageSize = 20,
}: DataTableProps<T>) {
  const company = useAuthStore((state) => state.company)
  const serverPaged = page !== undefined && pageCount !== undefined

  const [sortKey, setSortKey] = useState<string | null>(null)
  const [sortDir, setSortDir] = useState<SortDirection>('asc')
  const [search, setSearch] = useState('')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [columnFilters, setColumnFilters] = useState<Record<string, string>>({})
  const [hidden, setHidden] = useState<Set<string>>(new Set())
  const [currentPage, setCurrentPage] = useState(1)
  const [pageSize, setPageSize] = useState(initialPageSize)

  const columns = useMemo(() => allColumns.filter((col) => !hidden.has(col.key)), [allColumns, hidden])
  const valueOf = (column: DataTableColumn<T>, row: T): unknown =>
    column.accessor ? column.accessor(row) : (row as Record<string, unknown>)[column.key]

  // The page supplies its own filters through toolbarExtra; otherwise the
  // table offers a date range and status/type dropdowns from the data.
  const autoFilters = toolbar && !toolbarExtra

  const dateKey = useMemo(() => {
    if (dateFilterKey === false || !autoFilters) return null
    if (dateFilterKey) return dateFilterKey
    const sample = data.slice(0, 50)
    if (sample.length === 0) return null
    for (const column of allColumns) {
      const values = sample.map((row) => valueOf(column, row)).filter((v) => v !== null && v !== undefined && v !== '')
      if (values.length > 0 && values.filter(isIsoDate).length / values.length >= 0.6) return column.key
    }
    return null
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dateFilterKey, autoFilters, data, allColumns])

  const filterColumns = useMemo(() => {
    if (!autoFilters) return []
    const out: { key: string; header: string; options: string[] }[] = []
    for (const column of allColumns) {
      if (column.filterable === false || !column.header) continue
      if (!column.filterable && !AUTO_FILTER_KEY.test(column.key)) continue
      const distinct = Array.from(
        new Set(data.map((row) => valueOf(column, row)).filter((v): v is string => typeof v === 'string' && v.trim() !== '')),
      )
      if (distinct.length < (column.filterable ? 1 : 2) || distinct.length > 25) continue
      out.push({ key: column.key, header: column.header, options: distinct.sort() })
      if (!column.filterable && out.length >= 2) break
    }
    return out
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoFilters, allColumns, data])

  const filteredData = useMemo(() => {
    const q = onSearchChange ? '' : search.trim().toLowerCase()
    const dateColumn = allColumns.find((col) => col.key === dateKey)
    return data.filter((row) => {
      if (dateColumn && (dateFrom || dateTo)) {
        const d = String(valueOf(dateColumn, row) ?? '').slice(0, 10)
        if (!d || (dateFrom && d < dateFrom) || (dateTo && d > dateTo)) return false
      }
      for (const [key, value] of Object.entries(columnFilters)) {
        const column = allColumns.find((col) => col.key === key)
        if (column && value !== 'all' && valueOf(column, row) !== value) return false
      }
      if (!q) return true
      return columns.some((column) => {
        const value = valueOf(column, row)
        return value !== null && value !== undefined && typeof value !== 'object' && String(value).toLowerCase().includes(q)
      })
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [data, search, onSearchChange, columns, allColumns, dateKey, dateFrom, dateTo, columnFilters])

  // Back to page 1 whenever a filter narrows the list.
  useEffect(() => setCurrentPage(1), [search, dateFrom, dateTo, columnFilters])

  const sortedData = useMemo(() => {
    const column = allColumns.find((col) => col.key === sortKey)
    if (!column) return filteredData
    return [...filteredData].sort((a, b) => {
      const aVal = (valueOf(column, a) ?? '') as string | number
      const bVal = (valueOf(column, b) ?? '') as string | number
      if (aVal < bVal) return sortDir === 'asc' ? -1 : 1
      if (aVal > bVal) return sortDir === 'asc' ? 1 : -1
      return 0
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filteredData, sortKey, sortDir, allColumns])

  const totalPages = serverPaged ? pageCount : Math.max(1, Math.ceil(sortedData.length / pageSize))
  const visibleRows = serverPaged ? sortedData : sortedData.slice((currentPage - 1) * pageSize, currentPage * pageSize)

  const updateSearch = (query: string) => {
    setSearch(query)
    onSearchChange?.(query)
  }

  const toggleSort = (column: DataTableColumn<T>) => {
    if (!column.sortable) return
    if (sortKey === column.key) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortKey(column.key)
      setSortDir('asc')
    }
  }

  // Export: the visible columns of the filtered, sorted rows.
  const exportColumns = columns.filter((col) => col.header)
  const fileBase =
    (exportFileName || (typeof title === 'string' ? title : 'export')).replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').toLowerCase() || 'export'
  const cellText = (column: DataTableColumn<T>, row: T): string => {
    const value = valueOf(column, row)
    if (value !== null && value !== undefined && typeof value !== 'object') {
      return column.key === dateKey || isIsoDate(value) ? formatDate(String(value)) : String(value)
    }
    const rendered = column.render?.(row)
    return typeof rendered === 'string' || typeof rendered === 'number' ? String(rendered) : ''
  }

  const handleCsv = () => {
    if (sortedData.length === 0) return toast.error('Nothing to export')
    const esc = (v: string) => (/[",\r\n]/.test(v) ? `"${v.replace(/"/g, '""')}"` : v)
    const csv = [
      exportColumns.map((col) => esc(col.header)).join(','),
      ...sortedData.map((row) => exportColumns.map((col) => esc(cellText(col, row))).join(',')),
    ].join('\r\n')
    const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' }))
    const link = document.createElement('a')
    link.href = url
    link.download = `${fileBase}.csv`
    link.click()
    URL.revokeObjectURL(url)
  }

  const handlePdf = () => {
    if (sortedData.length === 0) return toast.error('Nothing to export')
    const docTitle = typeof title === 'string' ? title : document.title || 'Report'
    const filters = [
      ...(dateFrom || dateTo ? [`Period: ${dateFrom ? formatDate(dateFrom) : 'Earliest'} — ${dateTo ? formatDate(dateTo) : 'Latest'}`] : []),
      ...filterColumns.filter((f) => columnFilters[f.key] && columnFilters[f.key] !== 'all').map((f) => `${f.header}: ${humanize(columnFilters[f.key])}`),
      ...(search ? [`Search: ${search}`] : []),
      `Rows: ${sortedData.length}`,
    ]
    const html = `<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(docTitle)}</title><style>
      body{font-family:system-ui,sans-serif;color:#111;margin:24px;font-size:12px}
      header{display:flex;align-items:center;gap:12px;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:12px}
      header img{height:48px} h1{font-size:18px;margin:0} h2{font-size:14px;margin:2px 0 0;font-weight:600}
      .meta{color:#555;margin-bottom:10px} table{width:100%;border-collapse:collapse}
      th{text-align:left;border-bottom:1.5px solid #111;padding:5px 6px;font-size:11px;text-transform:uppercase}
      td{border-bottom:1px solid #ddd;padding:4px 6px} tr:nth-child(even) td{background:#f6f6f6}
    </style></head><body><header>${company?.logo_url ? `<img src="${escapeHtml(company.logo_url)}" alt="">` : ''}<div>
      <h1>${escapeHtml(company?.name ?? '')}</h1><h2>${escapeHtml(docTitle)}</h2></div></header>
      <div class="meta">${filters.map(escapeHtml).join(' · ')} · Printed ${escapeHtml(new Date().toLocaleString())}</div>
      <table><thead><tr>${exportColumns.map((col) => `<th>${escapeHtml(col.header)}</th>`).join('')}</tr></thead><tbody>
      ${sortedData.map((row) => `<tr>${exportColumns.map((col) => `<td>${escapeHtml(cellText(col, row))}</td>`).join('')}</tr>`).join('')}
      </tbody></table><script>window.onload=function(){window.print()}</script></body></html>`
    const win = window.open('', '_blank')
    if (!win) return toast.error('Please allow pop-ups to create the PDF')
    win.document.write(html)
    win.document.close()
  }

  const columnCount = columns.length + (rowActions ? 1 : 0)
  const rowCount = serverPaged ? (totalRows ?? sortedData.length) : filteredData.length

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card">
      {toolbar && (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3 print:hidden">
          <div className="min-w-0 text-base font-semibold text-foreground">
            {title}
            <span className={cn('text-xs font-normal text-muted-foreground', title && 'ml-2')}>
              {rowCount} {rowCount === 1 ? 'record' : 'records'}
            </span>
          </div>
          <div className="flex flex-wrap items-center justify-end gap-2">
            {dateKey && (
              <>
                <Input type="date" aria-label="From date" className="h-8 w-[9.5rem] text-sm" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                <span className="text-xs text-muted-foreground">to</span>
                <Input type="date" aria-label="To date" className="h-8 w-[9.5rem] text-sm" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                {(dateFrom || dateTo) && (
                  <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={() => { setDateFrom(''); setDateTo('') }}>
                    Clear
                  </Button>
                )}
              </>
            )}
            {filterColumns.map((f) => (
              <Select key={f.key} value={columnFilters[f.key] ?? 'all'} onValueChange={(v) => setColumnFilters((prev) => ({ ...prev, [f.key]: v }))}>
                <SelectTrigger className="h-8 w-40 text-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All {plural(f.header)}</SelectItem>
                  {f.options.map((option) => (
                    <SelectItem key={option} value={option}>
                      {humanize(option)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            ))}
            {toolbarExtra}
            {exportable && (
              <>
                <Button variant="outline" size="sm" className="h-8 text-xs" onClick={handleCsv}>
                  <FileSpreadsheet className="h-3.5 w-3.5" />
                  CSV
                </Button>
                <Button variant="outline" size="sm" className="h-8 text-xs" onClick={handlePdf}>
                  <Download className="h-3.5 w-3.5" />
                  PDF
                </Button>
              </>
            )}
            {allColumns.length > 4 && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm" className="h-8 text-xs" aria-label="Show or hide columns">
                    <Columns3 className="h-3.5 w-3.5" />
                    Columns
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  {allColumns.filter((col) => col.header).map((col) => (
                    <DropdownMenuCheckboxItem
                      key={col.key}
                      checked={!hidden.has(col.key)}
                      onCheckedChange={() =>
                        setHidden((prev) => {
                          const next = new Set(prev)
                          if (next.has(col.key)) next.delete(col.key)
                          else next.add(col.key)
                          return next
                        })
                      }
                    >
                      {col.header}
                    </DropdownMenuCheckboxItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            )}
            {searchSlot}
            {searchable && !searchSlot && (
              <div className="relative w-52">
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input value={search} onChange={(e) => updateSearch(e.target.value)} placeholder="Search..." aria-label="Search" className="h-8 pl-8 pr-8 text-sm" />
                {search && (
                  <button
                    type="button"
                    aria-label="Clear search"
                    onClick={() => updateSearch('')}
                    className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-accent"
                  >
                    <X className="h-3.5 w-3.5" />
                  </button>
                )}
              </div>
            )}
          </div>
        </div>
      )}

      <Table>
        <TableHeader>
          <TableRow className="bg-muted/60 hover:bg-muted/60">
            {columns.map((column) => (
              <TableHead
                key={column.key}
                className={cn('text-xs font-semibold text-foreground', column.sortable && 'cursor-pointer select-none', column.className)}
                onClick={() => toggleSort(column)}
              >
                <span className="inline-flex items-center gap-1">
                  {column.header}
                  {column.sortable &&
                    (sortKey === column.key ? (
                      sortDir === 'asc' ? (
                        <ArrowUp className="h-3 w-3" />
                      ) : (
                        <ArrowDown className="h-3 w-3" />
                      )
                    ) : (
                      <ArrowUpDown className="h-3 w-3 opacity-40" />
                    ))}
                </span>
              </TableHead>
            ))}
            {rowActions && <TableHead className="w-10" />}
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading ? (
            Array.from({ length: 5 }).map((_, rowIndex) => (
              <TableRow key={`skeleton-${rowIndex}`}>
                {Array.from({ length: columnCount }).map((__, colIndex) => (
                  <TableCell key={`skeleton-cell-${colIndex}`}>
                    <Skeleton className="h-4 w-full max-w-32" />
                  </TableCell>
                ))}
              </TableRow>
            ))
          ) : visibleRows.length === 0 ? (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columnCount} className="p-0">
                <EmptyState title={emptyTitle} subtext={emptySubtext} />
              </TableCell>
            </TableRow>
          ) : (
            visibleRows.map((row, index) => (
              <TableRow
                key={rowKey(row, index)}
                className={cn(index % 2 === 1 && 'bg-muted/20', onRowClick && 'cursor-pointer')}
                onClick={onRowClick ? () => onRowClick(row) : undefined}
              >
                {columns.map((column) => (
                  <TableCell key={column.key} className={column.className}>
                    {column.render ? column.render(row) : (column.accessor?.(row) ?? '—')}
                  </TableCell>
                ))}
                {rowActions && (
                  <TableCell className="w-10" onClick={(event) => event.stopPropagation()}>
                    <DropdownMenu>
                      <DropdownMenuTrigger asChild>
                        <button type="button" className="rounded-md p-1.5 hover:bg-accent" aria-label="Row actions">
                          <MoreVertical className="h-4 w-4 text-muted-foreground" />
                        </button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        {rowActions(row).map((action) => (
                          <DropdownMenuItem
                            key={action.label}
                            onClick={() => action.onClick(row)}
                            className={cn(action.destructive && 'text-destructive focus:text-destructive')}
                          >
                            {action.label}
                          </DropdownMenuItem>
                        ))}
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </TableCell>
                )}
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      {(serverPaged ? pageCount > 0 : sortedData.length > 0) && (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-border px-4 py-3 print:hidden">
          <div className="flex items-center gap-2 whitespace-nowrap text-sm text-muted-foreground">
            {serverPaged ? (
              <span>{totalRows !== undefined ? `${totalRows} total` : ''}</span>
            ) : (
              <>
                <span>Rows per page:</span>
                <Select value={String(pageSize)} onValueChange={(v) => { setPageSize(Number(v)); setCurrentPage(1) }}>
                  <SelectTrigger className="h-8 w-[70px] px-2">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {Array.from(new Set([10, 20, 50, 100, initialPageSize])).sort((a, b) => a - b).map((size) => (
                      <SelectItem key={size} value={String(size)}>
                        {size}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <span className="ml-2">
                  {(currentPage - 1) * pageSize + 1}-{Math.min(currentPage * pageSize, sortedData.length)} of {sortedData.length}
                </span>
              </>
            )}
          </div>
          <Pager
            page={serverPaged ? page : currentPage}
            pageCount={totalPages}
            onChange={serverPaged ? (p) => onPageChange?.(p) : setCurrentPage}
          />
        </div>
      )}
    </div>
  )
}

function Pager({ page, pageCount, onChange }: { page: number; pageCount: number; onChange: (page: number) => void }) {
  const last = Math.max(1, pageCount)
  const button = (label: string, target: number, disabled: boolean, icon: ReactNode) => (
    <Button variant="outline" size="sm" className="h-8 w-8 p-0" aria-label={label} disabled={disabled} onClick={() => onChange(target)}>
      {icon}
    </Button>
  )
  return (
    <div className="flex items-center gap-1">
      {button('First page', 1, page <= 1, <ChevronsLeft className="h-4 w-4" />)}
      {button('Previous page', page - 1, page <= 1, <ChevronLeft className="h-4 w-4" />)}
      <span className="px-2 text-sm font-medium">
        Page {page} of {last}
      </span>
      {button('Next page', page + 1, page >= last, <ChevronRight className="h-4 w-4" />)}
      {button('Last page', last, page >= last, <ChevronsRight className="h-4 w-4" />)}
    </div>
  )
}

const AUTO_FILTER_KEY = /(^|_)(status|type|kind|category|method|currency|department|gender|source|role|priority|mode)$/i

function isIsoDate(v: unknown): boolean {
  return typeof v === 'string' && /^\d{4}-\d{2}-\d{2}([T ]|$)/.test(v)
}

function humanize(v: string): string {
  const s = v.replace(/_/g, ' ')
  return s.charAt(0).toUpperCase() + s.slice(1)
}

function plural(header: string): string {
  if (/s$/i.test(header)) return header.toLowerCase()
  if (/[^aeiou]y$/i.test(header)) return `${header.slice(0, -1)}ies`.toLowerCase()
  return `${header}s`.toLowerCase()
}

function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] as string)
}
