import { useEffect, useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { CalendarCheck, CheckCircle2, Clock, Download, Globe, MapPin, Video } from 'lucide-react'

import {
  bookPublicMeeting,
  fetchPublicBookingDays,
  fetchPublicBookingPage,
  fetchPublicBookingSlots,
  fetchPublicLeadMagnet,
  submitPublicLeadMagnet,
  unsubscribePublic,
} from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'
import { cn } from '@/lib/utils'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

function PublicShell({ company, children }: { company?: { name: string | null; logo_url: string | null }; children: ReactNode }) {
  return (
    <div className="min-h-dvh bg-muted px-4 py-8 sm:py-12">
      <div className="mx-auto w-full max-w-3xl">
        {company?.name && (
          <div className="mb-6 flex items-center justify-center gap-3">
            {company.logo_url && <img src={company.logo_url} alt="" className="h-10 w-10 rounded-lg object-contain" />}
            <span className="text-lg font-semibold text-foreground">{company.name}</span>
          </div>
        )}
        {children}
        <p className="mt-6 text-center text-xs text-muted-foreground">Powered by FlipERP</p>
      </div>
    </div>
  )
}

function NotAvailable({ message }: { message: string }) {
  return (
    <PublicShell>
      <div className="rounded-2xl border border-border bg-card p-10 text-center shadow-sm">
        <p className="text-lg font-semibold">Not available</p>
        <p className="mt-2 text-sm text-muted-foreground">{message}</p>
      </div>
    </PublicShell>
  )
}

const localTime = (iso: string) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
const viewerTz = Intl.DateTimeFormat().resolvedOptions().timeZone

// --- /book/:slug ---

export function PublicBookingPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const [date, setDate] = useState<string | null>(null)
  const [slot, setSlot] = useState<string | null>(null)
  const [form, setForm] = useState({ name: '', email: '', phone: '', company_name: '', notes: '' })
  const [error, setError] = useState<string | null>(null)

  const { data: page, isLoading, isError } = useQuery({ queryKey: ['public-booking', slug], queryFn: () => fetchPublicBookingPage(slug), retry: false })
  const { data: days, refetch: refetchDays } = useQuery({ queryKey: ['public-booking-days', slug], queryFn: () => fetchPublicBookingDays(slug), enabled: !!page })
  const { data: slots, isFetching: loadingSlots, refetch: refetchSlots } = useQuery({
    queryKey: ['public-booking-slots', slug, date],
    queryFn: () => fetchPublicBookingSlots(slug, date!),
    enabled: !!page && !!date,
  })

  useEffect(() => {
    if (!date && days?.length) setDate(days[0])
  }, [days, date])

  const booking = useMutation({
    mutationFn: () => bookPublicMeeting(slug, { ...form, start: slot! }),
    onError: (err) => {
      setError(getApiErrorInfo(err).message)
      setSlot(null)
      refetchSlots()
      refetchDays()
    },
  })

  if (isLoading) return <FlipLoaderBlock label="Loading…" />
  if (isError || !page) return <NotAvailable message="This booking link is not active. Please contact the business directly." />

  if (booking.isSuccess) {
    const start = new Date(booking.data.scheduled_at)
    return (
      <PublicShell company={page.company}>
        <div className="rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
          <CalendarCheck className="mx-auto h-12 w-12 text-[hsl(142,72%,29%)]" />
          <h1 className="mt-3 text-xl font-semibold">You're booked!</h1>
          <p className="mt-2 text-foreground">
            {page.title} on <span className="font-medium">{start.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}</span> at{' '}
            <span className="font-medium">{localTime(booking.data.scheduled_at)}</span> ({booking.data.duration_minutes} min).
          </p>
          {booking.data.meeting_link && (
            <a href={booking.data.meeting_link} className="mt-3 inline-flex items-center gap-2 text-primary hover:underline" target="_blank" rel="noreferrer">
              <Video className="h-4 w-4" /> Video meeting link
            </a>
          )}
          {booking.data.location && <p className="mt-2 text-sm text-muted-foreground">Location: {booking.data.location}</p>}
          <p className="mt-4 text-sm text-muted-foreground">A confirmation with a calendar invite is on its way to {form.email}.</p>
        </div>
      </PublicShell>
    )
  }

  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  return (
    <PublicShell company={page.company}>
      <div className="grid grid-cols-[minmax(0,1fr)] overflow-hidden rounded-2xl border border-border bg-card shadow-sm md:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
        <div className="border-b border-border p-6 md:border-b-0 md:border-r">
          {page.host_name && <p className="text-sm text-muted-foreground">{page.host_name}</p>}
          <h1 className="mt-1 text-xl font-semibold">{page.title}</h1>
          <div className="mt-4 space-y-2 text-sm text-muted-foreground">
            <p className="flex items-center gap-2"><Clock className="h-4 w-4" /> {page.duration_minutes} minutes</p>
            {page.has_meeting_link && <p className="flex items-center gap-2"><Video className="h-4 w-4" /> Video call — link sent on booking</p>}
            {page.location && <p className="flex items-center gap-2"><MapPin className="h-4 w-4" /> {page.location}</p>}
            <p className="flex items-start gap-2"><Globe className="mt-0.5 h-4 w-4 shrink-0" /> <span className="min-w-0 break-words">Times shown in your timezone ({viewerTz})</span></p>
          </div>
          {page.description && <p className="mt-4 whitespace-pre-wrap text-sm">{page.description}</p>}
        </div>

        <div className="p-6">
          {!slot ? (
            <>
              <h2 className="text-sm font-semibold">Pick a day</h2>
              {days && days.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">No free times right now. Please check back later.</p>
              ) : (
                <div className="mt-3 flex gap-2 overflow-x-auto pb-2">
                  {(days ?? []).map((d) => {
                    const day = new Date(`${d}T12:00:00`)
                    return (
                      <button
                        key={d}
                        type="button"
                        onClick={() => setDate(d)}
                        className={cn(
                          'flex w-16 shrink-0 flex-col items-center rounded-lg border py-2 text-sm transition-colors',
                          d === date ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-muted',
                        )}
                      >
                        <span className="text-xs uppercase">{day.toLocaleDateString(undefined, { weekday: 'short' })}</span>
                        <span className="text-lg font-semibold">{day.getDate()}</span>
                        <span className="text-xs">{day.toLocaleDateString(undefined, { month: 'short' })}</span>
                      </button>
                    )
                  })}
                </div>
              )}
              {date && (
                <>
                  <h2 className="mt-4 text-sm font-semibold">Pick a time</h2>
                  {error && <p className="mt-2 rounded-md bg-danger-bg px-3 py-2 text-sm text-danger">{error}</p>}
                  {loadingSlots && !slots ? (
                    <p className="mt-3 text-sm text-muted-foreground">Loading times…</p>
                  ) : (slots ?? []).length === 0 ? (
                    <p className="mt-3 text-sm text-muted-foreground">No free times on this day.</p>
                  ) : (
                    <div className="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                      {slots!.map((s) => (
                        <button key={s} type="button" onClick={() => { setSlot(s); setError(null) }} className="rounded-lg border border-primary/40 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary hover:text-primary-foreground">
                          {localTime(s)}
                        </button>
                      ))}
                    </div>
                  )}
                </>
              )}
            </>
          ) : (
            <form
              className="grid gap-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (!booking.isPending) booking.mutate()
              }}
            >
              <div className="rounded-lg bg-muted/60 p-3 text-sm">
                <span className="font-medium">
                  {new Date(slot).toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })} at {localTime(slot)}
                </span>
                <button type="button" className="ml-2 text-primary hover:underline" onClick={() => setSlot(null)}>Change</button>
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="b-name">Your name</Label>
                <Input id="b-name" required autoComplete="name" value={form.name} onChange={(e) => set('name', e.target.value)} />
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="b-email">Email</Label>
                <Input id="b-email" type="email" required autoComplete="email" value={form.email} onChange={(e) => set('email', e.target.value)} />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                  <Label htmlFor="b-phone">Phone (optional)</Label>
                  <Input id="b-phone" type="tel" autoComplete="tel" value={form.phone} onChange={(e) => set('phone', e.target.value)} />
                </div>
                <div className="grid gap-1.5">
                  <Label htmlFor="b-company">Company (optional)</Label>
                  <Input id="b-company" autoComplete="organization" value={form.company_name} onChange={(e) => set('company_name', e.target.value)} />
                </div>
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="b-notes">Anything we should know? (optional)</Label>
                <Textarea id="b-notes" rows={3} value={form.notes} onChange={(e) => set('notes', e.target.value)} />
              </div>
              <Button type="submit" disabled={booking.isPending} className="mt-1">
                {booking.isPending ? 'Booking…' : 'Confirm booking'}
              </Button>
            </form>
          )}
        </div>
      </div>
    </PublicShell>
  )
}

