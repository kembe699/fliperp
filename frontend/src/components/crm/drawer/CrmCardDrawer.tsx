import { useQuery } from '@tanstack/react-query'

import { fetchCrmDealDetail, fetchCrmLeadDetail } from '@/api/crm'
import type { CrmDealDetail, CrmLeadDetail } from '@/types/crm'

import { Sheet, SheetBody, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { CrmDrawerOverviewTab } from '@/components/crm/drawer/CrmDrawerOverviewTab'
import { CrmDrawerMeetingsTab } from '@/components/crm/drawer/CrmDrawerMeetingsTab'
import { CrmDrawerEmailsTab } from '@/components/crm/drawer/CrmDrawerEmailsTab'
import { CrmDrawerActivitiesTab } from '@/components/crm/drawer/CrmDrawerActivitiesTab'
import { CrmDrawerQuotationTab } from '@/components/crm/drawer/CrmDrawerQuotationTab'

const LEAD_STATUS_VARIANT = { open: 'info', converted: 'success', disqualified: 'neutral' } as const

interface CrmCardDrawerProps {
  type: 'lead' | 'deal' | null
  id: number | null
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function CrmCardDrawer({ type, id, open, onOpenChange }: CrmCardDrawerProps) {
  const isLead = type === 'lead'
  const detailQueryKey = isLead ? ['crm-lead-detail', id] : ['crm-deal-detail', id]

  const { data: detail, isLoading } = useQuery<CrmLeadDetail | CrmDealDetail>({
    queryKey: detailQueryKey,
    queryFn: (): Promise<CrmLeadDetail | CrmDealDetail> => (isLead ? fetchCrmLeadDetail(id!) : fetchCrmDealDetail(id!)),
    enabled: open && !!id && !!type,
  })

  const lead = detail && isLead ? (detail as CrmLeadDetail).lead : null
  const deal = detail && !isLead ? (detail as CrmDealDetail).deal : null

  const leadId = isLead ? (id ?? undefined) : undefined
  const dealId = !isLead ? (id ?? undefined) : undefined
  const contactEmail = lead?.email ?? null
  const contactName = lead?.name ?? deal?.customer?.name ?? null
  const customerId = deal?.customer_id ?? null

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent widthClassName="max-w-3xl">
        <SheetHeader>
          <div className="flex items-center gap-2 pr-8">
            <SheetTitle className="truncate">{lead?.name ?? deal?.title ?? (isLoading ? 'Loading…' : 'Not found')}</SheetTitle>
            {lead && <StatusBadge label={lead.status} variant={LEAD_STATUS_VARIANT[lead.status]} />}
            {deal?.pipeline_stage && <StatusBadge label={deal.pipeline_stage.name} variant="info" />}
          </div>
          {deal && <p className="text-sm text-muted-foreground">{deal.customer?.name ?? deal.lead?.name ?? 'Unlinked'}</p>}
        </SheetHeader>

        <SheetBody>
          {isLoading || !detail ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : (
            <Tabs defaultValue="overview">
              <TabsList>
                <TabsTrigger value="overview">Overview</TabsTrigger>
                <TabsTrigger value="meetings">Meetings</TabsTrigger>
                <TabsTrigger value="emails">Emails</TabsTrigger>
                <TabsTrigger value="quotation">Quotation</TabsTrigger>
                <TabsTrigger value="activities">Activities</TabsTrigger>
              </TabsList>

              <TabsContent value="overview">
                <CrmDrawerOverviewTab type={isLead ? 'lead' : 'deal'} detail={detail} />
              </TabsContent>

              <TabsContent value="meetings">
                <CrmDrawerMeetingsTab
                  leadId={leadId}
                  dealId={dealId}
                  customerId={customerId}
                  defaultContactName={contactName}
                  defaultContactEmail={contactEmail}
                  upcoming={detail.meetings.upcoming}
                  past={detail.meetings.past}
                  queryKeyToInvalidate={detailQueryKey}
                />
              </TabsContent>

              <TabsContent value="emails">
                <CrmDrawerEmailsTab
                  leadId={leadId}
                  dealId={dealId}
                  customerId={customerId}
                  defaultToEmail={contactEmail}
                  defaultToName={contactName}
                  emails={detail.emails}
                  queryKeyToInvalidate={detailQueryKey}
                />
              </TabsContent>

              <TabsContent value="quotation">
                <CrmDrawerQuotationTab type={isLead ? 'lead' : 'deal'} detail={detail} queryKeyToInvalidate={detailQueryKey} />
              </TabsContent>

              <TabsContent value="activities">
                <CrmDrawerActivitiesTab
                  leadId={leadId}
                  dealId={dealId}
                  activities={detail.activities}
                  queryKeyToInvalidate={detailQueryKey}
                />
              </TabsContent>
            </Tabs>
          )}
        </SheetBody>
      </SheetContent>
    </Sheet>
  )
}
