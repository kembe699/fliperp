import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmDeal, fetchCrmLeads, fetchCrmCustomers, fetchCrmServices } from '@/api/crm'
import { fetchUsers } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { CrmPipelineStage } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface CrmDealFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  stages: CrmPipelineStage[]
  defaultStageId?: number
}

export function CrmDealFormDialog({ open, onOpenChange, stages, defaultStageId }: CrmDealFormDialogProps) {
  const queryClient = useQueryClient()

  const [targetType, setTargetType] = useState<'lead' | 'customer'>('customer')
  const [leadId, setLeadId] = useState<string | null>(null)
  const [customerId, setCustomerId] = useState<string | null>(null)
  const [pipelineStageId, setPipelineStageId] = useState<string>('')
  const [serviceId, setServiceId] = useState<string>('')
  const [title, setTitle] = useState('')
  const [expectedCloseDate, setExpectedCloseDate] = useState('')
  const [assignedTo, setAssignedTo] = useState<string>('')
  const [error, setError] = useState<string | null>(null)

  const { data: leads } = useQuery({
    queryKey: ['crm-leads-open-all'],
    queryFn: () => fetchCrmLeads({ status: 'open', per_page: 100 }),
    enabled: open && targetType === 'lead',
  })
  const { data: customers } = useQuery({
    queryKey: ['crm-customers-all'],
    queryFn: () => fetchCrmCustomers({ per_page: 100 }),
    enabled: open && targetType === 'customer',
  })
  const { data: services } = useQuery({
    queryKey: ['crm-services-all'],
    queryFn: () => fetchCrmServices({ per_page: 100, is_active: true }),
    enabled: open,
  })
  const { data: users } = useQuery({ queryKey: ['settings-users-all'], queryFn: () => fetchUsers({ per_page: 100 }), enabled: open })

  useEffect(() => {
    if (!open) return
    setTargetType('customer')
    setLeadId(null)
    setCustomerId(null)
    setPipelineStageId(defaultStageId ? String(defaultStageId) : stages[0] ? String(stages[0].id) : '')
    setServiceId('')
    setTitle('')
    setExpectedCloseDate('')
    setAssignedTo('')
    setError(null)
  }, [open, defaultStageId, stages])

  const mutation = useMutation({
    mutationFn: () =>
      createCrmDeal({
        lead_id: targetType === 'lead' && leadId ? Number(leadId) : null,
        customer_id: targetType === 'customer' && customerId ? Number(customerId) : null,
        pipeline_stage_id: Number(pipelineStageId),
        crm_service_id: serviceId ? Number(serviceId) : null,
        title,
        expected_close_date: expectedCloseDate || null,
        assigned_to: assignedTo ? Number(assignedTo) : null,
      }),
    onSuccess: () => {
      toast.success('Deal created')
      queryClient.invalidateQueries({ queryKey: ['crm-kanban'] })
      queryClient.invalidateQueries({ queryKey: ['crm-report-summary'] })
      onOpenChange(false)
    },
    onError: (err) => {
      const info = getApiErrorInfo(err)
      setError(info.message)
    },
  })

  const canSubmit =
    title.trim() &&
    pipelineStageId &&
    ((targetType === 'lead' && leadId) || (targetType === 'customer' && customerId))

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New Deal</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Linked To</Label>
            <div className="flex gap-2">
              <Button
                type="button"
                size="sm"
                variant={targetType === 'customer' ? 'default' : 'outline'}
                onClick={() => setTargetType('customer')}
              >
                Customer
              </Button>
              <Button type="button" size="sm" variant={targetType === 'lead' ? 'default' : 'outline'} onClick={() => setTargetType('lead')}>
                Lead
              </Button>
            </div>
          </div>

          {targetType === 'customer' ? (
            <div className="space-y-1.5">
              <Label>Customer</Label>
              <SearchableSelect
                value={customerId}
                onChange={setCustomerId}
                placeholder="Select a customer"
                options={(customers?.data ?? []).map((c) => ({ value: String(c.id), label: c.name, sublabel: c.phone }))}
              />
            </div>
          ) : (
            <div className="space-y-1.5">
              <Label>Lead</Label>
              <SearchableSelect
                value={leadId}
                onChange={setLeadId}
                placeholder="Select a lead"
                options={(leads?.data ?? []).map((l) => ({ value: String(l.id), label: l.name, sublabel: l.company_name ?? undefined }))}
              />
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="deal-title">Title</Label>
            <Input id="deal-title" value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>

          <p className="text-xs text-muted-foreground">
            Deal value isn't set here — it's calculated automatically once you attach services on the deal's Overview tab.
          </p>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Pipeline Stage</Label>
              <Select value={pipelineStageId} onValueChange={setPipelineStageId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select a stage" />
                </SelectTrigger>
                <SelectContent>
                  {stages.map((stage) => (
                    <SelectItem key={stage.id} value={String(stage.id)}>
                      {stage.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="expected-close">Expected Close</Label>
              <Input id="expected-close" type="date" value={expectedCloseDate} onChange={(e) => setExpectedCloseDate(e.target.value)} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Service</Label>
              <Select value={serviceId} onValueChange={setServiceId}>
                <SelectTrigger>
                  <SelectValue placeholder="Optional" />
                </SelectTrigger>
                <SelectContent>
                  {services?.data.map((service) => (
                    <SelectItem key={service.id} value={String(service.id)}>
                      {service.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Assigned To</Label>
              <Select value={assignedTo} onValueChange={setAssignedTo}>
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

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Deal
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
