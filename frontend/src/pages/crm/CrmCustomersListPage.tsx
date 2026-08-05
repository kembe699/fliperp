import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'

import { fetchCrmCustomers } from '@/api/crm'
import { fetchBranches } from '@/api/branches'
import { usePermissions } from '@/hooks/use-permissions'
import type { Customer, CustomerType } from '@/types/customer'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

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

export function CrmCustomersListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const canViewAll = can('crm-customers.view-all')

  const [page, setPage] = useState(1)
  const [customerType, setCustomerType] = useState<string>('all')
  const [branchId, setBranchId] = useState<string>('all')
  const [search, setSearch] = useState('')

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['crm-customers', page, customerType, branchId, search],
    queryFn: () =>
      fetchCrmCustomers({
        page,
        per_page: 15,
        customer_type: customerType === 'all' ? undefined : customerType,
        branch_id: branchId === 'all' ? undefined : Number(branchId),
        search: search || undefined,
      }),
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
      key: 'branch_id',
      header: 'Branch',
      render: (row) => branches?.find((branch) => branch.id === row.branch_id)?.name ?? '—',
    },
  ]

  const rowActions: (row: Customer) => DataTableRowAction<Customer>[] = (row) => [
    { label: 'View CRM Details', onClick: (c: Customer) => navigate(`/customers/${c.id}/statement`) },
  ]

  return (
    <div>
      <PageHeader parent="CRM" title="Customers" />

      {!canViewAll && (
        <p className="mb-4 text-sm text-muted-foreground">
          Showing only customers currently assigned to you. Ask a manager to grant view-all access to see every customer.
        </p>
      )}

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
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'name', label: 'Name' },
            { value: 'phone', label: 'Phone' },
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
          emptySubtext={canViewAll ? 'Try adjusting your filters.' : "You don't have any assigned customers yet."}
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
