import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Calendar, Download, MapPin, Plus, Users, Video } from 'lucide-react'

import { updateCrmMeetingStatus } from '@/api/crm'
import { downloadIcs } from '@/lib/pdf-download'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmMeeting, CrmMeetingStatus } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { ScheduleMeetingDialog } from '@/components/crm/drawer/ScheduleMeetingDialog'

const STATUS_VARIANT: Record<CrmMeetingStatus, 'info' | 'success' | 'neutral' | 'danger'> = {
  scheduled: 'info',
  completed: 'success',
  cancelled: 'neutral',
  no_show: 'danger',
}

const STATUS_TRANSITIONS: Record<CrmMeetingStatus, CrmMeetingStatus[]> = {
  scheduled: ['completed', 'cancelled', 'no_show'],
  completed: [],
  cancelled: [],
  no_show: [],
}

function MeetingCard({ meeting, queryKeyToInvalidate }: { meeting: CrmMeeting; queryKeyToInvalidate: unknown[] }) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const statusMutation = useMutation({
    mutationFn: (status: CrmMeetingStatus) => updateCrmMeetingStatus(meeting.id, status),
    onSuccess: () => {
      toast.success('Meeting status updated')
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const scheduledDate = new Date(meeting.scheduled_at)
  const transitions = STATUS_TRANSITIONS[meeting.status]

  return (
    <Card>
      <CardContent className="p-4">
        <div className="flex items-start justify-between gap-3">
          <div>
            <p className="font-medium text-foreground">{meeting.title}</p>
            <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
              <Calendar className="h-3.5 w-3.5" />
              {scheduledDate.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })} · {meeting.duration_minutes} min
            </p>
            {meeting.location && (
              <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                <MapPin className="h-3.5 w-3.5" />
                {meeting.location}
              </p>
            )}
            {meeting.meeting_link && (
              <p className="mt-0.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                <Video className="h-3.5 w-3.5" />
                <a href={meeting.meeting_link} target="_blank" rel="noreferrer" className="text-primary underline-offset-2 hover:underline">
                  {meeting.meeting_link}
                </a>
              </p>
            )}
            {meeting.attendees.length > 0 && (
              <p className="mt-1.5 flex items-center gap-1.5 text-xs text-muted-foreground">
                <Users className="h-3.5 w-3.5" />
                {meeting.attendees.map((a) => a.user?.name ?? a.external_name ?? a.external_email).filter(Boolean).join(', ')}
              </p>
            )}
          </div>
          <StatusBadge label={meeting.status.replace('_', ' ')} variant={STATUS_VARIANT[meeting.status]} />
        </div>

        <div className="mt-3 flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => downloadIcs(`/crm/meetings/${meeting.id}/ics`, `meeting-${meeting.id}.ics`)}>
            <Download className="h-3.5 w-3.5" />
            Download .ics
          </Button>
          {transitions.length > 0 && can('crm-meetings.update') && (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" disabled={statusMutation.isPending}>
                  Update Status
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="start">
                {transitions.map((status) => (
                  <DropdownMenuItem key={status} onClick={() => statusMutation.mutate(status)}>
                    Mark {status.replace('_', ' ')}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

interface CrmDrawerMeetingsTabProps {
  leadId?: number | null
  dealId?: number | null
  customerId?: number | null
  defaultContactName?: string | null
  defaultContactEmail?: string | null
  defaultTitle?: string
  defaultDescription?: string
  upcoming: CrmMeeting[]
  past: CrmMeeting[]
  queryKeyToInvalidate: unknown[]
}

export function CrmDrawerMeetingsTab({
  leadId,
  dealId,
  customerId,
  defaultContactName,
  defaultContactEmail,
  defaultTitle,
  defaultDescription,
  upcoming,
  past,
  queryKeyToInvalidate,
}: CrmDrawerMeetingsTabProps) {
  const { can } = usePermissions()
  const [scheduleOpen, setScheduleOpen] = useState(false)

  return (
    <div className="space-y-6">
      {can('crm-meetings.create') && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setScheduleOpen(true)}>
            <Plus className="h-4 w-4" />
            Schedule Meeting
          </Button>
        </div>
      )}

      <div>
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Upcoming</p>
        {upcoming.length === 0 ? (
          <p className="text-sm text-muted-foreground">No upcoming meetings scheduled.</p>
        ) : (
          <div className="space-y-2">
            {upcoming.map((meeting) => (
              <MeetingCard key={meeting.id} meeting={meeting} queryKeyToInvalidate={queryKeyToInvalidate} />
            ))}
          </div>
        )}
      </div>

      <div>
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Past</p>
        {past.length === 0 ? (
          <p className="text-sm text-muted-foreground">No past meetings.</p>
        ) : (
          <div className="space-y-2">
            {past.map((meeting) => (
              <MeetingCard key={meeting.id} meeting={meeting} queryKeyToInvalidate={queryKeyToInvalidate} />
            ))}
          </div>
        )}
      </div>

      <ScheduleMeetingDialog
        open={scheduleOpen}
        onOpenChange={setScheduleOpen}
        leadId={leadId}
        dealId={dealId}
        customerId={customerId}
        defaultContactName={defaultContactName}
        defaultContactEmail={defaultContactEmail}
        defaultTitle={defaultTitle}
        defaultDescription={defaultDescription}
        queryKeyToInvalidate={queryKeyToInvalidate}
      />
    </div>
  )
}
