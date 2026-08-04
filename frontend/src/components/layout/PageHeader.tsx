import type { ReactNode } from 'react'
import { ChevronRight } from 'lucide-react'

interface PageHeaderProps {
  parent?: string
  title: string
  action?: ReactNode
}

export function PageHeader({ parent, title, action }: PageHeaderProps) {
  return (
    <div className="mb-6 flex items-start justify-between gap-4">
      <div>
        {parent && (
          <div className="mb-1 flex items-center gap-1.5 text-sm text-muted-foreground">
            <span>{parent}</span>
            <ChevronRight className="h-3.5 w-3.5" />
            <span className="font-semibold text-foreground">{title}</span>
          </div>
        )}
        <h1 className="text-2xl font-bold text-foreground">{title}</h1>
      </div>
      {action && <div className="shrink-0">{action}</div>}
    </div>
  )
}
