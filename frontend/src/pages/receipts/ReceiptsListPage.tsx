import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { fetchSales, refundSale, voidSale } from '@/api/sales'
import { fetchBranches } from '@/api/branches'
import { fetchUsers } from '@/api/settings'
import { fetchPaymentTypes } from '@/api/pos'
import { fetchCustomers } from '@/api/customers'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { printPdf } from '@/lib/pdf-print'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import { SALE_STATUS_VARIANT } from '@/components/sales/status-variants'
import type { Sale } from '@/types/sale'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Input } from '@/components/ui/input'
import { SaleDetailDialog } from '@/pages/receipts/SaleDetailDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function ReceiptsListPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [viewingSaleId, setViewingSaleId] = useState<number | null>(null)
  const [status, setStatus] = useState<Sale['status'] | 'all'>('completed')
  const [branchId, setBranchId] = useState('all')
  const [servedBy, setServedBy] = useState('all')
  const [paymentTypeId, setPaymentTypeId] = useState('all')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [search, setSearch] = useState('')

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: cashiersPage } = useQuery({ queryKey: ['users-all'], queryFn: () => fetchUsers({ per_page: 100 }) })
  const { data: paymentTypes } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['receipts', page, status, branchId, servedBy, paymentTypeId, from, to, search],
    queryFn: () =>
      fetchSales({
        page,
        per_page: 15,
        status: status === 'all' ? undefined : status,
        branch_id: branchId === 'all' ? undefined : Number(branchId),
        served_by: servedBy === 'all' ? undefined : Number(servedBy),
        payment_type_id: paymentTypeId === 'all' ? undefined : Number(paymentTypeId),
        from: from || undefined,
        to: to || undefined,
        search: search || undefined,
      }),
  })

  const customerName = (id: number | null) => (id ? customersPage?.data.find((customer) => customer.id === id)?.name ?? `#${id}` : 'Walk-in Customer')
  const cashierName = (id: number) => cashiersPage?.data.find((user) => user.id === id)?.name ?? `#${id}`
  const paymentMethods = (row: Sale) =>
    row.payments.length === 0
      ? '—'
      : [...new Set(row.payments.map((payment) => paymentTypes?.find((type) => type.id === payment.payment_type_id)?.name ?? `#${payment.payment_type_id}`))].join(', ')

  const print = (row: Sale) => {
    toast.promise(printPdf(`/sales/${row.id}/receipt`), {
      loading: 'Preparing receipt…',
      success: 'Receipt sent to print',
      error: 'Could not print the receipt. Please try again.',
    })
  }

  const voidMutation = useMutation({
    mutationFn: voidSale,
    onSuccess: () => {
      toast.success('Sale voided')
      queryClient.invalidateQueries({ queryKey: ['receipts'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const refundMutation = useMutation({
    mutationFn: refundSale,
    onSuccess: () => {
      toast.success('Sale refunded')
      queryClient.invalidateQueries({ queryKey: ['receipts'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Sale>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'sale_date', header: 'Date', accessor: (row) => row.sale_date, sortable: true, render: (row) => formatDate(row.sale_date) },
    { key: 'customer_id', header: 'Customer', render: (row) => customerName(row.customer_id) },
    { key: 'served_by', header: 'Cashier', render: (row) => cashierName(row.served_by) },
    { key: 'total_amount', header: 'Total', accessor: (row) => row.total_amount, sortable: true, render: (row) => formatCurrency(row.total_amount) },
    { key: 'payments', header: 'Payment Method(s)', render: (row) => paymentMethods(row) },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status} variant={SALE_STATUS_VARIANT[row.status]} className="capitalize" />,
    },
  ]

  const rowActions: (row: Sale) => DataTableRowAction<Sale>[] = (row) => [
    { label: 'View', onClick: (sale) => setViewingSaleId(sale.id) },
    { label: 'Print', onClick: print },
    ...(row.status === 'completed' && can('sales.refund') ? [{ label: 'Refund', onClick: (sale: Sale) => refundMutation.mutate(sale.id) }] : []),
    ...(row.status === 'completed' && can('sales.void')
      ? [{ label: 'Void', destructive: true, onClick: (sale: Sale) => voidMutation.mutate(sale.id) }]
      : []),
  ]

  return (
    <div>
      <PageHeader parent="Sales" title="Receipts" />

      <FilterBar>
        <Select value={status} onValueChange={(value) => { setStatus(value as Sale['status'] | 'all'); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="completed">Completed</SelectItem>
            <SelectItem value="voided">Voided</SelectItem>
            <SelectItem value="refunded">Refunded</SelectItem>
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

        <Select value={servedBy} onValueChange={(value) => { setServedBy(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Cashier" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All cashiers</SelectItem>
            {cashiersPage?.data.map((user) => (
              <SelectItem key={user.id} value={String(user.id)}>
                {user.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={paymentTypeId} onValueChange={(value) => { setPaymentTypeId(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Payment Type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All payment types</SelectItem>
            {paymentTypes?.map((type) => (
              <SelectItem key={type.id} value={String(type.id)}>
                {type.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Input type="date" value={from} onChange={(event) => { setFrom(event.target.value); setPage(1) }} className="h-8 w-40 rounded-full text-sm" />
        <Input type="date" value={to} onChange={(event) => { setTo(event.target.value); setPage(1) }} className="h-8 w-40 rounded-full text-sm" />
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'reference_number', label: 'Reference' }]}
          placeholder="Search by reference number…"
          onSearch={(_by, query) => { setSearch(query); setPage(1) }}
          onClear={() => { setSearch(''); setPage(1) }}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load receipts. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No receipts found"
          emptySubtext="Completed sales will appear here once you start ringing up transactions in the POS."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <SaleDetailDialog saleId={viewingSaleId} onOpenChange={(open) => !open && setViewingSaleId(null)} />
    </div>
  )
}
