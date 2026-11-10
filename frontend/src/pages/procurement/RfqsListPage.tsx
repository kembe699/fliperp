import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchRfqs, type Rfq } from '@/api/rfqs'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { RFQ_STATUS_VARIANT } from './RfqDetailPage'

export function RfqsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const { data, isLoading, isError } = useQuery({ queryKey: ['rfqs'], queryFn: fetchRfqs })

  const columns: DataTableColumn<Rfq>[] = [
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'title', header: 'Title', accessor: (row) => row.title },
    {
      key: 'suppliers',
      header: 'Suppliers',
      accessor: (row) => (row.suppliers ?? []).map((s) => s.name).join(', '),
      render: (row) => {
        const list = row.suppliers ?? []
        const sent = list.filter((s) => s.sent_at).length
        return (
          <span>
            {list.length} supplier{list.length === 1 ? '' : 's'}
            <span className="text-xs text-muted-foreground"> · {sent} sent</span>
          </span>
        )
      },
    },
    { key: 'items_count', header: 'Items', accessor: (row) => row.items_count ?? 0 },
    { key: 'status', header: 'Status', accessor: (row) => row.status, render: (row) => <StatusBadge label={row.status} variant={RFQ_STATUS_VARIANT[row.status]} /> },
    { key: 'response_due_date', header: 'Quotes due', accessor: (row) => row.response_due_date, render: (row) => (row.response_due_date ? formatDate(row.response_due_date) : '—') },
    { key: 'issue_date', header: 'Issued', accessor: (row) => row.issue_date, render: (row) => formatDate(row.issue_date) },
  ]

  return (
    <div>
      <PageHeader
        parent="Procurement"
        title="Requests for Quotation"
        action={
          can('purchase-orders.create') && (
            <Button onClick={() => navigate('/rfqs/new')}>
              <Plus className="h-4 w-4" />
              New RFQ
            </Button>
          )
        }
      />
      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load requests for quotation. Please try again.</p>
      ) : (
        <DataTable
          title="Requests for Quotation"
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          onRowClick={(row) => navigate(`/rfqs/${row.id}`)}
          emptyTitle="No requests for quotation yet"
          emptySubtext="Create one to ask suppliers for prices, email it to them, and turn the best quote into a purchase order."
          exportFileName="requests-for-quotation"
        />
      )}
    </div>
  )
}
