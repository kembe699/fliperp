import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Activity, Calendar, FileText, Handshake, Mail } from 'lucide-react'

import { fetchCrmDealDetail } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { CrmDrawerOverviewTab } from '@/components/crm/drawer/CrmDrawerOverviewTab'
import { CrmDrawerMeetingsTab } from '@/components/crm/drawer/CrmDrawerMeetingsTab'
import { CrmDrawerEmailsTab } from '@/components/crm/drawer/CrmDrawerEmailsTab'
import { CrmDrawerActivitiesTab } from '@/components/crm/drawer/CrmDrawerActivitiesTab'
import { CrmDrawerQuotationTab } from '@/components/crm/drawer/CrmDrawerQuotationTab'

export function CrmDealDetailPage() {
  const { id } = useParams<{ id: string }>()
  const dealId = Number(id)

  const { data: detail, isLoading } = useQuery({
    queryKey: ['crm-deal-detail', dealId],
    queryFn: () => fetchCrmDealDetail(dealId),
    enabled: !!dealId,
  })

  if (isLoading || !detail) {
    return <div className="p-6 text-sm text-muted-foreground">Loading deal…</div>
  }

  const { deal, services, meetings, emails, activities } = detail
  const queryKey = ['crm-deal-detail', dealId]

  const contactName = deal.customer?.name ?? deal.lead?.name ?? null
  const meetingTitle = `Meeting: ${deal.title}`
  const meetingDescription = `Regarding deal "${deal.title}" (${formatCurrency(deal.value)}) with ${contactName ?? 'the customer'}.`

  return (
    <div>
      <PageHeader
        parent="CRM · Pipeline"
        title={deal.title}
        action={deal.pipeline_stage && <StatusBadge label={deal.pipeline_stage.name} variant="info" />}
      />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Value" value={formatCurrency(deal.value)} />
        <StatCard label="Services" value={services.length} />
        <StatCard label="Upcoming Meetings" value={meetings.upcoming.length} />
        <StatCard label="Quotations" value={detail.quotations.length} />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Handshake className="h-4 w-4" />
          Overview
        </div>
        <CrmDrawerOverviewTab type="deal" detail={detail} />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Calendar className="h-4 w-4" />
          Meetings
        </div>
        <CrmDrawerMeetingsTab
          dealId={dealId}
          customerId={deal.customer_id}
          defaultContactName={contactName}
          defaultTitle={meetingTitle}
          defaultDescription={meetingDescription}
          upcoming={meetings.upcoming}
          past={meetings.past}
          queryKeyToInvalidate={queryKey}
        />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <FileText className="h-4 w-4" />
          Quotations
        </div>
        <CrmDrawerQuotationTab type="deal" detail={detail} queryKeyToInvalidate={queryKey} />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Mail className="h-4 w-4" />
          Emails
        </div>
        <CrmDrawerEmailsTab emails={emails} />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Activity className="h-4 w-4" />
          Activities
        </div>
        <CrmDrawerActivitiesTab dealId={dealId} activities={activities} queryKeyToInvalidate={queryKey} />
      </div>
    </div>
  )
}
