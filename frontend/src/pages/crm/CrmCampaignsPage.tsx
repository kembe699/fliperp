import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchCampaigns, type CrmCampaign, type CrmCampaignStatus } from '@/api/crm-marketing'
import { formatDateTime } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { CampaignEditorDialog } from '@/components/crm/marketing/CampaignEditorDialog'

export const CAMPAIGN_STATUS_VARIANT: Record<CrmCampaignStatus, 'neutral' | 'info' | 'warning' | 'success' | 'danger'> = {
  draft: 'neutral',
  scheduled: 'info',
  sending: 'warning',
  sent: 'success',
  cancelled: 'danger',
}

const AUDIENCE_LABEL = { leads: 'Leads', customers: 'Customers', all: 'Leads & customers' } as const

/** CRM > Campaigns: marketing emails to leads and customers. */
export function CrmCampaignsPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const [editorOpen, setEditorOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['crm-campaigns'],
    queryFn: fetchCampaigns,
    staleTime: 0,
    refetchInterval: (query) => ((query.state.data ?? []).some((c) => c.status === 'sending' || c.status === 'scheduled') ? 10_000 : false),
  })

  const columns: DataTableColumn<CrmCampaign>[] = [
    {
      key: 'name',
      header: 'Campaign',
      sortable: true,
      accessor: (row) => row.name,
      render: (row) => (
        <div className="min-w-0">
          <p className="font-medium text-foreground">{row.name}</p>
          <p className="truncate text-xs text-muted-foreground">{row.subject}</p>
        </div>
      ),
    },
    { key: 'audience', header: 'Audience', accessor: (row) => AUDIENCE_LABEL[row.audience?.type ?? 'leads'] },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={CAMPAIGN_STATUS_VARIANT[row.status]} /> },
    { key: 'sent', header: 'Sent', accessor: (row) => (row.stats.total ? `${row.stats.sent} / ${row.stats.total}` : '—') },
    { key: 'open_rate', header: 'Opened', accessor: (row) => (row.stats.sent ? `${row.stats.open_rate}%` : '—') },
    { key: 'click_rate', header: 'Clicked', accessor: (row) => (row.stats.sent ? `${row.stats.click_rate}%` : '—') },
    {
      key: 'when',
      header: 'Date',
      accessor: (row) => formatDateTime(row.started_at ?? row.scheduled_at ?? row.created_at),
    },
  ]

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Campaigns"
        action={
          can('crm-campaigns.create') && (
            <Button onClick={() => setEditorOpen(true)}>
              <Plus className="h-4 w-4" />
              New campaign
            </Button>
          )
        }
      />
      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load campaigns. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          dateFilterKey={false}
          exportable={false}
          onRowClick={(row) => navigate(`/crm/campaigns/${row.id}`)}
          emptyTitle="No campaigns yet"
          emptySubtext="Write an email once and send it to your leads or customers — opens, clicks and unsubscribes are tracked."
        />
      )}
      <CampaignEditorDialog open={editorOpen} onOpenChange={setEditorOpen} campaign={null} onSaved={(c) => navigate(`/crm/campaigns/${c.id}`)} />
    </div>
  )
}
