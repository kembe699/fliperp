import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Headset, MessageSquare, Plus } from 'lucide-react'

import { fetchSupportTicket, fetchSupportTickets, replySupportTicket } from '@/api/support'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatDate } from '@/lib/format'
import type { TicketPriority, TicketStatus } from '@/types/support'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { ContactSupportModal } from '@/components/support/ContactSupportModal'

const STATUS_VARIANT: Record<TicketStatus, 'info' | 'warning' | 'success' | 'neutral'> = {
  open: 'info',
  in_progress: 'warning',
  resolved: 'success',
  closed: 'neutral',
}

const PRIORITY_VARIANT: Record<TicketPriority, 'neutral' | 'info' | 'warning' | 'danger'> = {
  low: 'neutral',
  medium: 'info',
  high: 'warning',
  urgent: 'danger',
}

export function SupportTicketsPage() {
  const queryClient = useQueryClient()
  const [contactOpen, setContactOpen] = useState(false)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [replyBody, setReplyBody] = useState('')

  const { data, isLoading } = useQuery({ queryKey: ['support-tickets'], queryFn: () => fetchSupportTickets({ per_page: 50 }) })
  const tickets = data?.data ?? []

  const { data: selectedTicket } = useQuery({
    queryKey: ['support-ticket', selectedId],
    queryFn: () => fetchSupportTicket(selectedId!),
    enabled: !!selectedId,
  })

  const replyMutation = useMutation({
    mutationFn: () => replySupportTicket(selectedId!, replyBody),
    onSuccess: () => {
      setReplyBody('')
      queryClient.invalidateQueries({ queryKey: ['support-ticket', selectedId] })
      queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canReply = selectedTicket && selectedTicket.status !== 'closed'

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Support Tickets"
        action={
          <Button onClick={() => setContactOpen(true)}>
            <Plus className="h-4 w-4" />
            New Ticket
          </Button>
        }
      />

      <Card>
        <CardContent className="p-0">
          {isLoading ? (
            <p className="p-6 text-sm text-muted-foreground">Loading…</p>
          ) : tickets.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-16 text-center">
              <Headset className="h-8 w-8 text-muted-foreground" />
              <p className="text-sm font-medium text-foreground">No support tickets yet</p>
              <p className="text-sm text-muted-foreground">Raised tickets for your company will show up here.</p>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="px-4 py-2.5">Subject</th>
                  <th className="px-4 py-2.5">Priority</th>
                  <th className="px-4 py-2.5">Status</th>
                  <th className="px-4 py-2.5">Raised</th>
                  <th className="px-4 py-2.5" />
                </tr>
              </thead>
              <tbody>
                {tickets.map((ticket) => (
                  <tr key={ticket.id} className="border-b border-border last:border-b-0 hover:bg-accent/30">
                    <td className="px-4 py-3 font-medium text-foreground">{ticket.subject}</td>
                    <td className="px-4 py-3">
                      <StatusBadge label={ticket.priority} variant={PRIORITY_VARIANT[ticket.priority]} className="capitalize" />
                    </td>
                    <td className="px-4 py-3">
                      <StatusBadge label={ticket.status.replace('_', ' ')} variant={STATUS_VARIANT[ticket.status]} className="capitalize" />
                    </td>
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

      <ContactSupportModal open={contactOpen} onOpenChange={setContactOpen} />

      <Dialog open={!!selectedId} onOpenChange={(open) => !open && setSelectedId(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{selectedTicket?.subject ?? 'Ticket'}</DialogTitle>
          </DialogHeader>
          {selectedTicket && (
            <div className="space-y-4">
              <div className="flex items-center gap-2">
                <StatusBadge label={selectedTicket.priority} variant={PRIORITY_VARIANT[selectedTicket.priority]} className="capitalize" />
                <StatusBadge label={selectedTicket.status.replace('_', ' ')} variant={STATUS_VARIANT[selectedTicket.status]} className="capitalize" />
              </div>

              <p className="rounded-lg bg-muted/40 p-3 text-sm text-foreground">{selectedTicket.description}</p>

              <div className="max-h-64 space-y-3 overflow-y-auto">
                {selectedTicket.replies.length === 0 ? (
                  <p className="text-sm text-muted-foreground">No replies yet.</p>
                ) : (
                  selectedTicket.replies.map((reply) => (
                    <div key={reply.id} className="rounded-lg border border-border p-3">
                      <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                        <span className="font-medium text-foreground">{reply.author_name ?? 'Support'}</span>
                        <span>{formatDate(reply.created_at)}</span>
                      </div>
                      <p className="text-sm text-foreground">{reply.body}</p>
                    </div>
                  ))
                )}
              </div>

              {canReply ? (
                <div className="space-y-2">
                  <Textarea value={replyBody} onChange={(event) => setReplyBody(event.target.value)} rows={3} placeholder="Write a reply…" />
                  <Button className="w-full" disabled={!replyBody || replyMutation.isPending} onClick={() => replyMutation.mutate()}>
                    {replyMutation.isPending ? 'Sending…' : 'Send Reply'}
                  </Button>
                </div>
              ) : (
                <p className="text-center text-xs text-muted-foreground">This ticket is closed.</p>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}
