import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { saveBookingPage, type CrmBookingPage, type CrmBookingPageInput } from '@/api/crm-marketing'
import { fetchUsers } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { cn } from '@/lib/utils'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'

export const WEEKDAYS = [
  { value: 1, short: 'Mon' },
  { value: 2, short: 'Tue' },
  { value: 3, short: 'Wed' },
  { value: 4, short: 'Thu' },
  { value: 5, short: 'Fri' },
  { value: 6, short: 'Sat' },
  { value: 7, short: 'Sun' },
]

const TIMEZONES: string[] = (() => {
  try {
    return (Intl as unknown as { supportedValuesOf?: (key: string) => string[] }).supportedValuesOf?.('timeZone') ?? []
  } catch {
    return []
  }
})()

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  page: CrmBookingPage | null
}

export function BookingPageFormDialog({ open, onOpenChange, page }: Props) {
  const queryClient = useQueryClient()
  const me = useAuthStore((state) => state.user)
  const company = useAuthStore((state) => state.company)
  const { data: users } = useQuery({ queryKey: ['users', 'all'], queryFn: () => fetchUsers({ per_page: 200 }), enabled: open })

  const defaults = useMemo<CrmBookingPageInput>(
    () => ({
      title: '',
      slug: '',
      description: '',
      duration_minutes: 30,
      buffer_minutes: 0,
      days: [1, 2, 3, 4, 5],
      start_time: '09:00',
      end_time: '17:00',
      timezone: company?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
      min_notice_hours: 2,
      max_days_ahead: 30,
      host_user_id: me?.id ?? 0,
      location: '',
      meeting_link: '',
      is_active: true,
    }),
    [company?.timezone, me?.id],
  )
  const [form, setForm] = useState<CrmBookingPageInput>(defaults)

  useEffect(() => {
    if (!open) return
    setForm(page ? { ...defaults, ...page, description: page.description ?? '', location: page.location ?? '', meeting_link: page.meeting_link ?? '' } : defaults)
  }, [open, page, defaults])

  const set = <K extends keyof CrmBookingPageInput>(key: K, value: CrmBookingPageInput[K]) => setForm((f) => ({ ...f, [key]: value }))

  const mutation = useMutation({
    mutationFn: () =>
      saveBookingPage(
        {
          ...form,
          slug: form.slug?.trim() || null,
          description: form.description?.trim() || null,
          location: form.location?.trim() || null,
          meeting_link: form.meeting_link?.trim() || null,
        },
        page?.id,
      ),
    onSuccess: () => {
      toast.success(page ? 'Booking page saved' : 'Booking page created')
      queryClient.invalidateQueries({ queryKey: ['crm-booking-pages'] })
      onOpenChange(false)
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const toggleDay = (day: number) =>
    set('days', form.days.includes(day) ? form.days.filter((d) => d !== day) : [...form.days, day].sort())

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90dvh] max-w-xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{page ? 'Edit booking page' : 'New booking page'}</DialogTitle>
        </DialogHeader>
        <form
          id="booking-page-form"
          className="grid gap-4"
          onSubmit={(e) => {
            e.preventDefault()
            if (!mutation.isPending) mutation.mutate()
          }}
        >
          <div className="grid gap-1.5">
            <Label htmlFor="bp-title">Title</Label>
            <Input id="bp-title" required value={form.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g. 30-minute consultation" />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="bp-desc">Description</Label>
            <Textarea id="bp-desc" rows={3} value={form.description ?? ''} onChange={(e) => set('description', e.target.value)} placeholder="What the meeting is about (shown to clients)" />
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
              <Label>Host</Label>
              <Select value={String(form.host_user_id || '')} onValueChange={(v) => set('host_user_id', Number(v))}>
                <SelectTrigger>
                  <SelectValue placeholder="Who takes the meetings" />
                </SelectTrigger>
                <SelectContent>
                  {(users?.data ?? []).filter((u) => u.is_active !== false).map((u) => (
                    <SelectItem key={u.id} value={String(u.id)}>
                      {u.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="bp-duration">Meeting length (minutes)</Label>
              <Input id="bp-duration" type="number" min={10} max={480} required value={form.duration_minutes} onChange={(e) => set('duration_minutes', Number(e.target.value))} />
            </div>
          </div>
          <div className="grid gap-1.5">
            <Label>Days</Label>
            <div className="flex flex-wrap gap-1.5">
              {WEEKDAYS.map((d) => (
                <button
                  key={d.value}
                  type="button"
                  onClick={() => toggleDay(d.value)}
                  className={cn(
                    'h-8 rounded-md border px-3 text-sm transition-colors',
                    form.days.includes(d.value) ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-muted',
                  )}
                >
                  {d.short}
                </button>
              ))}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div className="grid gap-1.5">
              <Label htmlFor="bp-start">From</Label>
              <Input id="bp-start" type="time" required value={form.start_time} onChange={(e) => set('start_time', e.target.value)} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="bp-end">To</Label>
              <Input id="bp-end" type="time" required value={form.end_time} onChange={(e) => set('end_time', e.target.value)} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="bp-notice">Notice (hours)</Label>
              <Input id="bp-notice" type="number" min={0} value={form.min_notice_hours} onChange={(e) => set('min_notice_hours', Number(e.target.value))} />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="bp-ahead">Days ahead</Label>
              <Input id="bp-ahead" type="number" min={1} max={365} value={form.max_days_ahead} onChange={(e) => set('max_days_ahead', Number(e.target.value))} />
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
              <Label htmlFor="bp-tz">Timezone</Label>
              <Input id="bp-tz" list="bp-timezones" required value={form.timezone} onChange={(e) => set('timezone', e.target.value)} />
              <datalist id="bp-timezones">
                {TIMEZONES.map((tz) => (
                  <option key={tz} value={tz} />
                ))}
              </datalist>
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="bp-buffer">Gap between meetings (minutes)</Label>
              <Input id="bp-buffer" type="number" min={0} max={240} value={form.buffer_minutes} onChange={(e) => set('buffer_minutes', Number(e.target.value))} />
            </div>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
              <Label htmlFor="bp-location">Location</Label>
              <Input id="bp-location" value={form.location ?? ''} onChange={(e) => set('location', e.target.value)} placeholder="Office address (optional)" />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="bp-link">Video meeting link</Label>
              <Input id="bp-link" type="url" value={form.meeting_link ?? ''} onChange={(e) => set('meeting_link', e.target.value)} placeholder="https://meet.google.com/… (optional)" />
            </div>
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="bp-slug">Link name</Label>
            <Input id="bp-slug" value={form.slug ?? ''} onChange={(e) => set('slug', e.target.value)} placeholder="Made from the title if left empty" />
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" className="h-4 w-4 accent-primary" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} />
            Taking bookings
          </label>
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="booking-page-form" disabled={mutation.isPending || form.days.length === 0 || !form.host_user_id}>
            {mutation.isPending ? 'Saving…' : 'Save'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
