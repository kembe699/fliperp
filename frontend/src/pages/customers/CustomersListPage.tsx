import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { fetchCustomers, deactivateCustomer } from '@/api/customers'
import { fetchBranches } from '@/api/branches'
import { formatCurrency } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Customer, CustomerType } from '@/types/customer'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { CustomerFormDialog } from '@/components/customers/CustomerFormDialog'

const CUSTOMER_TYPE_LABEL: Record<CustomerType, string> = {
  walk_in: 'Walk-in',
  regular: 'Regular',
  credit: 'Credit',
}

const CUSTOMER_TYPE_VARIANT: Record<CustomerType, 'neutral' | 'info' | 'warning'> = {
  walk_in: 'neutral',
  regular: 'info',
  credit: 'warning',
}

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function CustomersListPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [customerType, setCustomerType] = useState<string>('all')
  const [branchId, setBranchId] = useState<string>('all')
  const [activeStatus, setActiveStatus] = useState<string>('all')
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null)

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['customers', page, customerType, branchId, activeStatus, search],
    queryFn: () =>
      fetchCustomers({
        page,
        per_page: 15,
        customer_type: customerType === 'all' ? undefined : (customerType as CustomerType),
        branch_id: branchId === 'all' ? undefined : Number(branchId),
        is_active: activeStatus === 'all' ? undefined : activeStatus === 'active',
        search: search || undefined,
      }),
  })

  const deactivateMutation = useMutation({
    mutationFn: deactivateCustomer,
    onSuccess: () => {
      toast.success('Customer deactivated')
      queryClient.invalidateQueries({ queryKey: ['customers'] })
    },
    onError: () => toast.error('Could not deactivate this customer'),
  })

  const columns: DataTableColumn<Customer>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'phone', header: 'Phone', accessor: (row) => row.phone },
    {
      key: 'customer_type',
      header: 'Type',
      render: (row) => <StatusBadge label={CUSTOMER_TYPE_LABEL[row.customer_type]} variant={CUSTOMER_TYPE_VARIANT[row.customer_type]} />,
    },
    {
      key: 'credit_limit',
      header: 'Credit Limit',
      accessor: (row) => row.credit_limit,
      sortable: true,
      render: (row) => formatCurrency(row.credit_limit),
    },
    {
      key: 'branch_id',
      header: 'Branch',
      render: (row) => branches?.find((branch) => branch.id === row.branch_id)?.name ?? '—',
    },
    {
      key: 'is_active',
      header: 'Status',
      render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} />,
    },
  ]

  const rowActions: (row: Customer) => DataTableRowAction<Customer>[] = (row) => [
    ...(can('customers.update') ? [{ label: 'Edit', onClick: (c: Customer) => { setEditingCustomer(c); setFormOpen(true) } }] : []),
    { label: 'View Statement', onClick: (c: Customer) => navigate(`/customers/${c.id}/statement`) },
    ...(can('customers.update') && row.is_active
      ? [{ label: 'Deactivate', destructive: true, onClick: (c: Customer) => deactivateMutation.mutate(c) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Sales"
        title="Customers"
        action={
          <div className="flex gap-2">
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchCustomers({
                  per_page: 10000,
                  customer_type: customerType === 'all' ? undefined : (customerType as CustomerType),
                  branch_id: branchId === 'all' ? undefined : Number(branchId),
                  is_active: activeStatus === 'all' ? undefined : activeStatus === 'active',
                  search: search || undefined,
                })
                exportToCsv('customers.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('customers.create') && (
              <Button
                onClick={() => {
                  setEditingCustomer(null)
                  setFormOpen(true)
                }}
              >
                <Plus className="h-4 w-4" />
                New Customer
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={customerType} onValueChange={(value) => { setCustomerType(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Customer Type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All types</SelectItem>
            <SelectItem value="walk_in">Walk-in</SelectItem>
            <SelectItem value="regular">Regular</SelectItem>
            <SelectItem value="credit">Credit</SelectItem>
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

        <Select value={activeStatus} onValueChange={(value) => { setActiveStatus(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'name', label: 'Name' },
            { value: 'phone', label: 'Phone' },
            { value: 'email', label: 'Email' },
          ]}
          placeholder="Search customers…"
          onSearch={(_by, query) => { setSearch(query); setPage(1) }}
          onClear={() => { setSearch(''); setPage(1) }}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">
          Could not load customers. Please try again.
        </p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No customers found"
          emptySubtext="Try adjusting your filters, or create a new customer to get started."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <CustomerFormDialog open={formOpen} onOpenChange={setFormOpen} customer={editingCustomer} />
    </div>
  )
}
