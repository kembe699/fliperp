import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CalendarDays, ChevronLeft, ChevronRight, Copy, ExternalLink, Plus, Video } from 'lucide-react'

import { fetchCrmMeetings, updateCrmMeetingStatus } from '@/api/crm'
import { deleteBookingPage, fetchBookingPages, saveBookingPage, type CrmBookingPage } from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'
import { localISODate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import { cn } from '@/lib/utils'
import type { CrmMeeting, CrmMeetingStatus } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { EmptyState } from '@/components/shared/EmptyState'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'
import { BookingPageFormDialog, WEEKDAYS } from '@/components/crm/marketing/BookingPageFormDialog'

const STATUS_VARIANT: Record<CrmMeetingStatus, 'info' | 'success' | 'neutral' | 'danger'> = {
  scheduled: 'info',
  completed: 'success',
  cancelled: 'neutral',
  no_show: 'danger',
}
const STATUS_DOT: Record<CrmMeetingStatus, string> = {
  scheduled: 'bg-primary',
  completed: 'bg-[hsl(142,72%,29%)]',
  cancelled: 'bg-muted-foreground/50',
  no_show: 'bg-destructive',
}

const timeLabel = (iso: string) => new Date(iso).toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })

/** CRM > Calendar: every CRM meeting by month, plus the public booking pages clients book through. */
export function CrmCalendarPage() {
  const { can } = usePermissions()

  return (
    <div>
      <PageHeader parent="CRM" title="Calendar" />
      <Tabs defaultValue="calendar">
        <TabsList>
          <TabsTrigger value="calendar">Meetings</TabsTrigger>
          {can('crm-booking-pages.view') && <TabsTrigger value="booking">Booking pages</TabsTrigger>}
        </TabsList>
        <TabsContent value="calendar">
          <MeetingsCalendar />
        </TabsContent>
        {can('crm-booking-pages.view') && (
          <TabsContent value="booking">
            <BookingPages />
          </TabsContent>
        )}
      </Tabs>
    </div>
  )
}

