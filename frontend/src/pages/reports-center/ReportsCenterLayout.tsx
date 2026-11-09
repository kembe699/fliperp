import { useMemo, useState } from 'react'
import { Link, Outlet, useMatch } from 'react-router-dom'
import { ArrowLeft, Building2, PanelLeft, PanelLeftClose, Search } from 'lucide-react'

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
      {/* Same look as the main app sidebar (AppSidebar): white, bordered, pill items. */}
      <aside className={cn('flex h-screen shrink-0 flex-col border-r border-border bg-sidebar transition-all duration-200 print:hidden', open ? 'w-[260px]' : 'w-[68px]')}>
        <div className={cn('border-b border-border py-4', open ? 'px-5' : 'px-3')}>
          <img src="/logo.png" alt="FlipErp" className={cn('h-8 w-auto', !open && 'mx-auto h-6')} />
        </div>

        {open ? (
          <div className="px-5 pb-4 pt-5">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                {company?.logo_url ? <img src={company.logo_url} alt="" className="h-8 w-8 rounded object-contain" /> : <Building2 className="h-5 w-5" />}
              </div>
              <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-foreground">{company?.name ?? 'Company'}</p>
                <p className="truncate text-[11px] font-semibold uppercase tracking-wide text-primary">Reports Center</p>
              </div>
            </div>
            <div className="relative mt-4">
              <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
              <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search reports..." className="h-8 pl-8 text-xs" />
            </div>
            <div className="mt-4 h-px w-full bg-primary/20" />
          </div>
        ) : (
          <div className="flex justify-center py-4">
            <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-accent text-primary">
              <Building2 className="h-5 w-5" />
            </div>
          </div>
        )}

        <nav className={cn('flex-1 overflow-y-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden', open ? 'px-3' : 'px-2')}>
          {GROUPS.map((group) => {
            const entries = visible.filter((entry) => entry.group === group)
            if (entries.length === 0) return null
            return (
              <div key={group} className="mb-3">
                {open && <p className="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{group}</p>}
                <ul className="space-y-0.5">
                  {entries.map((entry) => {
                    const Icon = entry.icon
                    const active = entry.id === reportId
                    return (
                      <li key={entry.id}>
                        <Link
                          to={`/reports-center/${entry.id}`}
                          title={entry.label}
                          className={cn(
                            'flex items-center gap-2.5 rounded-full py-2 text-sm font-medium transition-colors',
                            open ? 'px-3' : 'justify-center px-0',
                            active ? 'bg-accent text-primary' : 'text-foreground hover:bg-accent/60',
                          )}
                        >
                          <Icon className={cn('h-4 w-4 shrink-0', active ? 'text-primary' : 'text-muted-foreground')} />
                          {open && <span className="flex-1 truncate">{entry.label}</span>}
                        </Link>
                      </li>
                    )
                  })}
                </ul>
              </div>
            )
          })}
          {visible.length === 0 && open && <p className="px-2 py-4 text-xs text-muted-foreground">No reports match.</p>}
        </nav>

        <div className="shrink-0 border-t border-border p-3">
          <Link
            to="/dashboard"
            title="Back to the app"
            className={cn('flex h-9 items-center gap-2.5 rounded-full text-sm font-medium text-foreground transition-colors hover:bg-accent/60', open ? 'px-3' : 'justify-center')}
          >
            <ArrowLeft className="h-4 w-4 text-muted-foreground" />
            {open && 'Back to the app'}
          </Link>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col overflow-hidden print:overflow-visible">
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-border bg-card px-6 print:hidden">
          <Button variant="ghost" size="sm" className="h-8 w-8 p-0 text-muted-foreground" onClick={() => setOpen(!open)} aria-label="Toggle sidebar">
            {open ? <PanelLeftClose className="h-4 w-4" /> : <PanelLeft className="h-4 w-4" />}
          </Button>
          <div className="min-w-0">
            <h1 className="truncate text-lg font-bold text-foreground">{current?.label ?? 'Reports'}</h1>
            <p className="truncate text-xs text-muted-foreground">{current?.desc ?? 'Every report in one place'}</p>
          </div>
        </header>
        <main className="flex-1 overflow-auto bg-background p-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden print:overflow-visible print:bg-white print:p-0">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
