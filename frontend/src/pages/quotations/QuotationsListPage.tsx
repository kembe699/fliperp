import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchQuotations } from '@/api/quotations'
import { fetchBranches } from '@/api/branches'
import { fetchCustomers } from '@/api/customers'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { QUOTATION_STATUS_LABEL, QUOTATION_STATUS_VARIANT } from '@/components/sales/status-variants'
import type { Quotation, QuotationStatus } from '@/types/quotation'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const STATUSES: QuotationStatus[] = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted']

export function QuotationsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [branchId, setBranchId] = useState('all')
  const [customerId, setCustomerId] = useState('all')
  const [search, setSearch] = useState('')

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: customersPage } = useQuery({ queryKey: ['customers-all'], queryFn: () => fetchCustomers({ per_page: 100 }) })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['quotations', 'all'],
    queryFn: () => fetchQuotations({ per_page: 2000 }),
  })

  const customerName = (id: number) => customersPage?.data.find((customer) => customer.id === id)?.name ?? `#${id}`

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => branchId === 'all' || row.branch_id === Number(branchId))
      .filter((row) => customerId === 'all' || row.customer_id === Number(customerId))
      .filter((row) => !search || row.reference_number.toLowerCase().includes(search.toLowerCase()) || customerName(row.customer_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, status, branchId, customerId, search, customersPage])

  const columns: DataTableColumn<Quotation>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'customer_id', header: 'Customer', render: (row) => customerName(row.customer_id) },
    { key: 'quotation_date', header: 'Date', accessor: (row) => row.quotation_date, sortable: true, render: (row) => formatDate(row.quotation_date) },
    { key: 'valid_until', header: 'Valid Until', accessor: (row) => row.valid_until, render: (row) => formatDate(row.valid_until) },
    { key: 'total_amount', header: 'Total', accessor: (row) => row.total_amount, sortable: true, render: (row) => formatCurrency(row.total_amount) },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={QUOTATION_STATUS_LABEL[row.status]} variant={QUOTATION_STATUS_VARIANT[row.status]} />,
    },
  ]

  const rowActions: (row: Quotation) => DataTableRowAction<Quotation>[] = (row) => [
    { label: 'View', onClick: (quotation) => navigate(`/quotations/${quotation.id}`) },
    ...(row.status === 'draft' && can('quotations.update')
      ? [{ label: 'Edit', onClick: (quotation: Quotation) => navigate(`/quotations/${quotation.id}/edit`) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Sales"
        title="Quotations"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('quotations.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('quotations.create') && (
              <Button onClick={() => navigate('/quotations/new')}>
                <Plus className="h-4 w-4" />
                New Quotation
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
                {QUOTATION_STATUS_LABEL[s]}
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
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'reference_number', label: 'Reference' },
            { value: 'customer', label: 'Customer' },
          ]}
          placeholder="Search quotations…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load quotations. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No quotations found"
          emptySubtext="Try adjusting your filters, or create a new quotation."
        />
      )}
    </div>
  )
}
