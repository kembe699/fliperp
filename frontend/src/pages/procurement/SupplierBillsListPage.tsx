import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchSupplierBills, fetchSuppliers } from '@/api/procurement'
import { formatCurrency, formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { SUPPLIER_BILL_STATUS_LABEL, SUPPLIER_BILL_STATUS_VARIANT } from '@/components/sales/inventory-status-variants'
import type { SupplierBill, SupplierBillStatus } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: SupplierBillStatus[] = ['unpaid', 'partially_paid', 'paid', 'overdue']

export function SupplierBillsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [supplierId, setSupplierId] = useState('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [search, setSearch] = useState('')

  const { data: suppliers } = useQuery({ queryKey: ['suppliers-all'], queryFn: () => fetchSuppliers({ per_page: 100 }) })

  const filters = {
    status: status === 'all' ? undefined : (status as SupplierBillStatus),
    supplier_id: supplierId === 'all' ? undefined : Number(supplierId),
    from: from || undefined,
    to: to || undefined,
    search: search || undefined,
  }

  const { data, isLoading, isError } = useQuery({
    queryKey: ['supplier-bills', page, status, supplierId, from, to, search],
    queryFn: () => fetchSupplierBills({ page, per_page: 15, ...filters }),
  })

  const supplierName = (id: number) => suppliers?.data.find((supplier) => supplier.id === id)?.name ?? `Supplier #${id}`

  const columns: DataTableColumn<SupplierBill>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'supplier_id', header: 'Supplier', render: (row) => supplierName(row.supplier_id) },
    { key: 'bill_date', header: 'Bill Date', accessor: (row) => row.bill_date, sortable: true, render: (row) => formatDate(row.bill_date) },
    { key: 'due_date', header: 'Due Date', accessor: (row) => row.due_date, sortable: true, render: (row) => formatDate(row.due_date) },
    { key: 'total_amount', header: 'Total Amount', accessor: (row) => row.total_amount, sortable: true, render: (row) => formatCurrency(row.total_amount) },
    { key: 'amount_paid', header: 'Amount Paid', accessor: (row) => row.amount_paid, render: (row) => formatCurrency(row.amount_paid) },
    { key: 'balance_due', header: 'Balance', accessor: (row) => row.balance_due, render: (row) => formatCurrency(row.balance_due) },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={SUPPLIER_BILL_STATUS_LABEL[row.status]} variant={SUPPLIER_BILL_STATUS_VARIANT[row.status]} />,
    },
  ]

  const rowActions: (row: SupplierBill) => DataTableRowAction<SupplierBill>[] = (row) => [
    { label: 'View', onClick: (bill) => navigate(`/supplier-bills/${bill.id}`) },
    ...(row.balance_due > 0 && can('supplier-payments.create')
      ? [{ label: 'Record Payment', onClick: (bill: SupplierBill) => navigate(`/supplier-bills/${bill.id}`) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Procurement"
        title="Supplier Bills"
        action={
          <div className="flex gap-2">
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchSupplierBills({ per_page: 10000, ...filters })
                exportToCsv('supplier-bills.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('supplier-bills.create') && (
              <Button onClick={() => navigate('/supplier-bills/new')}>
                <Plus className="h-4 w-4" />
                New Bill
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {SUPPLIER_BILL_STATUS_LABEL[s]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={supplierId} onValueChange={(value) => { setSupplierId(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Supplier" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All suppliers</SelectItem>
            {suppliers?.data.map((supplier) => (
              <SelectItem key={supplier.id} value={String(supplier.id)}>
                {supplier.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <div className="flex items-center gap-1.5">
          <Input type="date" value={from} onChange={(event) => { setFrom(event.target.value); setPage(1) }} className="h-8 w-36 rounded-full text-sm" />
          <span className="text-sm text-muted-foreground">to</span>
          <Input type="date" value={to} onChange={(event) => { setTo(event.target.value); setPage(1) }} className="h-8 w-36 rounded-full text-sm" />
        </div>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'reference_number', label: 'Reference Number' }, { value: 'supplier_name', label: 'Supplier Name' }]}
          placeholder="Search supplier bills…"
          onSearch={(_by, query) => { setSearch(query); setPage(1) }}
          onClear={() => { setSearch(''); setPage(1) }}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load supplier bills. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No supplier bills found"
          emptySubtext="Try adjusting your filters, or create a new bill."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
