import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchPaymentRequests, PAYMENT_REQUEST_STATUS_LABEL, type PaymentRequest, type PaymentRequestTab } from '@/api/paymentRequests'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PAYMENT_REQUEST_STATUS_VARIANT } from './status'

export function PaymentRequestsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [tab, setTab] = useState<PaymentRequestTab>(() => (can('payment-requests.approve') && !can('payment-requests.create') ? 'awaiting' : 'all'))
  const { data, isLoading, isError } = useQuery({ queryKey: ['payment-requests', tab], queryFn: () => fetchPaymentRequests(tab) })
  // The awaiting count is worth showing on the tab even when another tab is open.
  const { data: awaiting } = useQuery({ queryKey: ['payment-requests', 'awaiting'], queryFn: () => fetchPaymentRequests('awaiting'), enabled: can('payment-requests.approve') })

  const columns: DataTableColumn<PaymentRequest>[] = [
    { key: 'reference_number', header: 'Request', accessor: (row) => row.reference_number, sortable: true },
    { key: 'request_date', header: 'Date', accessor: (row) => row.request_date, render: (row) => formatDate(row.request_date), sortable: true },
    { key: 'payee', header: 'Payee', accessor: (row) => row.payee, sortable: true },
    { key: 'description', header: 'Purpose', accessor: (row) => row.description, render: (row) => <span className="line-clamp-1 max-w-xs">{row.description}</span> },
    {
      key: 'amount',
      header: 'Amount',
      accessor: (row) => row.amount,
      sortable: true,
      className: 'text-right',
      render: (row) => (
        <span className="tabular-nums">
          {formatCurrency(row.amount, row.currency_code)}
          {row.base_amount !== row.amount && <span className="block text-xs text-muted-foreground">≈ {formatCurrency(row.base_amount)}</span>}
        </span>
      ),
    },
    { key: 'requester_name', header: 'Requester', accessor: (row) => row.requester_name, filterable: true },
    { key: 'project_name', header: 'Project', accessor: (row) => row.project_name ?? row.grant_code, filterable: true },
    {
      key: 'status',
      header: 'Status',
      accessor: (row) => PAYMENT_REQUEST_STATUS_LABEL[row.status],
      filterable: true,
      render: (row) => (
        <div>
          <StatusBadge label={PAYMENT_REQUEST_STATUS_LABEL[row.status]} variant={PAYMENT_REQUEST_STATUS_VARIANT[row.status]} />
          {row.current_step && <span className="mt-1 block text-xs text-muted-foreground">Waiting: {row.current_step.label}</span>}
        </div>
      ),
    },
  ]

  const awaitingCount = awaiting?.meta?.total ?? awaiting?.data.length ?? 0

  return (
    <div>
      <PageHeader
        parent="Accounting"
        title="Payment Requests"
        action={
          can('payment-requests.create') && (
            <Button onClick={() => navigate('/payment-requests/new')}>
              <Plus className="h-4 w-4" />
              New Payment Request
            </Button>
          )
        }
      />

      <Tabs value={tab} onValueChange={(value) => setTab(value as PaymentRequestTab)} className="mb-4">
        <TabsList>
          <TabsTrigger value="all">All requests</TabsTrigger>
          <TabsTrigger value="mine">My requests</TabsTrigger>
          {can('payment-requests.approve') && (
            <TabsTrigger value="awaiting">
              Waiting for my approval
              {awaitingCount > 0 && <span className="ml-2 rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">{awaitingCount}</span>}
            </TabsTrigger>
          )}
        </TabsList>
      </Tabs>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load payment requests. Please try again.</p>
      ) : (
        <DataTable
          title="Payment Requests"
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          onRowClick={(row) => navigate(`/payment-requests/${row.id}`)}
          emptyTitle={tab === 'awaiting' ? 'Nothing waiting for your approval' : 'No payment requests yet'}
          emptySubtext={tab === 'awaiting' ? 'Requests that need your approval will appear here.' : 'Raise one to ask for a payment. It goes to the right approvers for its amount, then to Finance to pay.'}
          exportFileName="payment-requests"
        />
      )}
    </div>
  )
}
