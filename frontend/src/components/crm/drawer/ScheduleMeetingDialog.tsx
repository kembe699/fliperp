import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, X } from 'lucide-react'

import { createCrmMeeting, type CrmMeetingAttendeeInput } from '@/api/crm'
import { fetchUsers } from '@/api/settings'
import { useAuthStore } from '@/lib/auth-store'
import { getApiErrorInfo } from '@/lib/api-errors'
import { toISODate } from '@/lib/format'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Textarea } from '@/components/ui/textarea'

interface ScheduleMeetingDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  leadId?: number | null
  dealId?: number | null
  customerId?: number | null
  defaultContactName?: string | null
  defaultContactEmail?: string | null
  queryKeyToInvalidate: unknown[]
}

export function ScheduleMeetingDialog({
  open,
  onOpenChange,
  leadId,
  dealId,
  customerId,
  defaultContactName,
  defaultContactEmail,
  queryKeyToInvalidate,
}: ScheduleMeetingDialogProps) {
  const queryClient = useQueryClient()
  const currentUser = useAuthStore((state) => state.user)

  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [scheduledAt, setScheduledAt] = useState('')
  const [duration, setDuration] = useState('30')
  const [location, setLocation] = useState('')
  const [meetingLink, setMeetingLink] = useState('')
  const [internalAttendeeIds, setInternalAttendeeIds] = useState<number[]>([])
  const [contactName, setContactName] = useState('')
  const [contactEmail, setContactEmail] = useState('')
  const [pickerOpen, setPickerOpen] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const { data: users } = useQuery({ queryKey: ['settings-users-all'], queryFn: () => fetchUsers({ per_page: 100 }), enabled: open })

  useEffect(() => {
    if (!open) return
    setTitle('')
    setDescription('')
    setScheduledAt('')
    setDuration('30')
    setLocation('')
    setMeetingLink('')
    setInternalAttendeeIds(currentUser ? [currentUser.id] : [])
    setContactName(defaultContactName ?? '')
    setContactEmail(defaultContactEmail ?? '')
    setError(null)
  }, [open, currentUser, defaultContactName, defaultContactEmail])

  const mutation = useMutation({
    mutationFn: () => {
      const attendees: CrmMeetingAttendeeInput[] = internalAttendeeIds.map((userId) => ({ user_id: userId }))
      if (contactEmail.trim()) {
        attendees.push({ external_name: contactName.trim() || undefined, external_email: contactEmail.trim() })
      }

      return createCrmMeeting({
        lead_id: leadId ?? null,
        deal_id: dealId ?? null,
        customer_id: customerId ?? null,
        title,
        description: description || null,
        scheduled_at: scheduledAt,
        duration_minutes: Number(duration) || 30,
        location: location || null,
        meeting_link: meetingLink || null,
        organizer_id: currentUser?.id,
        attendees,
      })
    },
    onSuccess: () => {
      toast.success('Meeting scheduled')
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
      onOpenChange(false)
    },
    onError: (err) => setError(getApiErrorInfo(err).message),
  })

  const toggleAttendee = (userId: number) => {
    setInternalAttendeeIds((prev) => (prev.includes(userId) ? prev.filter((id) => id !== userId) : [...prev, userId]))
  }

  const canSubmit = title.trim() && scheduledAt

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Schedule Meeting</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="meeting-title">Title</Label>
            <Input id="meeting-title" value={title} onChange={(e) => setTitle(e.target.value)} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="meeting-description">Description</Label>
            <Textarea id="meeting-description" rows={2} value={description} onChange={(e) => setDescription(e.target.value)} />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="meeting-datetime">Date &amp; Time</Label>
              <Input
                id="meeting-datetime"
                type="datetime-local"
                min={toISODate(new Date())}
                value={scheduledAt}
                onChange={(e) => setScheduledAt(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="meeting-duration">Duration (minutes)</Label>
              <Input id="meeting-duration" type="number" min="5" step="5" value={duration} onChange={(e) => setDuration(e.target.value)} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="meeting-location">Location</Label>
              <Input id="meeting-location" value={location} onChange={(e) => setLocation(e.target.value)} placeholder="Optional" />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="meeting-link">Meeting Link</Label>
              <Input id="meeting-link" value={meetingLink} onChange={(e) => setMeetingLink(e.target.value)} placeholder="Optional" />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label>Internal Attendees</Label>
            <div className="flex flex-wrap items-center gap-1.5">
              {internalAttendeeIds.map((userId) => {
                const user = users?.data.find((u) => u.id === userId)
                return (
                  <Badge key={userId} variant="outline" className="gap-1 pr-1.5">
                    {user?.name ?? userId}
                    <button type="button" onClick={() => toggleAttendee(userId)} className="rounded-full p-0.5 hover:bg-muted">
                      <X className="h-3 w-3" />
                    </button>
                  </Badge>
                )
              })}
              <Popover open={pickerOpen} onOpenChange={setPickerOpen}>
                <PopoverTrigger asChild>
                  <Button type="button" variant="outline" size="sm" className="h-7 gap-1 rounded-full px-2.5 text-xs">
                    <Plus className="h-3 w-3" />
                    Add
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-56 p-0" align="start">
                  <Command>
                    <CommandInput placeholder="Search staff…" />
                    <CommandList>
                      <CommandEmpty>No matching staff.</CommandEmpty>
                      <CommandGroup>
                        {users?.data
                          .filter((u) => !internalAttendeeIds.includes(u.id))
                          .map((u) => (
                            <CommandItem key={u.id} value={u.name} onSelect={() => { toggleAttendee(u.id); setPickerOpen(false) }}>
                              {u.name}
                            </CommandItem>
                          ))}
                      </CommandGroup>
                    </CommandList>
                  </Command>
                </PopoverContent>
              </Popover>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="contact-name">Contact Name</Label>
              <Input id="contact-name" value={contactName} onChange={(e) => setContactName(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="contact-email">Contact Email</Label>
              <Input id="contact-email" type="email" value={contactEmail} onChange={(e) => setContactEmail(e.target.value)} />
            </div>
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Schedule Meeting
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
