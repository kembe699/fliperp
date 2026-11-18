import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { sendCrmEmail } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

interface SendEmailDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  leadId?: number | null
  dealId?: number | null
  customerId?: number | null
  defaultEmail?: string | null
  defaultName?: string | null
  /** Refreshed after sending, so the Emails tab shows the new message. */
  queryKeyToInvalidate?: unknown[]
}

/**
 * Compose an email to a client from the CRM. It goes out under the company's
 * name, replies come back to the sender's own inbox, and it is logged on the
 * lead/deal/customer's Emails tab and in CRM > Emails.
 */
export function SendEmailDialog({
  open,
  onOpenChange,
  leadId,
  dealId,
  customerId,
  defaultEmail,
  defaultName,
  queryKeyToInvalidate,
}: SendEmailDialogProps) {
  const queryClient = useQueryClient()
  const user = useAuthStore((state) => state.user)
  const [toEmail, setToEmail] = useState('')
  const [toName, setToName] = useState('')
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')

  useEffect(() => {
    if (!open) return
    setToEmail(defaultEmail ?? '')
    setToName(defaultName ?? '')
    setSubject('')
    const firstName = (defaultName ?? '').trim().split(/\s+/)[0]
    setBody(`${firstName ? `Hi ${firstName},` : 'Hi,'}\n\n\n\nKind regards,\n${user?.name ?? ''}`)
  }, [open, defaultEmail, defaultName, user?.name])

  const mutation = useMutation({
    mutationFn: () =>
      sendCrmEmail({
        lead_id: leadId ?? null,
        deal_id: dealId ?? null,
        customer_id: customerId ?? null,
        to_email: toEmail.trim(),
        to_name: toName.trim() || null,
        subject: subject.trim(),
        body,
      }),
    onSuccess: (email) => {
      if (email.status === 'failed') {
        toast.error(`The email could not be sent: ${email.error_message ?? 'unknown error'}`)
      } else {
        toast.success(`Email sent to ${email.to_email}`)
        onOpenChange(false)
      }
      if (queryKeyToInvalidate) queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
      queryClient.invalidateQueries({ queryKey: ['crm-emails'] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const canSend = /\S+@\S+\.\S+/.test(toEmail.trim()) && subject.trim() !== '' && body.trim() !== ''

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>Send email</DialogTitle>
          <DialogDescription>Replies come straight back to {user?.email ?? 'your inbox'}.</DialogDescription>
        </DialogHeader>
        <form
          id="send-email-form"
          className="grid gap-4"
          onSubmit={(event) => {
            event.preventDefault()
            if (canSend && !mutation.isPending) mutation.mutate()
          }}
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
              <Label htmlFor="email-to">To (email)</Label>
              <Input id="email-to" type="email" required value={toEmail} onChange={(e) => setToEmail(e.target.value)} placeholder="client@example.com" />
            </div>
            <div className="grid gap-1.5">
              <Label htmlFor="email-name">Name</Label>
              <Input id="email-name" value={toName} onChange={(e) => setToName(e.target.value)} placeholder="Optional" />
            </div>
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="email-subject">Subject</Label>
            <Input id="email-subject" required value={subject} onChange={(e) => setSubject(e.target.value)} />
          </div>
          <div className="grid gap-1.5">
            <Label htmlFor="email-body">Message</Label>
            <Textarea id="email-body" required rows={10} value={body} onChange={(e) => setBody(e.target.value)} />
            <p className="text-xs text-muted-foreground">Links (https://…) become clickable.</p>
          </div>
        </form>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="submit" form="send-email-form" disabled={!canSend || mutation.isPending}>
            {mutation.isPending ? 'Sending…' : 'Send email'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
