import { useState } from 'react'
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

  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [branchId, setBranchId] = useState('all')
  const [customerId, setCustomerId] = useState('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['invoices', page, status, branchId, customerId, from, to],
    queryFn: () =>
      fetchInvoices({
        page,
        per_page: 15,
        status: status === 'all' ? undefined : (status as InvoiceStatus),
        branch_id: branchId === 'all' ? undefined : Number(branchId),
        customer_id: customerId === 'all' ? undefined : Number(customerId),
        from: from || undefined,
        to: to || undefined,
      }),
  })

  const customerName = (id: number) => customersPage?.data.find((customer) => customer.id === id)?.name ?? `#${id}`

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
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchInvoices({
                  per_page: 10000,
                  status: status === 'all' ? undefined : (status as InvoiceStatus),
                  branch_id: branchId === 'all' ? undefined : Number(branchId),
                  customer_id: customerId === 'all' ? undefined : Number(customerId),
                  from: from || undefined,
                  to: to || undefined,
                })
                exportToCsv('invoices.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
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
        <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1) }}>
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

        <Select value={branchId} onValueChange={(value) => { setBranchId(value); setPage(1) }}>
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

        <Select value={customerId} onValueChange={(value) => { setCustomerId(value); setPage(1) }}>
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

        <Input type="date" value={from} onChange={(event) => { setFrom(event.target.value); setPage(1) }} className="h-8 w-40 rounded-full text-sm" />
        <Input type="date" value={to} onChange={(event) => { setTo(event.target.value); setPage(1) }} className="h-8 w-40 rounded-full text-sm" />
      </FilterBar>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load invoices. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No invoices found"
          emptySubtext="Try adjusting your filters, or create a new invoice."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
