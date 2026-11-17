import { Link, useLocation } from 'react-router-dom'

import { cn } from '@/lib/utils'

const VIEWS = [
  { path: '/reports/general-ledger', label: 'By transaction', hint: 'Every journal entry on one line' },
  { path: '/reports/general-ledger/by-account', label: 'By account', hint: 'Each account with opening, running and closing balance' },
]

/** The two views of the one General Ledger menu item. */
export function LedgerViewTabs() {
  const { pathname } = useLocation()

  return (
    <div className="mb-4 inline-flex rounded-lg border border-border bg-card p-1" role="tablist" aria-label="General Ledger view">
      {VIEWS.map((view) => {
        const active = pathname === view.path
        return (
          <Link
            key={view.path}
            to={view.path}
            role="tab"
            aria-selected={active}
            title={view.hint}
            className={cn('rounded-md px-4 py-1.5 text-sm font-medium transition-colors', active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground')}
          >
            {view.label}
          </Link>
        )
      })}
    </div>
  )
}
