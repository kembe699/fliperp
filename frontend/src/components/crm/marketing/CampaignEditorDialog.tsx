import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import {
  fetchCampaignLeadSources,
  fetchLeadMagnets,
  previewCampaignAudience,
  saveCampaign,
  type CrmCampaign,
  type CrmCampaignAudience,
} from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'
import { cn } from '@/lib/utils'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'

const LEAD_STATUSES = [
  { value: 'open', label: 'Open' },
  { value: 'converted', label: 'Converted' },
  { value: 'disqualified', label: 'Disqualified' },
]

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  campaign: CrmCampaign | null
  onSaved?: (campaign: CrmCampaign) => void
}

/** Write or edit a campaign: name, subject, message and who it goes to (with a live count). */
export function CampaignEditorDialog({ open, onOpenChange, campaign, onSaved }: Props) {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [audience, setAudience] = useState<CrmCampaignAudience>({ type: 'leads', lead_statuses: ['open'] })
  const [debounced, setDebounced] = useState(audience)

  useEffect(() => {
    if (!open) return
    setName(campaign?.name ?? '')
    setSubject(campaign?.subject ?? '')
    setBody(campaign?.body ?? 'Hi {{first_name}},\n\n\n\nBest regards,\n{{company}}')
    setAudience(campaign?.audience ?? { type: 'leads', lead_statuses: ['open'] })
  }, [open, campaign])

  useEffect(() => {
    const id = setTimeout(() => setDebounced(audience), 350)
    return () => clearTimeout(id)
  }, [audience])

  const { data: sources } = useQuery({ queryKey: ['crm-campaign-lead-sources'], queryFn: fetchCampaignLeadSources, enabled: open })
  const { data: magnets } = useQuery({ queryKey: ['crm-lead-magnets'], queryFn: fetchLeadMagnets, enabled: open })
  const { data: preview, isFetching: counting } = useQuery({
    queryKey: ['crm-campaign-audience', debounced],
    queryFn: () => previewCampaignAudience(debounced),
    enabled: open,
  })

  const mutation = useMutation({
    mutationFn: () => saveCampaign({ name: name.trim(), subject: subject.trim(), body, audience }, campaign?.id),
    onSuccess: (saved) => {
      toast.success('Campaign saved')
      queryClient.invalidateQueries({ queryKey: ['crm-campaigns'] })
      queryClient.invalidateQueries({ queryKey: ['crm-campaign', saved.id] })
      onOpenChange(false)
      onSaved?.(saved)
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const includesLeads = audience.type !== 'customers'
  const toggleIn = (key: 'lead_statuses' | 'lead_sources', value: string) => {
    const current = audience[key] ?? []
    setAudience({ ...audience, [key]: current.includes(value) ? current.filter((v) => v !== value) : [...current, value] })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92dvh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{campaign ? 'Edit campaign' : 'New email campaign'}</DialogTitle>
        </DialogHeader>
        <form
          id="campaign-form"
          className="grid gap-4"
          onSubmit={(e) => {
            e.preventDefault()
            if (!mutation.isPending) mutation.mutate()
          }}
        >
          <div className="grid gap-1.5">
            <Label htmlFor="cmp-name">Campaign name</Label>
            <Input id="cmp-name" required value={name} onChange={(e) => setName(e.target.value)} placeholder="Internal, e.g. November newsletter" />
          </div>

          <fieldset className="grid gap-3 rounded-lg border border-border p-3">
            <legend className="px-1 text-sm font-medium">Who gets it</legend>
            <Select value={audience.type} onValueChange={(v) => setAudience({ ...audience, type: v as CrmCampaignAudience['type'] })}>
              <SelectTrigger className="sm:w-64">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="leads">Leads</SelectItem>
                <SelectItem value="customers">Customers</SelectItem>
                <SelectItem value="all">Leads and customers</SelectItem>
              </SelectContent>
            </Select>
            {includesLeads && (
              <>
                <div className="grid gap-1.5">
                  <span className="text-xs font-medium text-muted-foreground">Lead status (none ticked = all)</span>
                  <div className="flex flex-wrap gap-1.5">
                    {LEAD_STATUSES.map((s) => (
                      <Chip key={s.value} active={(audience.lead_statuses ?? []).includes(s.value)} onClick={() => toggleIn('lead_statuses', s.value)}>
                        {s.label}
                      </Chip>
                    ))}
                  </div>
                </div>
                {(sources ?? []).length > 0 && (
                  <div className="grid gap-1.5">
                    <span className="text-xs font-medium text-muted-foreground">Lead source (none ticked = all)</span>
                    <div className="flex max-h-28 flex-wrap gap-1.5 overflow-y-auto">
                      {sources!.map((s) => (
                        <Chip key={s} active={(audience.lead_sources ?? []).includes(s)} onClick={() => toggleIn('lead_sources', s)}>
                          {s}
                        </Chip>
                      ))}
                    </div>
                  </div>
                )}
                {(magnets ?? []).length > 0 && (
                  <div className="grid gap-1.5 sm:w-80">
                    <span className="text-xs font-medium text-muted-foreground">Only people who downloaded</span>
                    <Select
                      value={audience.lead_magnet_id ? String(audience.lead_magnet_id) : 'any'}
                      onValueChange={(v) => setAudience({ ...audience, lead_magnet_id: v === 'any' ? null : Number(v) })}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="any">Any lead magnet (no filter)</SelectItem>
                        {magnets!.map((m) => (
                          <SelectItem key={m.id} value={String(m.id)}>
                            {m.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                )}
              </>
            )}
            <p className="text-sm">
              {counting && !preview ? (
                'Counting…'
              ) : (
                <>
                  <span className="font-semibold">{preview?.count ?? 0}</span> {preview?.count === 1 ? 'person' : 'people'} with an email address
                  <span className="text-muted-foreground"> (anyone who unsubscribed is left out)</span>
                </>
              )}
            </p>
          </fieldset>

          <div className="grid gap-1.5">
            <Label htmlFor="cmp-subject">Subject</Label>
            <Input id="cmp-subject" required value={subject} onChange={(e) => setSubject(e.target.value)} placeholder="e.g. {{first_name}}, our new offer is here" />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="cmp-body">Message</Label>
            <Textarea id="cmp-body" required rows={12} value={body} onChange={(e) => setBody(e.target.value)} />
            <p className="text-xs text-muted-foreground">
              Personalise with <code>{'{{first_name}}'}</code>, <code>{'{{name}}'}</code> and <code>{'{{company}}'}</code>. Links (https://…) become clickable and clicks are counted. An unsubscribe link is added automatically.
            </p>
          </div>
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="campaign-form" disabled={mutation.isPending}>
            {mutation.isPending ? 'Saving…' : 'Save draft'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function Chip({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn('h-7 rounded-full border px-3 text-xs transition-colors', active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-muted')}
    >
      {children}
    </button>
  )
}
