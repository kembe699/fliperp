import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { ChevronDown } from 'lucide-react'

import { cn } from '@/lib/utils'

export function FilterBar({ children }: { children: ReactNode }) {
  return <div className="mb-4 flex flex-wrap items-center gap-2">{children}</div>
}

interface FilterPillProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  label: string
  active?: boolean
  withChevron?: boolean
}

export function FilterPill({ label, active, withChevron = true, className, ...props }: FilterPillProps) {
  return (
    <button
      type="button"
      className={cn(
        'inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
        active
          ? 'border-primary bg-accent text-primary'
          : 'border-border bg-card text-muted-foreground hover:text-foreground',
        className,
      )}
      {...props}
    >
      {label}
      {withChevron && <ChevronDown className="h-3.5 w-3.5" />}
    </button>
  )
}
