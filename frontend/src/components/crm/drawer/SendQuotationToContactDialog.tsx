import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { sendQuotationToContact } from '@/api/crm'
import { useAuthStore } from '@/lib/auth-store'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { Quotation } from '@/types/quotation'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

interface SendQuotationToContactDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  quotation: Quotation
  leadId?: number
  dealId?: number
  contactEmail?: string | null
  contactName?: string | null
  queryKeyToInvalidate: unknown[]
  emailsQueryKeyToInvalidate: unknown[]
}

export function SendQuotationToContactDialog({
  open,
  onOpenChange,
  quotation,
  leadId,
  dealId,
  contactEmail,
  contactName,
  queryKeyToInvalidate,
  emailsQueryKeyToInvalidate,
}: SendQuotationToContactDialogProps) {
  const queryClient = useQueryClient()
  const company = useAuthStore((state) => state.company)

  const [toEmail, setToEmail] = useState('')
  const [toName, setToName] = useState('')
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    const companyName = company?.name ?? ''
    setToEmail(contactEmail ?? '')
    setToName(contactName ?? '')
    setSubject(`Quotation ${quotation.reference_number} from ${companyName}`)
    setBody(
      `Hi ${contactName ?? ''},\n\nPlease find attached quotation ${quotation.reference_number} from ${companyName}, valid until ${formatDate(quotation.valid_until)} for a total of ${formatCurrency(quotation.total_amount)}.\n\nLet us know if you have any questions.\n\n${companyName}`,
    )
    setError(null)
  }, [open, quotation, contactEmail, contactName, company])

  const mutation = useMutation({
    mutationFn: () =>
      sendQuotationToContact(quotation.id, {
        to_email: toEmail,
        to_name: toName || undefined,
        subject,
        body,
        lead_id: leadId,
        deal_id: dealId,
      }),
    onSuccess: () => {
      toast.success('Quotation queued for delivery')
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
      queryClient.invalidateQueries({ queryKey: emailsQueryKeyToInvalidate })
      onOpenChange(false)
    },
    onError: (err) => setError(getApiErrorInfo(err).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Send Quotation to Contact</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="quote-to">To</Label>
              <Input id="quote-to" type="email" value={toEmail} onChange={(e) => setToEmail(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="quote-to-name">Recipient Name</Label>
              <Input id="quote-to-name" value={toName} onChange={(e) => setToName(e.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="quote-subject">Subject</Label>
            <Input id="quote-subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="quote-body">Message</Label>
            <Textarea id="quote-body" rows={6} value={body} onChange={(e) => setBody(e.target.value)} />
            <p className="text-xs text-muted-foreground">The quotation PDF will be attached automatically.</p>
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!toEmail.trim() || !subject.trim() || !body.trim() || mutation.isPending} onClick={() => mutation.mutate()}>
            Send
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
