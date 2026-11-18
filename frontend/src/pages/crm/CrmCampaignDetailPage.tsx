import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CalendarClock, Pencil, Send, Square, Trash2 } from 'lucide-react'

import {
  cancelCampaign,
  deleteCampaign,
  fetchCampaign,
  fetchCampaignRecipients,
  scheduleCampaign,
  sendCampaignTest,
  type CrmCampaignRecipient,
} from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { formatDateTime } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'
import { CampaignEditorDialog } from '@/components/crm/marketing/CampaignEditorDialog'
import { CAMPAIGN_STATUS_VARIANT } from '@/pages/crm/CrmCampaignsPage'

const RECIPIENT_VARIANT = { pending: 'neutral', sent: 'success', failed: 'danger' } as const

export function CrmCampaignDetailPage() {
  const { id } = useParams<{ id: string }>()
  const campaignId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const me = useAuthStore((state) => state.user)

  const [editorOpen, setEditorOpen] = useState(false)
  const [testOpen, setTestOpen] = useState(false)
  const [scheduleOpen, setScheduleOpen] = useState(false)
  const [testEmail, setTestEmail] = useState('')
  const [scheduleAt, setScheduleAt] = useState('')
  const [filter, setFilter] = useState('all')
  const [page, setPage] = useState(1)

  const { data: campaign, isLoading } = useQuery({
    queryKey: ['crm-campaign', campaignId],
    queryFn: () => fetchCampaign(campaignId),
    enabled: !!campaignId,
    refetchInterval: (query) => (['sending', 'scheduled'].includes(query.state.data?.status ?? '') ? 5_000 : false),
  })
  const { data: recipients, isLoading: loadingRecipients } = useQuery({
    queryKey: ['crm-campaign-recipients', campaignId, filter, page, campaign?.stats.sent],
    queryFn: () => fetchCampaignRecipients(campaignId, { page, per_page: 20, filter: filter === 'all' ? undefined : filter }),
    enabled: !!campaign && campaign.status !== 'draft',
  })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['crm-campaign', campaignId] })
    queryClient.invalidateQueries({ queryKey: ['crm-campaigns'] })
  }
  const onError = (err: unknown) => toast.error(getApiErrorInfo(err).message)

  const sendNow = useMutation({ mutationFn: () => scheduleCampaign(campaignId), onSuccess: (r) => { toast.success(r.message); refresh() }, onError })
  const schedule = useMutation({
    mutationFn: () => scheduleCampaign(campaignId, new Date(scheduleAt).toISOString()),
    onSuccess: (r) => { toast.success(r.message); setScheduleOpen(false); refresh() },
    onError,
  })
  const cancel = useMutation({ mutationFn: () => cancelCampaign(campaignId), onSuccess: () => { toast.success('Campaign stopped'); refresh() }, onError })
  const remove = useMutation({
    mutationFn: () => deleteCampaign(campaignId),
    onSuccess: () => { toast.success('Campaign deleted'); queryClient.invalidateQueries({ queryKey: ['crm-campaigns'] }); navigate('/crm/campaigns') },
    onError,
  })
  const test = useMutation({
    mutationFn: () => sendCampaignTest(campaignId, testEmail.trim()),
    onSuccess: (message) => { toast.success(message); setTestOpen(false) },
    onError,
  })

  if (isLoading || !campaign) return <FlipLoaderBlock label="Loading campaign…" />

  const { stats, status } = campaign
  const editable = status === 'draft' || status === 'scheduled'
  const canSend = can('crm-campaigns.send')
  const progress = stats.total ? Math.round(((stats.sent + stats.failed) / stats.total) * 100) : 0

  const columns: DataTableColumn<CrmCampaignRecipient>[] = [
    {
      key: 'email',
      header: 'Recipient',
      render: (r) => (
        <div>
          <p className="font-medium text-foreground">{r.name || r.email}</p>
          {r.name && <p className="text-xs text-muted-foreground">{r.email}</p>}
        </div>
      ),
    },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge label={r.status} variant={RECIPIENT_VARIANT[r.status]} /> },
    { key: 'opened', header: 'Opened', accessor: (r) => (r.opened_at ? `Yes${r.open_count > 1 ? ` (${r.open_count}×)` : ''}` : '—') },
    { key: 'clicked', header: 'Clicked', accessor: (r) => (r.clicked_at ? `Yes${r.click_count > 1 ? ` (${r.click_count}×)` : ''}` : '—') },
    { key: 'unsub', header: 'Unsubscribed', accessor: (r) => (r.unsubscribed_at ? 'Yes' : '—') },
    {
      key: 'contact',
      header: 'Contact',
      render: (r) =>
        r.lead_id ? (
          <Link to={`/crm/leads/${r.lead_id}`} className="text-primary hover:underline" onClick={(e) => e.stopPropagation()}>Lead</Link>
        ) : r.customer_id ? (
          <Link to={`/customers/${r.customer_id}/statement`} className="text-primary hover:underline" onClick={(e) => e.stopPropagation()}>Customer</Link>
        ) : '—',
    },
  ]

  return (
    <div>
      <PageHeader
        parent="CRM · Campaigns"
        title={campaign.name}
        action={
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge label={status} variant={CAMPAIGN_STATUS_VARIANT[status]} />
            {editable && can('crm-campaigns.update') && (
              <Button variant="outline" size="sm" onClick={() => setEditorOpen(true)}>
                <Pencil className="h-4 w-4" /> Edit
              </Button>
            )}
            {canSend && (
              <Button variant="outline" size="sm" onClick={() => { setTestEmail(me?.email ?? ''); setTestOpen(true) }}>
                Send test
              </Button>
            )}
            {editable && canSend && (
              <>
                <Button variant="outline" size="sm" onClick={() => setScheduleOpen(true)}>
                  <CalendarClock className="h-4 w-4" /> Schedule
                </Button>
                <Button
                  size="sm"
                  disabled={sendNow.isPending}
                  onClick={() => window.confirm('Send this campaign to everyone in its audience now?') && sendNow.mutate()}
                >
                  <Send className="h-4 w-4" /> Send now
                </Button>
              </>
            )}
            {(status === 'sending' || status === 'scheduled') && canSend && (
              <Button variant="outline" size="sm" onClick={() => window.confirm(status === 'sending' ? 'Stop sending? People not yet emailed will not get it.' : 'Unschedule this campaign?') && cancel.mutate()}>
                <Square className="h-4 w-4" /> {status === 'sending' ? 'Stop' : 'Unschedule'}
              </Button>
            )}
            {status !== 'sending' && can('crm-campaigns.delete') && (
              <Button variant="ghost" size="sm" className="text-destructive" onClick={() => window.confirm('Delete this campaign and its statistics?') && remove.mutate()}>
                <Trash2 className="h-4 w-4" />
              </Button>
            )}
          </div>
        }
      />

      {status === 'scheduled' && campaign.scheduled_at && (
        <p className="mb-4 rounded-lg border border-border bg-muted/40 p-3 text-sm">
          Scheduled for <span className="font-medium">{formatDateTime(campaign.scheduled_at)}</span>. It goes out automatically.
        </p>
      )}
      {status === 'sending' && (
        <div className="mb-4 rounded-lg border border-border bg-card p-3">
          <div className="flex justify-between text-sm">
            <span>Sending… emails go out in small batches every minute.</span>
            <span className="font-medium">{progress}%</span>
          </div>
          <div className="mt-2 h-2 overflow-hidden rounded-full bg-muted">
            <div className="h-full bg-primary transition-all" style={{ width: `${progress}%` }} />
          </div>
        </div>
      )}

      {status !== 'draft' && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <StatCard label="Recipients" value={stats.total} />
          <StatCard label="Delivered" value={stats.sent} />
          <StatCard label="Opened" value={`${stats.open_rate}%`} subtext={`${stats.opened} people`} />
          <StatCard label="Clicked" value={`${stats.click_rate}%`} subtext={`${stats.clicked} people`} />
          <StatCard label="Unsubscribed" value={stats.unsubscribed} />
          <StatCard label="Failed" value={stats.failed} />
        </div>
      )}

      <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div className="rounded-xl border border-border bg-card">
          <div className="border-b border-border p-4">
            <p className="text-xs uppercase text-muted-foreground">Subject</p>
            <p className="font-medium">{campaign.subject}</p>
          </div>
          <p className="whitespace-pre-wrap p-4 text-sm leading-relaxed">{campaign.body}</p>
          <p className="border-t border-border px-4 py-2 text-xs text-muted-foreground">
            Created by {campaign.created_by?.name ?? '—'} · replies go to their inbox
          </p>
        </div>

        {status !== 'draft' && (
          <DataTable
            title="Recipients"
            columns={columns}
            data={recipients?.data ?? []}
            rowKey={(r) => r.id}
            isLoading={loadingRecipients}
            dateFilterKey={false}
            searchable={false}
            exportable={false}
            toolbarExtra={
              <Select value={filter} onValueChange={(v) => { setFilter(v); setPage(1) }}>
                <SelectTrigger className="h-8 w-36 text-sm">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Everyone</SelectItem>
                  <SelectItem value="opened">Opened</SelectItem>
                  <SelectItem value="clicked">Clicked</SelectItem>
                  <SelectItem value="unsubscribed">Unsubscribed</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                </SelectContent>
              </Select>
            }
            page={recipients?.meta.current_page}
            pageCount={recipients?.meta.last_page}
            totalRows={recipients?.meta.total}
            onPageChange={setPage}
            emptyTitle="Nobody here yet"
          />
        )}
      </div>

      <CampaignEditorDialog open={editorOpen} onOpenChange={setEditorOpen} campaign={campaign} />

      <Dialog open={testOpen} onOpenChange={setTestOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Send a test</DialogTitle>
            <DialogDescription>See exactly how the email looks before it goes out.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-1.5">
            <Label htmlFor="test-email">Send to</Label>
            <Input id="test-email" type="email" value={testEmail} onChange={(e) => setTestEmail(e.target.value)} />
          </div>
          <DialogFooter>
            <Button disabled={test.isPending || !/\S+@\S+\.\S+/.test(testEmail)} onClick={() => test.mutate()}>
              {test.isPending ? 'Sending…' : 'Send test'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={scheduleOpen} onOpenChange={setScheduleOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>Schedule campaign</DialogTitle>
            <DialogDescription>It starts sending automatically at this time.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-1.5">
            <Label htmlFor="schedule-at">Send at</Label>
            <Input id="schedule-at" type="datetime-local" value={scheduleAt} onChange={(e) => setScheduleAt(e.target.value)} />
          </div>
          <DialogFooter>
            <Button disabled={schedule.isPending || !scheduleAt || new Date(scheduleAt) <= new Date()} onClick={() => schedule.mutate()}>
              Schedule
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
