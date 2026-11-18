import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Mail } from 'lucide-react'

import { fetchCrmEmails } from '@/api/crm'
import { formatDateTime } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmEmail, CrmEmailStatus } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { SendEmailDialog } from '@/components/crm/SendEmailDialog'

const STATUS_VARIANT: Record<CrmEmailStatus, 'warning' | 'success' | 'danger'> = { queued: 'warning', sent: 'success', failed: 'danger' }

/** CRM > Emails: every email sent to clients from the CRM, and a way to write a new one. */
export function CrmEmailsPage() {
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [composeOpen, setComposeOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['crm-emails', page],
    queryFn: () => fetchCrmEmails({ page, per_page: 20 }),
  })

  const columns: DataTableColumn<CrmEmail>[] = [
    {
      key: 'to',
      header: 'To',
      render: (row) => (
        <div className="min-w-0">
          <p className="font-medium text-foreground">{row.to_name || row.to_email}</p>
          {row.to_name && <p className="text-xs text-muted-foreground">{row.to_email}</p>}
        </div>
      ),
    },
    { key: 'subject', header: 'Subject', accessor: (row) => row.subject },
    {
      key: 'about',
      header: 'Linked to',
      render: (row) =>
        row.lead_id ? (
          <Link className="text-primary hover:underline" to={`/crm/leads/${row.lead_id}`} onClick={(e) => e.stopPropagation()}>Lead</Link>
        ) : row.deal_id ? (
          <Link className="text-primary hover:underline" to={`/crm/deals/${row.deal_id}`} onClick={(e) => e.stopPropagation()}>Deal</Link>
        ) : row.customer_id ? (
          <Link className="text-primary hover:underline" to={`/customers/${row.customer_id}/statement`} onClick={(e) => e.stopPropagation()}>Customer</Link>
        ) : (
          '—'
        ),
    },
    { key: 'sent_by', header: 'Sent by', accessor: (row) => row.sent_by?.name ?? 'Automatic' },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={STATUS_VARIANT[row.status]} /> },
    { key: 'created_at', header: 'Date', accessor: (row) => formatDateTime(row.created_at) },
  ]

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Emails"
        action={
          can('crm-emails.create') && (
            <Button onClick={() => setComposeOpen(true)}>
              <Mail className="h-4 w-4" />
              New email
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load emails. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          dateFilterKey={false}
          searchable={false}
          exportable={false}
          emptyTitle="No emails yet"
          emptySubtext="Emails you send to clients, meeting confirmations and quotations appear here."
          renderExpanded={(row) => (
            <div className="space-y-2 p-2 text-sm">
              <p className="whitespace-pre-wrap text-foreground">{row.body}</p>
              {row.status === 'failed' && row.error_message && <p className="text-destructive">{row.error_message}</p>}
            </div>
          )}
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <SendEmailDialog open={composeOpen} onOpenChange={setComposeOpen} />
    </div>
  )
}
