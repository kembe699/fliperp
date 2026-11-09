import { useMemo, useState } from 'react'
import { Link, Outlet, useMatch } from 'react-router-dom'
import { ArrowLeft, ChevronRight, PanelLeft, PanelLeftClose, Search } from 'lucide-react'

import { cn } from '@/lib/utils'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { CATALOG, GROUPS, findEntry } from './catalog'

/**
 * The Reports Center: every report in a sidebar grouped by area (searchable,
 * collapsible), the chosen report on the right with its own filters — the
 * same layout as the IntelliCare Reports Center.
 */
export function ReportsCenterLayout() {
  // The layout sits above the :reportId route, so it reads the id from the URL itself.
  const reportId = useMatch('/reports-center/:reportId')?.params.reportId ?? 'overview'
  const company = useAuthStore((state) => state.company)
  const { can, permissions } = usePermissions()
  // Start as icons only on a phone, where the full sidebar would cover the report.
  const [open, setOpen] = useState(() => typeof window === 'undefined' || window.innerWidth >= 1024)
  const [search, setSearch] = useState('')

  const visible = useMemo(() => {
    const term = search.trim().toLowerCase()
    return CATALOG.filter((entry) => can(entry.permission ?? 'reports.view'))
      .filter((entry) => !term || entry.label.toLowerCase().includes(term) || entry.desc.toLowerCase().includes(term))
    // can() reads `permissions`, which is the dependency that matters.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, permissions])
  const current = findEntry(reportId)

  return (
    <div className="flex h-screen bg-background print:block print:h-auto">
      <aside
        className={cn(
          'flex shrink-0 flex-col bg-gradient-to-b from-primary to-primary-hover text-primary-foreground transition-all duration-200 print:hidden',
          open ? 'w-64' : 'w-[68px]',
        )}
      >
        <div className="flex items-center gap-3 border-b border-white/10 p-4">
          {company?.logo_url ? (
            <img src={company.logo_url} alt="" className="h-9 w-9 shrink-0 rounded bg-white object-contain p-0.5" />
          ) : (
            <img src="/logo.png" alt="" className="h-8 w-8 shrink-0 rounded bg-white object-contain p-0.5" />
          )}
          {open && (
            <div className="min-w-0">
              <p className="truncate text-sm font-bold">{company?.name ?? 'Company'}</p>
              <p className="text-[11px] uppercase tracking-widest text-white/80">Reports Center</p>
            </div>
          )}
        </div>

        {open && (
          <div className="border-b border-white/10 p-3">
            <div className="relative">
              <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-white/60" />
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search reports..."
                className="h-8 border-white/15 bg-white/10 pl-8 text-xs text-white placeholder:text-white/50 focus-visible:ring-white/40"
              />
            </div>
          </div>
        )}

        <nav className="flex-1 overflow-y-auto p-2">
          {GROUPS.map((group) => {
            const entries = visible.filter((entry) => entry.group === group)
            if (entries.length === 0) return null
            return (
              <div key={group} className="mb-2">
                {open && <p className="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-widest text-white/70">{group}</p>}
                {entries.map((entry) => {
                  const Icon = entry.icon
                  const active = entry.id === reportId
                  return (
                    <Link
                      key={entry.id}
                      to={`/reports-center/${entry.id}`}
                      title={entry.label}
                      className={cn(
                        'flex w-full items-center gap-3 rounded-lg border px-3 py-2 text-sm transition-colors',
                        active ? 'border-white/25 bg-white/15' : 'border-transparent hover:bg-white/10',
                      )}
                    >
                      <Icon className="h-4 w-4 shrink-0" />
                      {open && (
                        <>
                          <span className="flex-1 truncate text-left">{entry.label}</span>
                          {active && <ChevronRight className="h-3.5 w-3.5 shrink-0 text-white/70" />}
                        </>
                      )}
                    </Link>
                  )
                })}
              </div>
            )
          })}
          {visible.length === 0 && open && <p className="px-3 py-4 text-xs text-white/70">No reports match.</p>}
        </nav>

        <div className="border-t border-white/10 p-3">
          <Link to="/dashboard" className="flex h-9 items-center gap-2 rounded-md px-2 text-xs hover:bg-white/10">
            <ArrowLeft className="h-4 w-4" />
            {open && 'Back to the app'}
          </Link>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col overflow-hidden print:overflow-visible">
        <header className="flex shrink-0 items-center gap-3 border-b border-border bg-card px-6 py-3 print:hidden">
          <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-muted-foreground" onClick={() => setOpen(!open)} aria-label="Toggle sidebar">
            {open ? <PanelLeftClose className="h-4 w-4" /> : <PanelLeft className="h-4 w-4" />}
          </Button>
          <div className="min-w-0">
            <h1 className="truncate text-lg font-bold text-foreground">{current?.label ?? 'Reports'}</h1>
            <p className="truncate text-xs text-muted-foreground">{current?.desc ?? 'Every report in one place'}</p>
          </div>
        </header>
        <main className="flex-1 overflow-auto bg-muted/30 p-6 print:overflow-visible print:bg-white print:p-0">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
