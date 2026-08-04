import type { ReactNode } from 'react'

import { cn } from '@/lib/utils'

export function PortalStatCard({
  label,
  value,
  icon,
  className,
}: {
  label: string
  value: ReactNode
  icon?: ReactNode
  className?: string
}) {
  return (
    <div className={cn('rounded-xl border border-border bg-white p-4', className)}>
      <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
        {icon}
        {label}
      </div>
      <p className="mt-1.5 text-2xl font-semibold text-foreground">{value}</p>
    </div>
  )
}