function MeetingsCalendar() {
  const [month, setMonth] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1))
  const [selected, setSelected] = useState<CrmMeeting | null>(null)

  // Whole weeks covering the month, Monday first.
  const gridStart = useMemo(() => {
    const d = new Date(month)
    d.setDate(1 - ((d.getDay() + 6) % 7))
    return d
  }, [month])
  const days = useMemo(() => Array.from({ length: 42 }, (_, i) => new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i)), [gridStart])

  const { data, isLoading } = useQuery({
    queryKey: ['crm-meetings', 'calendar', localISODate(days[0]), localISODate(days[41])],
    queryFn: () => fetchCrmMeetings({ from: localISODate(days[0]), to: localISODate(days[41]), per_page: 500 }),
    staleTime: 0,
  })

  const byDay = useMemo(() => {
    const map = new Map<string, CrmMeeting[]>()
    for (const meeting of data?.data ?? []) {
      const key = localISODate(new Date(meeting.scheduled_at))
      map.set(key, [...(map.get(key) ?? []), meeting])
    }
    return map
  }, [data])

  const today = localISODate(new Date())
  const monthMeetings = (data?.data ?? []).filter((m) => new Date(m.scheduled_at).getMonth() === month.getMonth())

  return (
    <div className="rounded-xl border border-border bg-card">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border p-3">
        <div className="flex items-center gap-1">
          <Button variant="outline" size="icon" className="h-8 w-8" aria-label="Previous month" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() - 1, 1))}>
            <ChevronLeft className="h-4 w-4" />
          </Button>
          <Button variant="outline" size="icon" className="h-8 w-8" aria-label="Next month" onClick={() => setMonth(new Date(month.getFullYear(), month.getMonth() + 1, 1))}>
            <ChevronRight className="h-4 w-4" />
          </Button>
          <h2 className="ml-2 text-base font-semibold">{month.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })}</h2>
        </div>
        <div className="flex items-center gap-3">
          <span className="text-sm text-muted-foreground">{monthMeetings.length} meeting{monthMeetings.length === 1 ? '' : 's'}</span>
          <Button variant="outline" size="sm" onClick={() => setMonth(new Date(new Date().getFullYear(), new Date().getMonth(), 1))}>
            Today
          </Button>
        </div>
      </div>

      {isLoading ? (
        <FlipLoaderBlock label="Loading meetings…" />
      ) : (
        <>
          {/* Month grid (tablet and up) */}
          <div className="hidden md:block">
            <div className="grid grid-cols-7 border-b border-border text-xs font-medium uppercase text-muted-foreground">
              {WEEKDAYS.map((d) => (
                <div key={d.value} className="px-2 py-1.5">
                  {d.short}
                </div>
              ))}
            </div>
            <div className="grid grid-cols-7">
              {days.map((day) => {
                const key = localISODate(day)
                const items = byDay.get(key) ?? []
                const inMonth = day.getMonth() === month.getMonth()
                return (
                  <div key={key} className={cn('min-h-28 border-b border-r border-border p-1.5 [&:nth-child(7n)]:border-r-0', !inMonth && 'bg-muted/40')}>
                    <div className={cn('mb-1 flex h-6 w-6 items-center justify-center rounded-full text-xs', key === today ? 'bg-primary font-semibold text-primary-foreground' : inMonth ? 'text-foreground' : 'text-muted-foreground')}>
                      {day.getDate()}
                    </div>
                    <div className="space-y-1">
                      {items.slice(0, 3).map((m) => (
                        <button key={m.id} type="button" title={`${timeLabel(m.scheduled_at)} · ${m.title}`} onClick={() => setSelected(m)} className="flex w-full items-center gap-1.5 rounded px-1 py-0.5 text-left text-xs hover:bg-muted">
                          <span className={cn('h-1.5 w-1.5 shrink-0 rounded-full', STATUS_DOT[m.status])} />
                          <span className="shrink-0 text-muted-foreground">{timeLabel(m.scheduled_at)}</span>
                          <span className={cn('truncate', m.status === 'cancelled' && 'line-through')}>{m.title}</span>
                        </button>
                      ))}
                      {items.length > 3 && <p className="px-1 text-xs text-muted-foreground">+{items.length - 3} more</p>}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>

          {/* Agenda list (phones) */}
          <div className="divide-y divide-border md:hidden">
            {monthMeetings.length === 0 && <p className="p-6 text-center text-sm text-muted-foreground">No meetings this month.</p>}
            {monthMeetings.map((m) => (
              <button key={m.id} type="button" onClick={() => setSelected(m)} className="flex w-full items-start gap-3 p-3 text-left hover:bg-muted/50">
                <div className="w-12 shrink-0 text-center">
                  <p className="text-xs uppercase text-muted-foreground">{new Date(m.scheduled_at).toLocaleDateString(undefined, { weekday: 'short' })}</p>
                  <p className="text-lg font-semibold">{new Date(m.scheduled_at).getDate()}</p>
                </div>
                <div className="min-w-0 flex-1">
                  <p className={cn('truncate font-medium', m.status === 'cancelled' && 'line-through')}>{m.title}</p>
                  <p className="text-sm text-muted-foreground">
                    {timeLabel(m.scheduled_at)} · {m.duration_minutes} min
                  </p>
                </div>
                <StatusBadge label={m.status.replace('_', ' ')} variant={STATUS_VARIANT[m.status]} />
              </button>
            ))}
          </div>
        </>
      )}
      <p className="border-t border-border px-3 py-2 text-xs text-muted-foreground">
        Schedule meetings from a lead or deal (Meetings tab). Meetings booked by clients through a booking page appear here automatically.
      </p>

      <MeetingDialog meeting={selected} onClose={() => setSelected(null)} />
    </div>
  )
}

function MeetingDialog({ meeting, onClose }: { meeting: CrmMeeting | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const mutation = useMutation({
    mutationFn: (status: CrmMeetingStatus) => updateCrmMeetingStatus(meeting!.id, status),
    onSuccess: () => {
      toast.success('Meeting updated')
      queryClient.invalidateQueries({ queryKey: ['crm-meetings'] })
      onClose()
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })
  if (!meeting) return null
  const contact = meeting.attendees?.find((a) => a.external_email)
  const start = new Date(meeting.scheduled_at)

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>{meeting.title}</DialogTitle>
        </DialogHeader>
        <div className="space-y-3 text-sm">
          <div className="flex items-center gap-2">
            <StatusBadge label={meeting.status.replace('_', ' ')} variant={STATUS_VARIANT[meeting.status]} />
            {meeting.booking_page_id && <span className="text-xs text-muted-foreground">Booked online</span>}
          </div>
          <p className="flex items-center gap-2">
            <CalendarDays className="h-4 w-4 text-muted-foreground" />
            {start.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })} · {timeLabel(meeting.scheduled_at)} ({meeting.duration_minutes} min)
          </p>
          {meeting.meeting_link && (
            <a href={meeting.meeting_link} target="_blank" rel="noreferrer" className="flex items-center gap-2 text-primary hover:underline">
              <Video className="h-4 w-4" />
              Join video meeting
            </a>
          )}
          {meeting.location && <p className="text-muted-foreground">Location: {meeting.location}</p>}
          {contact && (
            <p>
              With <span className="font-medium">{contact.external_name}</span> <span className="text-muted-foreground">&lt;{contact.external_email}&gt;</span>
            </p>
          )}
          {meeting.organizer && <p className="text-muted-foreground">Host: {meeting.organizer.name}</p>}
          {meeting.booking_notes && <p className="rounded-md bg-muted p-2">“{meeting.booking_notes}”</p>}
          <div className="flex flex-wrap gap-3">
            {meeting.lead_id && <Link className="text-primary hover:underline" to={`/crm/leads/${meeting.lead_id}`}>Open lead{meeting.lead ? `: ${meeting.lead.name}` : ''}</Link>}
            {meeting.deal_id && <Link className="text-primary hover:underline" to={`/crm/deals/${meeting.deal_id}`}>Open deal</Link>}
            {meeting.customer_id && <Link className="text-primary hover:underline" to={`/customers/${meeting.customer_id}/statement`}>Open customer{meeting.customer ? `: ${meeting.customer.name}` : ''}</Link>}
          </div>
        </div>
        {meeting.status === 'scheduled' && can('crm-meetings.update') && (
          <DialogFooter className="gap-2 sm:gap-0">
            <Button variant="outline" disabled={mutation.isPending} onClick={() => mutation.mutate('cancelled')}>Cancel meeting</Button>
            <Button variant="outline" disabled={mutation.isPending} onClick={() => mutation.mutate('no_show')}>No-show</Button>
            <Button disabled={mutation.isPending} onClick={() => mutation.mutate('completed')}>Mark done</Button>
          </DialogFooter>
        )}
      </DialogContent>
    </Dialog>
  )
}

function BookingPages() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<CrmBookingPage | null>(null)
  const { data: pages, isLoading } = useQuery({ queryKey: ['crm-booking-pages'], queryFn: fetchBookingPages , staleTime: 0 })

  const toggle = useMutation({
    mutationFn: (page: CrmBookingPage) => saveBookingPage({ ...page, is_active: !page.is_active }, page.id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['crm-booking-pages'] }),
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })
  const remove = useMutation({
    mutationFn: (id: number) => deleteBookingPage(id),
    onSuccess: () => {
      toast.success('Booking page deleted')
      queryClient.invalidateQueries({ queryKey: ['crm-booking-pages'] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const copy = (url: string) => {
    navigator.clipboard?.writeText(url).then(() => toast.success('Link copied'), () => toast.error('Could not copy — select the link instead'))
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-sm text-muted-foreground">Share a booking link — clients pick a free time, and it lands in this calendar as a meeting with a new lead.</p>
        {can('crm-booking-pages.create') && (
          <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
            <Plus className="h-4 w-4" />
            New booking page
          </Button>
        )}
      </div>

      {isLoading ? (
        <FlipLoaderBlock label="Loading…" />
      ) : (pages ?? []).length === 0 ? (
        <EmptyState title="No booking pages yet" subtext="Create one and share its link on your website, email signature or social media." />
      ) : (
        <div className="grid gap-3 lg:grid-cols-2">
          {pages!.map((page) => (
            <div key={page.id} className="rounded-xl border border-border bg-card p-4">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="font-semibold">{page.title}</p>
                  <p className="text-sm text-muted-foreground">
                    {page.duration_minutes} min · {page.days.map((d) => WEEKDAYS.find((w) => w.value === d)?.short).join(', ')} · {page.start_time}–{page.end_time} ({page.timezone})
                  </p>
                  <p className="text-sm text-muted-foreground">Host: {page.host?.name ?? '—'}</p>
                </div>
                <StatusBadge label={page.is_active ? 'Live' : 'Off'} variant={page.is_active ? 'success' : 'neutral'} />
              </div>
              <div className="mt-3 flex items-center gap-2 rounded-md border border-border bg-muted/40 px-2 py-1.5">
                <span className="min-w-0 flex-1 truncate text-sm">{page.public_url}</span>
                <Button variant="ghost" size="icon" className="h-7 w-7" aria-label="Copy link" onClick={() => copy(page.public_url)}>
                  <Copy className="h-3.5 w-3.5" />
                </Button>
                <a href={page.public_url} target="_blank" rel="noreferrer" aria-label="Open booking page" className="inline-flex h-7 w-7 items-center justify-center rounded-md hover:bg-muted">
                  <ExternalLink className="h-3.5 w-3.5" />
                </a>
              </div>
              <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">
                  {page.bookings_count} booking{page.bookings_count === 1 ? '' : 's'} · {page.upcoming_count} upcoming
                </p>
                <div className="flex gap-1">
                  {can('crm-booking-pages.update') && (
                    <>
                      <Button variant="ghost" size="sm" onClick={() => toggle.mutate(page)}>{page.is_active ? 'Pause' : 'Turn on'}</Button>
                      <Button variant="ghost" size="sm" onClick={() => { setEditing(page); setFormOpen(true) }}>Edit</Button>
                    </>
                  )}
                  {can('crm-booking-pages.delete') && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-destructive"
                      onClick={() => window.confirm(`Delete "${page.title}"? Its link stops working; booked meetings stay.`) && remove.mutate(page.id)}
                    >
                      Delete
                    </Button>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <BookingPageFormDialog open={formOpen} onOpenChange={setFormOpen} page={editing} />
    </div>
  )
}
