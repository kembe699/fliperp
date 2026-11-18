import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Copy, ExternalLink, FileDown, Plus } from 'lucide-react'

import {
  deleteLeadMagnet,
  fetchLeadMagnetSubmissions,
  fetchLeadMagnets,
  saveLeadMagnet,
  type CrmLeadMagnet,
  type CrmLeadMagnetInput,
} from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatDateTime } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { EmptyState } from '@/components/shared/EmptyState'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

/** CRM > Lead Magnets: a free download offered on a public page in exchange for contact details. */
export function CrmLeadMagnetsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<CrmLeadMagnet | null>(null)
  const [viewing, setViewing] = useState<CrmLeadMagnet | null>(null)

  const { data: magnets, isLoading } = useQuery({ queryKey: ['crm-lead-magnets'], queryFn: fetchLeadMagnets , staleTime: 0 })
  const remove = useMutation({
    mutationFn: (id: number) => deleteLeadMagnet(id),
    onSuccess: () => {
      toast.success('Lead magnet deleted')
      queryClient.invalidateQueries({ queryKey: ['crm-lead-magnets'] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })
  const copy = (url: string) => navigator.clipboard?.writeText(url).then(() => toast.success('Link copied'), () => toast.error('Could not copy'))

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Lead Magnets"
        action={
          can('crm-lead-magnets.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New lead magnet
            </Button>
          )
        }
      />
      <p className="mb-4 text-sm text-muted-foreground">
        Offer a guide, price list, checklist or catalogue. Visitors leave their name and email to get it — each one becomes a lead, and the file is emailed to them.
      </p>

      {isLoading ? (
        <FlipLoaderBlock label="Loading…" />
      ) : (magnets ?? []).length === 0 ? (
        <EmptyState title="No lead magnets yet" subtext="Create one, then share its link on social media, your website or WhatsApp." />
      ) : (
        <div className="grid gap-3 lg:grid-cols-2">
          {magnets!.map((m) => (
            <div key={m.id} className="rounded-xl border border-border bg-card p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="font-semibold">{m.title}</p>
                  <p className="flex items-center gap-1.5 truncate text-sm text-muted-foreground">
                    <FileDown className="h-3.5 w-3.5 shrink-0" />
                    {m.file_name ?? m.external_url ?? '—'}
                  </p>
                </div>
                <StatusBadge label={m.is_active ? 'Live' : 'Off'} variant={m.is_active ? 'success' : 'neutral'} />
              </div>
              <div className="mt-3 grid grid-cols-4 gap-2 text-center">
                {[
                  ['Views', m.views_count],
                  ['Sign-ups', m.submissions_count],
                  ['Conversion', `${m.conversion_rate}%`],
                  ['Downloads', m.downloads_count],
                ].map(([label, value]) => (
                  <div key={label} className="rounded-lg bg-muted/50 py-2">
                    <p className="text-lg font-semibold">{value}</p>
                    <p className="text-xs text-muted-foreground">{label}</p>
                  </div>
                ))}
              </div>
              <div className="mt-3 flex items-center gap-2 rounded-md border border-border bg-muted/40 px-2 py-1.5">
                <span className="min-w-0 flex-1 truncate text-sm">{m.public_url}</span>
                <Button variant="ghost" size="icon" className="h-7 w-7" aria-label="Copy link" onClick={() => copy(m.public_url)}>
                  <Copy className="h-3.5 w-3.5" />
                </Button>
                <a href={m.public_url} target="_blank" rel="noreferrer" aria-label="Open page" className="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-muted">
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
              </div>
              <div className="mt-2 flex justify-end gap-1">
                <Button variant="ghost" size="sm" onClick={() => setViewing(m)}>Sign-ups</Button>
                {can('crm-lead-magnets.update') && <Button variant="ghost" size="sm" onClick={() => { setEditing(m); setFormOpen(true) }}>Edit</Button>}
                {can('crm-lead-magnets.delete') && (
                  <Button variant="ghost" size="sm" className="text-destructive" onClick={() => window.confirm(`Delete "${m.title}"? Its link stops working; the leads stay.`) && remove.mutate(m.id)}>
                    Delete
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <LeadMagnetFormDialog open={formOpen} onOpenChange={setFormOpen} magnet={editing} />
      <SubmissionsDialog magnet={viewing} onClose={() => setViewing(null)} />
    </div>
  )
}

function LeadMagnetFormDialog({ open, onOpenChange, magnet }: { open: boolean; onOpenChange: (open: boolean) => void; magnet: CrmLeadMagnet | null }) {
  const queryClient = useQueryClient()
  const empty: CrmLeadMagnetInput = { title: '', headline: '', description: '', external_url: '', button_label: 'Get it now', thank_you_message: '', ask_phone: false, ask_company: false, is_active: true, file: null }
  const [form, setForm] = useState<CrmLeadMagnetInput>(empty)
  const set = <K extends keyof CrmLeadMagnetInput>(key: K, value: CrmLeadMagnetInput[K]) => setForm((f) => ({ ...f, [key]: value }))

  useEffect(() => {
    if (!open) return
    setForm(
      magnet
        ? {
            title: magnet.title,
            slug: magnet.slug,
            headline: magnet.headline ?? '',
            description: magnet.description ?? '',
            external_url: magnet.external_url ?? '',
            button_label: magnet.button_label,
            thank_you_message: magnet.thank_you_message ?? '',
            ask_phone: magnet.ask_phone,
            ask_company: magnet.ask_company,
            is_active: magnet.is_active,
            file: null,
          }
        : empty,
    )
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, magnet])

  const mutation = useMutation({
    mutationFn: () => saveLeadMagnet(form, magnet?.id),
    onSuccess: () => {
      toast.success(magnet ? 'Lead magnet saved' : 'Lead magnet created')
      queryClient.invalidateQueries({ queryKey: ['crm-lead-magnets'] })
      onOpenChange(false)
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92dvh] max-w-xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{magnet ? 'Edit lead magnet' : 'New lead magnet'}</DialogTitle>
        </DialogHeader>
        <form id="lead-magnet-form" className="grid gap-4" onSubmit={(e) => { e.preventDefault(); if (!mutation.isPending) mutation.mutate() }}>
          <div className="grid gap-1.5">
            <Label htmlFor="lm-title">Title</Label>
            <Input id="lm-title" required value={form.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g. 2026 Price List" />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="lm-headline">Headline</Label>
            <Input id="lm-headline" value={form.headline ?? ''} onChange={(e) => set('headline', e.target.value)} placeholder="e.g. Get our full price list, free" />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="lm-desc">Description</Label>
            <Textarea id="lm-desc" rows={4} value={form.description ?? ''} onChange={(e) => set('description', e.target.value)} placeholder="What's inside and why it's useful" />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="lm-file">File {magnet?.file_name && <span className="font-normal text-muted-foreground">(current: {magnet.file_name})</span>}</Label>
            <Input id="lm-file" type="file" onChange={(e) => set('file', e.target.files?.[0] ?? null)} />
            <p className="text-xs text-muted-foreground">PDF, Word, Excel, PowerPoint, image, ZIP… up to 20 MB. Or give a link instead:</p>
            <Input type="url" value={form.external_url ?? ''} onChange={(e) => set('external_url', e.target.value)} placeholder="https://… (optional, used if there's no file)" />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
              <Label htmlFor="lm-button">Button text</Label>
              <Input id="lm-button" value={form.button_label ?? ''} onChange={(e) => set('button_label', e.target.value)} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="lm-slug">Link name</Label>
              <Input id="lm-slug" value={form.slug ?? ''} onChange={(e) => set('slug', e.target.value)} placeholder="From the title if empty" />
            </div>
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="lm-thanks">Thank-you message</Label>
            <Textarea id="lm-thanks" rows={2} value={form.thank_you_message ?? ''} onChange={(e) => set('thank_you_message', e.target.value)} placeholder="Shown after sign-up" />
          </div>
          <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <label className="flex items-center gap-2"><input type="checkbox" className="h-4 w-4 accent-primary" checked={form.ask_phone} onChange={(e) => set('ask_phone', e.target.checked)} /> Ask for phone number</label>
            <label className="flex items-center gap-2"><input type="checkbox" className="h-4 w-4 accent-primary" checked={form.ask_company} onChange={(e) => set('ask_company', e.target.checked)} /> Ask for company</label>
            <label className="flex items-center gap-2"><input type="checkbox" className="h-4 w-4 accent-primary" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} /> Live</label>
          </div>
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>Cancel</Button>
          <Button type="submit" form="lead-magnet-form" disabled={mutation.isPending}>{mutation.isPending ? 'Saving…' : 'Save'}</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function SubmissionsDialog({ magnet, onClose }: { magnet: CrmLeadMagnet | null; onClose: () => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['crm-lead-magnet-submissions', magnet?.id],
    queryFn: () => fetchLeadMagnetSubmissions(magnet!.id),
    enabled: !!magnet,
  })
  return (
    <Dialog open={!!magnet} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-h-[90dvh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Sign-ups: {magnet?.title}</DialogTitle>
          <DialogDescription>Everyone who asked for this download (latest first).</DialogDescription>
        </DialogHeader>
        {isLoading ? (
          <FlipLoaderBlock label="Loading…" />
        ) : (data ?? []).length === 0 ? (
          <p className="py-6 text-center text-sm text-muted-foreground">No sign-ups yet.</p>
        ) : (
          <div className="divide-y divide-border text-sm">
            {data!.map((s) => (
              <div key={s.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <div className="min-w-0">
                  <p className="font-medium">
                    {s.lead_id ? <Link to={`/crm/leads/${s.lead_id}`} className="text-primary hover:underline">{s.name}</Link> : s.name}
                  </p>
                  <p className="text-muted-foreground">{[s.email, s.phone, s.company_name].filter(Boolean).join(' · ')}</p>
                </div>
                <div className="text-right text-xs text-muted-foreground">
                  <p>{formatDateTime(s.created_at)}</p>
                  <p>{s.downloaded_at ? `Downloaded${s.download_count > 1 ? ` ${s.download_count}×` : ''}` : 'Not downloaded yet'}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </DialogContent>
    </Dialog>
  )
}
