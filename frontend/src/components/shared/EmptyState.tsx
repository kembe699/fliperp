import type { LucideIcon } from 'lucide-react'
import { Inbox } from 'lucide-react'

interface EmptyStateProps {
  icon?: LucideIcon
  title?: string
  subtext?: string
}

export function EmptyState({ icon: Icon = Inbox, title = 'No data found', subtext }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
      <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
        <Icon className="h-7 w-7 text-muted-foreground" strokeWidth={1.5} />
      </div>
      <p className="text-base font-semibold text-foreground">{title}</p>
      {subtext && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{subtext}</p>}
    </div>
  )
}
