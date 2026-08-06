import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { syncCrmDealServices, syncCrmLeadServices, updateCrmDeal, updateCrmLead } from '@/api/crm'
import { fetchUsers } from '@/api/settings'
import { formatCurrency } from '@/lib/currency'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmDealDetail, CrmLeadDetail } from '@/types/crm'

import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { InlineTextField } from '@/components/crm/InlineTextField'
import { ServiceTagPicker } from '@/components/crm/ServiceTagPicker'

const SOURCE_OPTIONS = ['referral', 'website', 'cold_call', 'social_media', 'event', 'other']

interface CrmDrawerOverviewTabProps {
  type: 'lead' | 'deal'
  detail: CrmLeadDetail | CrmDealDetail
}

export function CrmDrawerOverviewTab({ type, detail }: CrmDrawerOverviewTabProps) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const id = type === 'lead' ? (detail as CrmLeadDetail).lead.id : (detail as CrmDealDetail).deal.id
  const canEdit = type === 'lead' ? can('crm-leads.update') : can('crm-deals.update')
  const detailQueryKey = type === 'lead' ? ['crm-lead-detail', id] : ['crm-deal-detail', id]
  const listQueryKey = type === 'lead' ? ['crm-leads'] : ['crm-kanban']

  const { data: users } = useQuery({ queryKey: ['settings-users-all'], queryFn: () => fetchUsers({ per_page: 100 }) })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: detailQueryKey })
    queryClient.invalidateQueries({ queryKey: listQueryKey })
  }

  const patchLeadMutation = useMutation({
    mutationFn: (values: Record<string, unknown>) => updateCrmLead(id, values),
    onSuccess: () => {
      toast.success('Lead updated')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const patchDealMutation = useMutation({
    mutationFn: (values: Record<string, unknown>) => updateCrmDeal(id, values),
    onSuccess: () => {
      toast.success('Deal updated')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const assignHandler = (userId: string) => {
    const payload = { assigned_to: userId ? Number(userId) : null }
    if (type === 'lead') {
      patchLeadMutation.mutate(payload)
    } else {
      patchDealMutation.mutate(payload)
    }
  }

  if (type === 'lead') {
    const { lead, services } = detail as CrmLeadDetail

    return (
      <div className="space-y-6">
        <Card>
          <CardContent className="p-4">
            <div className="grid grid-cols-2 gap-4">
              <InlineTextField label="Name" value={lead.name} disabled={!canEdit} onSave={(v) => patchLeadMutation.mutateAsync({ name: v })} />
              <InlineTextField label="Company" value={lead.company_name} disabled={!canEdit} onSave={(v) => patchLeadMutation.mutateAsync({ company_name: v || null })} />
              <InlineTextField label="Email" type="email" value={lead.email} disabled={!canEdit} onSave={(v) => patchLeadMutation.mutateAsync({ email: v || null })} />
              <InlineTextField label="Phone" value={lead.phone} disabled={!canEdit} onSave={(v) => patchLeadMutation.mutateAsync({ phone: v || null })} />
              <div className="space-y-1.5">
                <Label>Source</Label>
                <Select
                  value={lead.source ?? undefined}
                  disabled={!canEdit}
                  onValueChange={(value) => patchLeadMutation.mutate({ source: value })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Not set" />
                  </SelectTrigger>
                  <SelectContent>
                    {SOURCE_OPTIONS.map((source) => (
                      <SelectItem key={source} value={source}>
                        {source.replace('_', ' ')}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Assigned Handler</Label>
                <Select
                  value={lead.assigned_to ? String(lead.assigned_to.id) : undefined}
                  disabled={!canEdit}
                  onValueChange={(value) => assignHandler(value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Unassigned" />
                  </SelectTrigger>
                  <SelectContent>
                    {users?.data.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>
                        {u.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-4">
            <ServiceTagPicker
              services={services}
              disabled={!canEdit}
              queryKeyToInvalidate={detailQueryKey}
              onSync={(ids) => syncCrmLeadServices(id, ids)}
            />
          </CardContent>
        </Card>
      </div>
    )
  }

  const { deal, services } = detail as CrmDealDetail

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 gap-4">
        <Card>
          <CardContent className="p-4">
            <div className="grid grid-cols-2 gap-4">
              <InlineTextField label="Title" value={deal.title} disabled={!canEdit} onSave={(v) => patchDealMutation.mutateAsync({ title: v })} />
              <InlineTextField
                label="Expected Close Date"
                type="date"
                value={deal.expected_close_date}
                disabled={!canEdit}
                onSave={(v) => patchDealMutation.mutateAsync({ expected_close_date: v || null })}
              />
              <div className="col-span-2 space-y-1.5">
                <Label>Assigned Handler</Label>
                <Select
                  value={deal.assigned_to ? String(deal.assigned_to.id) : undefined}
                  disabled={!canEdit}
                  onValueChange={(value) => assignHandler(value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Unassigned" />
                  </SelectTrigger>
                  <SelectContent>
                    {users?.data.map((u) => (
                      <SelectItem key={u.id} value={String(u.id)}>
                        {u.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="grid grid-cols-2 gap-4 p-4 text-sm">
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Customer</p>
              <p className="mt-1 text-foreground">{deal.customer?.name ?? '—'}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Original Lead</p>
              <p className="mt-1 text-foreground">{deal.lead?.name ?? '—'}</p>
            </div>
            <div className="col-span-2">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Deal Value</p>
              <p className="mt-1 font-semibold text-foreground">{formatCurrency(deal.value)}</p>
              <p className="mt-0.5 text-xs text-muted-foreground">Calculated from attached services</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardContent className="p-4">
          <ServiceTagPicker
            label="Attached Services"
            emptyText="No services attached to this deal yet."
            services={services}
            disabled={!canEdit}
            queryKeyToInvalidate={detailQueryKey}
            onSync={(ids) => syncCrmDealServices(id, ids)}
          />
        </CardContent>
      </Card>
    </div>
  )
}
