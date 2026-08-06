import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'

import { fetchCrmLeadDetail } from '@/api/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { CrmDrawerOverviewTab } from '@/components/crm/drawer/CrmDrawerOverviewTab'
import { CrmDrawerMeetingsTab } from '@/components/crm/drawer/CrmDrawerMeetingsTab'
import { CrmDrawerEmailsTab } from '@/components/crm/drawer/CrmDrawerEmailsTab'
import { CrmDrawerActivitiesTab } from '@/components/crm/drawer/CrmDrawerActivitiesTab'
import { CrmDrawerQuotationTab } from '@/components/crm/drawer/CrmDrawerQuotationTab'

const STATUS_VARIANT = { open: 'info', converted: 'success', disqualified: 'neutral' } as const

export function CrmLeadDetailPage() {
  const { id } = useParams<{ id: string }>()
  const leadId = Number(id)

  const { data: detail, isLoading } = useQuery({
    queryKey: ['crm-lead-detail', leadId],
    queryFn: () => fetchCrmLeadDetail(leadId),
    enabled: !!leadId,
  })

  if (isLoading || !detail) {
    return <div className="p-6 text-sm text-muted-foreground">Loading lead…</div>
  }

  const { lead, services, meetings, emails, activities } = detail
  const queryKey = ['crm-lead-detail', leadId]

  const meetingTitle = `Meeting: ${lead.name}`
  const meetingDescription = `Regarding lead "${lead.name}"${lead.company_name ? ` at ${lead.company_name}` : ''}.`

  return (
    <div>
      <PageHeader parent="CRM · Leads" title={lead.name} action={<StatusBadge label={lead.status} variant={STATUS_VARIANT[lead.status]} />} />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Status" value={lead.status} />
        <StatCard label="Services" value={services.length} />
        <StatCard label="Upcoming Meetings" value={meetings.upcoming.length} />
        <StatCard label="Quotations" value={detail.quotations.length} />
      </div>

      <Tabs defaultValue="overview" className="mt-6">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="meetings">Meetings</TabsTrigger>
          <TabsTrigger value="quotations">Quotations</TabsTrigger>
          <TabsTrigger value="emails">Emails</TabsTrigger>
          <TabsTrigger value="activities">Activities</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <CrmDrawerOverviewTab type="lead" detail={detail} />
        </TabsContent>

        <TabsContent value="meetings">
          <CrmDrawerMeetingsTab
            leadId={leadId}
            defaultContactName={lead.name}
            defaultContactEmail={lead.email}
            defaultTitle={meetingTitle}
            defaultDescription={meetingDescription}
            upcoming={meetings.upcoming}
            past={meetings.past}
            queryKeyToInvalidate={queryKey}
          />
        </TabsContent>

        <TabsContent value="quotations">
          <CrmDrawerQuotationTab type="lead" detail={detail} queryKeyToInvalidate={queryKey} />
        </TabsContent>

        <TabsContent value="emails">
          <CrmDrawerEmailsTab emails={emails} />
        </TabsContent>

        <TabsContent value="activities">
          <CrmDrawerActivitiesTab leadId={leadId} activities={activities} queryKeyToInvalidate={queryKey} />
        </TabsContent>
      </Tabs>
    </div>
  )
}
