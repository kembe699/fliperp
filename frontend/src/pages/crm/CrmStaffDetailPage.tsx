import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Calendar, Handshake, Mail, TrendingUp, Users, AlertCircle } from 'lucide-react'

import { fetchCrmEmails, fetchCrmMeetings, fetchCrmStaffReport } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import type { CrmEmail, CrmMeeting, CrmStaffDeal } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'

const EMAIL_STATUS_VARIANT: Record<CrmEmail['status'], 'warning' | 'success' | 'danger'> = {
  queued: 'warning',
  sent: 'success',
  failed: 'danger',
}

export function CrmStaffDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const userId = Number(id)

  const { data, isLoading } = useQuery({ queryKey: ['crm-report-staff'], queryFn: fetchCrmStaffReport })
  const staff = data?.find((row) => row.user_id === userId)

  const { data: meetings } = useQuery({
    queryKey: ['crm-meetings', 'organizer', userId],
    queryFn: () => fetchCrmMeetings({ organizer_id: userId, status: 'scheduled', per_page: 20 }),
    enabled: !!userId,
  })

  const { data: emails } = useQuery({
    queryKey: ['crm-emails', 'sent-by', userId],
    queryFn: () => fetchCrmEmails({ sent_by: userId, per_page: 20 }),
    enabled: !!userId,
  })

  const goToMeetingContext = (meeting: CrmMeeting) => {
    if (meeting.deal_id) navigate(`/crm/deals/${meeting.deal_id}`)
    else if (meeting.lead_id) navigate(`/crm/leads/${meeting.lead_id}`)
    else if (meeting.customer_id) navigate(`/customers/${meeting.customer_id}/statement`)
  }

  const dealColumns: DataTableColumn<CrmStaffDeal>[] = [
    { key: 'title', header: 'Deal', accessor: (row) => row.title },
    { key: 'stage_name', header: 'Stage', accessor: (row) => row.stage_name ?? '—' },
    { key: 'value', header: 'Value', render: (row) => formatCurrency(row.value) },
  ]

  const meetingColumns: DataTableColumn<CrmMeeting>[] = [
    { key: 'title', header: 'Title', accessor: (row) => row.title },
    {
      key: 'scheduled_at',
      header: 'When',
      accessor: (row) => row.scheduled_at,
      render: (row) => new Date(row.scheduled_at).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }),
    },
    {
      key: 'related_to',
      header: 'Related To',
      render: (row) => row.deal_id ? 'Deal' : row.lead_id ? 'Lead' : row.customer_id ? 'Customer' : '—',
    },
  ]

  const emailColumns: DataTableColumn<CrmEmail>[] = [
    { key: 'subject', header: 'Subject', accessor: (row) => row.subject },
    { key: 'to', header: 'Recipient', render: (row) => (row.to_name ? `${row.to_name} <${row.to_email}>` : row.to_email) },
    { key: 'created_at', header: 'Date', accessor: (row) => row.created_at, render: (row) => formatDate(row.created_at) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status} variant={EMAIL_STATUS_VARIANT[row.status]} /> },
  ]

  if (isLoading) {
    return <div className="p-6 text-sm text-muted-foreground">Loading staff member…</div>
  }

  if (!staff) {
    return (
      <div>
        <PageHeader parent="CRM · Staff" title="Staff Member Not Found" />
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
          No staff performance data was found for this user.
        </p>
      </div>
    )
  }

  return (
    <div>
      <PageHeader parent="CRM · Staff" title={staff.user_name} />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <StatCard icon={Users} label="Customers Assigned" value={staff.customers_assigned} />
        <StatCard icon={AlertCircle} label="Open Activities" value={staff.open_activities_count} />
        <StatCard icon={AlertCircle} label="Open Complaints" value={staff.open_complaints_count} />
        <StatCard icon={TrendingUp} label="Closed Won Value" value={formatCurrency(staff.closed_won_value)} />
        <StatCard icon={Calendar} label="Upcoming Meetings" value={staff.upcoming_meetings_count} />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Handshake className="h-4 w-4" />
          Deals
        </div>
        <DataTable
          columns={dealColumns}
          data={staff.deals}
          rowKey={(row) => row.deal_id}
          emptyTitle="No deals"
          emptySubtext="This staff member has no deals assigned yet."
        />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Calendar className="h-4 w-4" />
          Meetings
        </div>
        <DataTable
          columns={meetingColumns}
          data={meetings?.data ?? []}
          rowKey={(row) => row.id}
          rowActions={(row) => [{ label: 'View', onClick: goToMeetingContext }]}
          emptyTitle="No upcoming meetings"
          emptySubtext="This staff member has no scheduled meetings coming up."
        />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Mail className="h-4 w-4" />
          Email Activity
        </div>
        <DataTable
          columns={emailColumns}
          data={emails?.data ?? []}
          rowKey={(row) => row.id}
          emptyTitle="No emails sent"
          emptySubtext="This staff member hasn't sent any CRM emails yet."
        />
      </div>
    </div>
  )
}
