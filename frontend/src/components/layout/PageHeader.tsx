import type { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, ChevronRight } from 'lucide-react'

interface PageHeaderProps {
  parent?: string
  title: string
  action?: ReactNode
}

export function PageHeader({ parent, title, action }: PageHeaderProps) {
  const navigate = useNavigate()

  return (
    <div className="mb-4 flex flex-wrap items-start justify-between gap-3 sm:mb-6 sm:gap-4">
      <div className="min-w-0">
        {parent && (
          <div className="mb-1 flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground">
            <button
              type="button"
              onClick={() => navigate(-1)}
              aria-label="Go back"
              className="-ml-1 flex items-center gap-1 rounded-full p-1 hover:bg-muted hover:text-foreground"
            >
              <ArrowLeft className="h-3.5 w-3.5" />
            </button>
            <span className="shrink-0">{parent}</span>
            <ChevronRight className="h-3.5 w-3.5" />
            <span className="truncate font-semibold text-foreground">{title}</span>
          </div>
        )}
        <h1 className="break-words text-xl font-bold text-foreground sm:text-2xl">{title}</h1>
      </div>
      {action && <div className="flex max-w-full flex-wrap items-center gap-2 sm:shrink-0 [&>div]:flex-wrap">{action}</div>}
    </div>
  )
}
