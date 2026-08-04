import { useMemo, useState, type ReactNode } from 'react'
import { ArrowDown, ArrowUp, ArrowUpDown, ChevronLeft, ChevronRight, MoreVertical } from 'lucide-react'

import { cn } from '@/lib/utils'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Skeleton } from '@/components/ui/skeleton'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { EmptyState } from '@/components/shared/EmptyState'

export interface DataTableColumn<T> {
  key: string
  header: string
  render?: (row: T) => ReactNode
  accessor?: (row: T) => string | number | null
  sortable?: boolean
  className?: string
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
  page?: number
  pageCount?: number
  totalRows?: number
  onPageChange?: (page: number) => void
}

type SortDirection = 'asc' | 'desc'

export function DataTable<T>({
  columns,
  data,
  rowKey,
  isLoading,
  emptyTitle,
  emptySubtext,
  rowActions,
  page,
  pageCount,
  totalRows,
  onPageChange,
}: DataTableProps<T>) {
  const [sortKey, setSortKey] = useState<string | null>(null)
  const [sortDir, setSortDir] = useState<SortDirection>('asc')

  const sortedData = useMemo(() => {
    if (!sortKey) return data
    const column = columns.find((col) => col.key === sortKey)
    if (!column?.accessor) return data

    const accessor = column.accessor
    return [...data].sort((a, b) => {
      const aVal = accessor(a) ?? ''
      const bVal = accessor(b) ?? ''
      if (aVal < bVal) return sortDir === 'asc' ? -1 : 1
      if (aVal > bVal) return sortDir === 'asc' ? 1 : -1
      return 0
    })
  }, [data, sortKey, sortDir, columns])

  const toggleSort = (column: DataTableColumn<T>) => {
    if (!column.sortable || !column.accessor) return
    if (sortKey === column.key) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortKey(column.key)
      setSortDir('asc')
    }
  }

  const columnCount = columns.length + (rowActions ? 1 : 0)

  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card">
      <Table>
        <TableHeader>
          <TableRow className="hover:bg-transparent">
            {columns.map((column) => (
              <TableHead
                key={column.key}
                className={cn(column.sortable && 'cursor-pointer select-none', column.className)}
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
          ) : sortedData.length === 0 ? (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={columnCount} className="p-0">
                <EmptyState title={emptyTitle} subtext={emptySubtext} />
              </TableCell>
            </TableRow>
          ) : (
            sortedData.map((row, index) => (
              <TableRow key={rowKey(row, index)}>
                {columns.map((column) => (
                  <TableCell key={column.key} className={column.className}>
                    {column.render ? column.render(row) : (column.accessor?.(row) ?? '—')}
                  </TableCell>
                ))}
                {rowActions && (
                  <TableCell className="w-10">
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

      {page !== undefined && pageCount !== undefined && pageCount > 0 && (
        <div className="flex items-center justify-between border-t border-border px-4 py-3">
          <p className="text-sm text-muted-foreground">
            Page {page} of {pageCount}
            {totalRows !== undefined && ` · ${totalRows} total`}
          </p>
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => onPageChange?.(page - 1)}
            >
              <ChevronLeft className="h-4 w-4" />
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= pageCount}
              onClick={() => onPageChange?.(page + 1)}
            >
              Next
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
        </div>
      )}
    </div>
  )
}