// --- /lm/:slug ---

export function PublicLeadMagnetPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const [form, setForm] = useState({ name: '', email: '', phone: '', company_name: '' })
  const { data: magnet, isLoading, isError } = useQuery({ queryKey: ['public-lead-magnet', slug], queryFn: () => fetchPublicLeadMagnet(slug), retry: false, staleTime: Infinity })
  const submit = useMutation({ mutationFn: () => submitPublicLeadMagnet(slug, form) })

  if (isLoading) return <FlipLoaderBlock label="Loading…" />
  if (isError || !magnet) return <NotAvailable message="This page is no longer available." />

  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  return (
    <PublicShell company={magnet.company}>
      <div className="mx-auto max-w-xl rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8">
        {submit.isSuccess ? (
          <div className="text-center">
            <CheckCircle2 className="mx-auto h-12 w-12 text-[hsl(142,72%,29%)]" />
            <h1 className="mt-3 text-xl font-semibold">{magnet.title} is ready</h1>
            <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{submit.data.thank_you_message}</p>
            <a href={submit.data.download_url} className="mt-5 inline-flex h-10 items-center gap-2 rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground hover:bg-primary/90">
              <Download className="h-4 w-4" /> Download now
            </a>
          </div>
        ) : (
          <>
            <h1 className="text-2xl font-semibold leading-tight">{magnet.headline || magnet.title}</h1>
            {magnet.headline && <p className="mt-1 text-sm font-medium text-primary">{magnet.title}</p>}
            {magnet.description && <p className="mt-4 whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground">{magnet.description}</p>}
            <form
              className="mt-6 grid gap-3"
              onSubmit={(e) => {
                e.preventDefault()
                if (!submit.isPending) submit.mutate()
              }}
            >
              <div className="grid gap-1.5">
                <Label htmlFor="lm-name">Your name</Label>
                <Input id="lm-name" required autoComplete="name" value={form.name} onChange={(e) => set('name', e.target.value)} />
              </div>
              <div className="grid gap-1.5">
                <Label htmlFor="lm-email">Email</Label>
                <Input id="lm-email" type="email" required autoComplete="email" value={form.email} onChange={(e) => set('email', e.target.value)} />
              </div>
              {magnet.ask_phone && (
                <div className="grid gap-1.5">
                  <Label htmlFor="lm-phone">Phone</Label>
                  <Input id="lm-phone" type="tel" required autoComplete="tel" value={form.phone} onChange={(e) => set('phone', e.target.value)} />
                </div>
              )}
              {magnet.ask_company && (
                <div className="grid gap-1.5">
                  <Label htmlFor="lm-company">Company</Label>
                  <Input id="lm-company" autoComplete="organization" value={form.company_name} onChange={(e) => set('company_name', e.target.value)} />
                </div>
              )}
              {submit.isError && <p className="rounded-md bg-danger-bg px-3 py-2 text-sm text-danger">{getApiErrorInfo(submit.error).message}</p>}
              <Button type="submit" disabled={submit.isPending} className="mt-1 h-11 text-base">
                {submit.isPending ? 'Sending…' : magnet.button_label}
              </Button>
              <p className="text-center text-xs text-muted-foreground">We'll email you the link too. You can unsubscribe from our emails at any time.</p>
            </form>
          </>
        )}
      </div>
    </PublicShell>
  )
}

// --- /unsubscribe/:token ---

export function UnsubscribePage() {
  const { token = '' } = useParams<{ token: string }>()
  const mutation = useMutation({ mutationFn: () => unsubscribePublic(token) })
  const { mutate } = mutation

  useEffect(() => {
    if (token) mutate()
  }, [token, mutate])

  return (
    <PublicShell>
      <div className="mx-auto max-w-md rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
        {mutation.isPending || mutation.isIdle ? (
          <p className="text-sm text-muted-foreground">Unsubscribing…</p>
        ) : mutation.isError ? (
          <>
            <p className="text-lg font-semibold">Link not valid</p>
            <p className="mt-2 text-sm text-muted-foreground">This unsubscribe link is not valid or has expired.</p>
          </>
        ) : (
          <>
            <CheckCircle2 className="mx-auto h-10 w-10 text-[hsl(142,72%,29%)]" />
            <p className="mt-3 text-lg font-semibold">You're unsubscribed</p>
            <p className="mt-2 text-sm text-muted-foreground">
              {mutation.data.email} will no longer receive marketing emails from {mutation.data.company}.
            </p>
          </>
        )}
      </div>
    </PublicShell>
  )
}
