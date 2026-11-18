import { useState } from 'react'
import { Mail } from 'lucide-react'

import { formatRelativeTime } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmEmail, CrmEmailStatus } from '@/types/crm'

import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyState } from '@/components/shared/EmptyState'
import { Button } from '@/components/ui/button'
import { SendEmailDialog } from '@/components/crm/SendEmailDialog'

const STATUS_VARIANT: Record<CrmEmailStatus, 'warning' | 'success' | 'danger'> = {
  queued: 'warning',
  sent: 'success',
  failed: 'danger',
}

interface CrmDrawerEmailsTabProps {
  emails: CrmEmail[]
  /** Where "Send email" files the message, and who it is addressed to by default. */
  leadId?: number | null
  dealId?: number | null
  customerId?: number | null
  defaultEmail?: string | null
  defaultName?: string | null
  queryKeyToInvalidate?: unknown[]
}

export function CrmDrawerEmailsTab({ emails, leadId, dealId, customerId, defaultEmail, defaultName, queryKeyToInvalidate }: CrmDrawerEmailsTabProps) {
  const { can } = usePermissions()
  const [composeOpen, setComposeOpen] = useState(false)
  const canCompose = can('crm-emails.create') && Boolean(leadId || dealId || customerId)

  return (
    <div className="space-y-2">
      {canCompose && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setComposeOpen(true)}>
            <Mail className="h-4 w-4" />
            Send email
          </Button>
        </div>
      )}
      {emails.length === 0 && (
        <EmptyState title="No emails yet" subtext="Emails, meeting confirmations and quotations sent to this contact show up here." />
      )}
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
      {canCompose && (
        <SendEmailDialog
          open={composeOpen}
          onOpenChange={setComposeOpen}
          leadId={leadId}
          dealId={dealId}
          customerId={customerId}
          defaultEmail={defaultEmail}
          defaultName={defaultName}
          queryKeyToInvalidate={queryKeyToInvalidate}
        />
      )}
    </div>
  )
}
