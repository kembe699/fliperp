import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchInvoices } from '@/api/invoices'
import { fetchBranches } from '@/api/branches'
import { fetchCustomers } from '@/api/customers'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { INVOICE_STATUS_LABEL, INVOICE_STATUS_VARIANT } from '@/components/sales/status-variants'
import type { Invoice, InvoiceStatus } from '@/types/invoice'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: InvoiceStatus[] = ['draft', 'sent', 'partially_paid', 'paid', 'overdue', 'cancelled']

export function InvoicesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [branchId, setBranchId] = useState('all')
  const [customerId, setCustomerId] = useState('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [search, setSearch] = useState('')

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['invoices', 'all'],
    queryFn: () => fetchInvoices({ per_page: 2000 }),
  })

  const customerName = (id: number) => customersPage?.data.find((customer) => customer.id === id)?.name ?? `#${id}`

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => branchId === 'all' || row.branch_id === Number(branchId))
      .filter((row) => customerId === 'all' || row.customer_id === Number(customerId))
      .filter((row) => !from || row.invoice_date >= from)
      .filter((row) => !to || row.invoice_date <= to)
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()) || customerName(row.customer_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, status, branchId, customerId, from, to, search, customersPage])

  const columns: DataTableColumn<Invoice>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'customer_id', header: 'Customer', render: (row) => customerName(row.customer_id) },
    { key: 'invoice_date', header: 'Invoice Date', accessor: (row) => row.invoice_date, sortable: true, render: (row) => formatDate(row.invoice_date) },
    { key: 'due_date', header: 'Due Date', accessor: (row) => row.due_date, sortable: true, render: (row) => formatDate(row.due_date) },
    { key: 'total_amount', header: 'Total', accessor: (row) => row.total_amount, sortable: true, render: (row) => formatCurrency(row.total_amount) },
    { key: 'amount_paid', header: 'Amount Paid', accessor: (row) => row.amount_paid, render: (row) => formatCurrency(row.amount_paid) },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={INVOICE_STATUS_LABEL[row.status]} variant={INVOICE_STATUS_VARIANT[row.status]} />,
    },
  ]

  const rowActions: (row: Invoice) => DataTableRowAction<Invoice>[] = (row) => [
    { label: 'View', onClick: (invoice) => navigate(`/invoices/${invoice.id}`) },
    ...(row.status === 'draft' && can('invoices.update')
      ? [{ label: 'Edit', onClick: (invoice: Invoice) => navigate(`/invoices/${invoice.id}/edit`) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Sales"
        title="Invoices"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('invoices.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('invoices.create') && (
              <Button onClick={() => navigate('/invoices/new')}>
                <Plus className="h-4 w-4" />
                New Invoice
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {INVOICE_STATUS_LABEL[s]}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={branchId} onValueChange={setBranchId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Branch" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All branches</SelectItem>
            {branches?.map((branch) => (
              <SelectItem key={branch.id} value={String(branch.id)}>
                {branch.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={customerId} onValueChange={setCustomerId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Customer" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All customers</SelectItem>
            {customersPage?.data.map((customer) => (
              <SelectItem key={customer.id} value={String(customer.id)}>
                {customer.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} className="h-8 w-40 rounded-full text-sm" />
        <Input type="date" value={to} onChange={(event) => setTo(event.target.value)} className="h-8 w-40 rounded-full text-sm" />
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'reference_number', label: 'Reference' },
            { value: 'customer', label: 'Customer' },
          ]}
          placeholder="Search invoices…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load invoices. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No invoices found"
          emptySubtext="Try adjusting your filters, or create a new invoice."
        />
      )}
    </div>
  )
}
