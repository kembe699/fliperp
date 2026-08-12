import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import { Headset, Lock, MessageSquare } from 'lucide-react'

import { fetchPlatformTicket, fetchPlatformTickets, replyPlatformTicket, updatePlatformTicket } from '@/api/platform'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatDate } from '@/lib/format'
import { cn } from '@/lib/utils'
import type { PlatformTicket, PlatformTicketPriority, PlatformTicketStatus } from '@/types/platform'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const STATUS_VARIANT: Record<PlatformTicketStatus, 'info' | 'warning' | 'success' | 'neutral'> = {
  open: 'info',
  in_progress: 'warning',
  resolved: 'success',
  closed: 'neutral',
}

const PRIORITY_VARIANT: Record<PlatformTicketPriority, 'neutral' | 'info' | 'warning' | 'danger'> = {
  low: 'neutral',
  medium: 'info',
  high: 'warning',
  urgent: 'danger',
}

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function PlatformTicketsPage() {
  const queryClient = useQueryClient()
  const [searchParams, setSearchParams] = useSearchParams()

  const [status, setStatus] = useState<string>('all')
  const [priority, setPriority] = useState<string>('all')
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [replyBody, setReplyBody] = useState('')
  const [isInternalNote, setIsInternalNote] = useState(false)

  useEffect(() => {
    const idParam = searchParams.get('id')
    if (idParam) {
      setSelectedId(Number(idParam))
      searchParams.delete('id')
      setSearchParams(searchParams, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const { data, isLoading } = useQuery({
    queryKey: ['platform-tickets', status, priority],
    queryFn: () =>
      fetchPlatformTickets({
        per_page: 50,
        status: status === 'all' ? undefined : (status as PlatformTicketStatus),
        priority: priority === 'all' ? undefined : (priority as PlatformTicketPriority),
      }),
  })
  const tickets = data?.data ?? []

  const { data: selectedTicket } = useQuery({
    queryKey: ['platform-ticket', selectedId],
    queryFn: () => fetchPlatformTicket(selectedId!),
    enabled: !!selectedId,
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['platform-tickets'] })
    queryClient.invalidateQueries({ queryKey: ['platform-ticket', selectedId] })
  }

  const updateMutation = useMutation({
    mutationFn: (patch: Parameters<typeof updatePlatformTicket>[1]) => updatePlatformTicket(selectedId!, patch),
    onSuccess: invalidate,
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const replyMutation = useMutation({
    mutationFn: () => replyPlatformTicket(selectedId!, replyBody, isInternalNote),
    onSuccess: () => {
      setReplyBody('')
      setIsInternalNote(false)
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <div>
      <PageHeader title="Support Tickets" />

      <FilterBar>
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="open">Open</SelectItem>
            <SelectItem value="in_progress">In Progress</SelectItem>
            <SelectItem value="resolved">Resolved</SelectItem>
            <SelectItem value="closed">Closed</SelectItem>
          </SelectContent>
        </Select>
        <Select value={priority} onValueChange={setPriority}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Priority" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All priorities</SelectItem>
            <SelectItem value="low">Low</SelectItem>
            <SelectItem value="medium">Medium</SelectItem>
            <SelectItem value="high">High</SelectItem>
            <SelectItem value="urgent">Urgent</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <p className="p-6 text-sm text-muted-foreground">Loading…</p>
          ) : tickets.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-16 text-center">
              <Headset className="h-8 w-8 text-muted-foreground" />
              <p className="text-sm font-medium text-foreground">No tickets found</p>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="px-4 py-2.5">Subject</th>
                  <th className="px-4 py-2.5">Client</th>
                  <th className="px-4 py-2.5">Priority</th>
                  <th className="px-4 py-2.5">Status</th>
                  <th className="px-4 py-2.5">Assigned</th>
                  <th className="px-4 py-2.5">Created</th>
                  <th className="px-4 py-2.5" />
                </tr>
              </thead>
              <tbody>
                {tickets.map((ticket) => (
                  <tr key={ticket.id} className="border-b border-border last:border-b-0 hover:bg-accent/30">
                    <td className="px-4 py-3 font-medium text-foreground">{ticket.subject}</td>
                    <td className="px-4 py-3 text-muted-foreground">{ticket.company_name ?? '—'}</td>
                    <td className="px-4 py-3">
                      <StatusBadge label={ticket.priority} variant={PRIORITY_VARIANT[ticket.priority]} className="capitalize" />
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge label={ticket.status.replace('_', ' ')} variant={STATUS_VARIANT[ticket.status]} className="capitalize" />
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{ticket.assignee_name ?? 'Unassigned'}</td>
                    <td className="px-4 py-3 text-muted-foreground">{formatDate(ticket.created_at)}</td>
                    <td className="px-4 py-3 text-right">
                      <Button variant="outline" size="sm" onClick={() => setSelectedId(ticket.id)}>
                        <MessageSquare className="h-3.5 w-3.5" />
                        View
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>

      <Dialog open={!!selectedId} onOpenChange={(open) => !open && setSelectedId(null)}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>{selectedTicket?.subject ?? 'Ticket'}</DialogTitle>
          </DialogHeader>
          {selectedTicket && (
            <div className="space-y-4">
              <p className="text-sm text-muted-foreground">
                Raised by <span className="font-medium text-foreground">{selectedTicket.raised_by_name ?? 'Unknown'}</span> ·{' '}
                {selectedTicket.company_name}
              </p>

              <p className="rounded-lg bg-muted/40 p-3 text-sm text-foreground">{selectedTicket.description}</p>

              <div className="grid grid-cols-3 gap-2">
                <div className="space-y-1">
                  <p className="text-xs font-medium text-muted-foreground">Status</p>
                  <Select value={selectedTicket.status} onValueChange={(value) => updateMutation.mutate({ status: value as PlatformTicketStatus })}>
                    <SelectTrigger className="h-8 text-sm">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="open">Open</SelectItem>
                      <SelectItem value="in_progress">In Progress</SelectItem>
                      <SelectItem value="resolved">Resolved</SelectItem>
                      <SelectItem value="closed">Closed</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-medium text-muted-foreground">Priority</p>
                  <Select value={selectedTicket.priority} onValueChange={(value) => updateMutation.mutate({ priority: value as PlatformTicketPriority })}>
                    <SelectTrigger className="h-8 text-sm">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="low">Low</SelectItem>
                      <SelectItem value="medium">Medium</SelectItem>
                      <SelectItem value="high">High</SelectItem>
                      <SelectItem value="urgent">Urgent</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1">
                  <p className="text-xs font-medium text-muted-foreground">Assigned To</p>
                  <StatusBadge label={selectedTicket.assignee_name ?? 'Unassigned'} variant="neutral" />
                </div>
              </div>

              <div className="max-h-64 space-y-3 overflow-y-auto">
                {selectedTicket.replies.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No replies yet.</p>
                ) : (
                  selectedTicket.replies.map((reply) => (
                    <div
                      key={reply.id}
                      className={cn(
                        'rounded-lg border p-3',
                        reply.is_internal_note ? 'border-warning/40 bg-warning-bg' : 'border-border',
                      )}
                    >
                      <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5 font-medium text-foreground">
                          {reply.is_internal_note && <Lock className="h-3 w-3" />}
                          {reply.author_name ?? 'Staff'}
                          {reply.is_internal_note && <span className="text-warning">Internal Note</span>}
                        </span>
                        <span>{formatDate(reply.created_at)}</span>
                      </div>
                      <p className="text-sm text-foreground">{reply.body}</p>
                    </div>
                  ))
                )}
              </div>

              <div className="space-y-2">
                <Textarea value={replyBody} onChange={(event) => setReplyBody(event.target.value)} rows={3} placeholder="Write a reply…" />
                <div className="flex items-center justify-between">
                  <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <input type="checkbox" checked={isInternalNote} onChange={(event) => setIsInternalNote(event.target.checked)} />
                    Internal note (not visible to client)
                  </label>
                  <Button disabled={!replyBody || replyMutation.isPending} onClick={() => replyMutation.mutate()}>
                    {replyMutation.isPending ? 'Sending…' : 'Send Reply'}
                  </Button>
                </div>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
