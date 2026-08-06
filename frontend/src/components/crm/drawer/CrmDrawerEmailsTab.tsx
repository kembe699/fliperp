import { formatRelativeTime } from '@/lib/format'
import type { CrmEmail, CrmEmailStatus } from '@/types/crm'

import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyState } from '@/components/shared/EmptyState'

const STATUS_VARIANT: Record<CrmEmailStatus, 'warning' | 'success' | 'danger'> = {
  queued: 'warning',
  sent: 'success',
  failed: 'danger',
}

interface CrmDrawerEmailsTabProps {
  emails: CrmEmail[]
}

export function CrmDrawerEmailsTab({ emails }: CrmDrawerEmailsTabProps) {
  if (emails.length === 0) {
    return <EmptyState title="No emails yet" subtext="Meeting confirmations and quotations sent from this record will show up here." />
  }

  return (
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
  )
}
