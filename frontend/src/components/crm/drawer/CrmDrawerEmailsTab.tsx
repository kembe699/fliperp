import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Send } from 'lucide-react'

import { sendCrmEmail } from '@/api/crm'
import { formatRelativeTime } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmEmail, CrmEmailStatus } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyState } from '@/components/shared/EmptyState'

const STATUS_VARIANT: Record<CrmEmailStatus, 'warning' | 'success' | 'danger'> = {
  queued: 'warning',
  sent: 'success',
  failed: 'danger',
}

interface CrmDrawerEmailsTabProps {
  leadId?: number | null
  dealId?: number | null
  customerId?: number | null
  defaultToEmail?: string | null
  defaultToName?: string | null
  emails: CrmEmail[]
  queryKeyToInvalidate: unknown[]
}

export function CrmDrawerEmailsTab({ leadId, dealId, customerId, defaultToEmail, defaultToName, emails, queryKeyToInvalidate }: CrmDrawerEmailsTabProps) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [toEmail, setToEmail] = useState(defaultToEmail ?? '')
  const [toName, setToName] = useState(defaultToName ?? '')
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')

  const mutation = useMutation({
    mutationFn: () =>
      sendCrmEmail({
        lead_id: leadId ?? null,
        deal_id: dealId ?? null,
        customer_id: customerId ?? null,
        to_email: toEmail,
        to_name: toName || null,
        subject,
        body,
      }),
    onSuccess: () => {
      toast.success('Email queued for delivery')
      setSubject('')
      setBody('')
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSend = toEmail.trim() && subject.trim() && body.trim()

  return (
    <div className="space-y-6">
      {can('crm-emails.create') && (
        <Card>
          <CardContent className="space-y-3 p-4">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <Label htmlFor="email-to">To</Label>
                <Input id="email-to" type="email" value={toEmail} onChange={(e) => setToEmail(e.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="email-to-name">Recipient Name</Label>
                <Input id="email-to-name" value={toName} onChange={(e) => setToName(e.target.value)} />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email-subject">Subject</Label>
              <Input id="email-subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email-body">Message</Label>
              <Textarea id="email-body" rows={5} value={body} onChange={(e) => setBody(e.target.value)} />
            </div>
            <div className="flex justify-end">
              <Button size="sm" disabled={!canSend || mutation.isPending} onClick={() => mutation.mutate()}>
                <Send className="h-3.5 w-3.5" />
                Send
              </Button>
            </div>
          </CardContent>
        </Card>
      )}

      <div>
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">History</p>
        {emails.length === 0 ? (
          <EmptyState title="No emails yet" subtext="Emails sent from this record will show up here." />
        ) : (
          <div className="space-y-2">
            {emails.map((email) => (
              <Card key={email.id}>
                <CardContent className="p-4">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-foreground">{email.subject}</p>
                      <p className="mt-0.5 truncate text-sm text-muted-foreground">
                        To {email.to_name ? `${email.to_name} <${email.to_email}>` : email.to_email}
                      </p>
                      <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{email.body}</p>
                    </div>
                    <div className="shrink-0 text-right">
                      <StatusBadge
                        label={email.status}
                        variant={STATUS_VARIANT[email.status]}
                        className={email.status === 'failed' ? 'cursor-help' : undefined}
                      />
                      <p className="mt-1 text-xs text-muted-foreground" title={new Date(email.created_at).toLocaleString()}>
                        {formatRelativeTime(email.created_at)}
                      </p>
                    </div>
                  </div>
                  {email.status === 'failed' && email.error_message && (
                    <p className="mt-2 rounded-md bg-danger-bg px-2.5 py-1.5 text-xs text-danger">{email.error_message}</p>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
