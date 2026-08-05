import { useState } from 'react'
import { Plus } from 'lucide-react'

import { usePermissions } from '@/hooks/use-permissions'
import type { CrmDealDetail, CrmLeadDetail } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/shared/EmptyState'
import { ConvertLeadDialog } from '@/components/crm/ConvertLeadDialog'
import { CreateCrmQuotationDialog } from '@/components/crm/drawer/CreateCrmQuotationDialog'
import { CrmQuotationCard } from '@/components/crm/drawer/CrmQuotationCard'

interface CrmDrawerQuotationTabProps {
  type: 'lead' | 'deal'
  detail: CrmLeadDetail | CrmDealDetail
  queryKeyToInvalidate: unknown[]
}

export function CrmDrawerQuotationTab({ type, detail, queryKeyToInvalidate }: CrmDrawerQuotationTabProps) {
  const { can } = usePermissions()
  const [createOpen, setCreateOpen] = useState(false)
  const [convertOpen, setConvertOpen] = useState(false)

  if (type === 'lead') {
    const { lead, services, quotations } = detail as CrmLeadDetail

    if (lead.status !== 'converted') {
      return (
        <div className="space-y-4">
          <EmptyState
            title="This lead hasn't been converted yet"
            subtext="Quotations are created against real customers — convert this lead to a customer first, then come back here to quote it."
          />
          <div className="flex justify-center">
            <Button onClick={() => setConvertOpen(true)}>Convert to Customer</Button>
          </div>
          <ConvertLeadDialog open={convertOpen} onOpenChange={setConvertOpen} lead={lead} />
        </div>
      )
    }

    return (
      <div className="space-y-4">
        {can('quotations.create') && (
          <div className="flex justify-end">
            <Button size="sm" onClick={() => setCreateOpen(true)}>
              <Plus className="h-4 w-4" />
              New Quotation
            </Button>
          </div>
        )}

        {quotations.length === 0 ? (
          <EmptyState title="No quotations yet" subtext="Create one from this lead's interested services." />
        ) : (
          <div className="space-y-3">
            {quotations.map((summary) => (
              <CrmQuotationCard
                key={summary.id}
                summary={summary}
                leadId={lead.id}
                contactEmail={lead.email}
                contactName={lead.name}
                queryKeyToInvalidate={queryKeyToInvalidate}
                emailsQueryKeyToInvalidate={queryKeyToInvalidate}
              />
            ))}
          </div>
        )}

        <CreateCrmQuotationDialog
          open={createOpen}
          onOpenChange={setCreateOpen}
          type="lead"
          id={lead.id}
          attachedServices={services}
          queryKeyToInvalidate={queryKeyToInvalidate}
        />
      </div>
    )
  }

  const { deal, services, quotations } = detail as CrmDealDetail

  if (!deal.customer_id) {
    return (
      <EmptyState
        title="This deal isn't linked to a customer yet"
        subtext="Quotations need a real customer — link this deal to a customer, or convert its originating lead, before quoting it."
      />
    )
  }

  return (
    <div className="space-y-4">
      {can('quotations.create') && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="h-4 w-4" />
            New Quotation
          </Button>
        </div>
      )}

      {quotations.length === 0 ? (
        <EmptyState title="No quotations yet" subtext="Create one from this deal's attached services." />
      ) : (
        <div className="space-y-3">
          {quotations.map((summary) => (
            <CrmQuotationCard
              key={summary.id}
              summary={summary}
              dealId={deal.id}
              contactName={deal.customer?.name}
              queryKeyToInvalidate={queryKeyToInvalidate}
              emailsQueryKeyToInvalidate={queryKeyToInvalidate}
            />
          ))}
        </div>
      )}

      <CreateCrmQuotationDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        type="deal"
        id={deal.id}
        attachedServices={services}
        queryKeyToInvalidate={queryKeyToInvalidate}
      />
    </div>
  )
}
