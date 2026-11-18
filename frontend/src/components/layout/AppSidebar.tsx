import { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { Building2, ChevronDown, Headset, LogOut } from 'lucide-react'

import { cn } from '@/lib/utils'
import { useAuthStore } from '@/lib/auth-store'
import { logout as logoutRequest } from '@/api/auth'
import { usePermissions } from '@/hooks/use-permissions'
import { navConfig } from '@/nav/nav-config'
import { ContactSupportModal } from '@/components/support/ContactSupportModal'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { BrandLogo } from '@/components/brand/BrandLogo'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (first + last).toUpperCase()
}

export function AppSidebar() {
  const location = useLocation()
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const company = useAuthStore((state) => state.company)
  const clearAuth = useAuthStore((state) => state.clearAuth)
  const { can } = usePermissions()

  // Filter first, then everything below (active-section detection,
  // rendering) works off this list only — so a hidden item can never be
  // "active" from a stale URL, and a parent whose every child got filtered
  // out is dropped entirely rather than rendering as a dead expand toggle.
  const visibleNavConfig = useMemo(() => {
    return navConfig
      .map((item) => {
        const visibleChildren = item.children?.filter((child) => !child.requiredPermission || can(child.requiredPermission))
        if (item.children) {
          return visibleChildren && visibleChildren.length > 0 ? { ...item, children: visibleChildren } : null
        }
        return !item.requiredPermission || can(item.requiredPermission) ? item : null
      })
      .filter((item): item is NonNullable<typeof item> => item !== null)
  }, [can])

  const activeParentLabel = useMemo(() => {
    return visibleNavConfig.find((item) => item.children?.some((child) => location.pathname.startsWith(child.path)))?.label
  }, [location.pathname, visibleNavConfig])

  // One section open at a time: opening a section closes the one that was open.
  const [expanded, setExpanded] = useState<string | null>(activeParentLabel ?? null)
  const [supportModalOpen, setSupportModalOpen] = useState(false)

  useEffect(() => {
    if (activeParentLabel) {
      setExpanded(activeParentLabel)
    }
  }, [activeParentLabel])

  const toggleSection = (label: string) => {
    setExpanded((open) => (open === label ? null : label))
  }

  const handleLogout = async () => {
    try {
      await logoutRequest()
    } catch {
      // Even if the API call fails, still clear local state and redirect.
    }
    clearAuth()
    navigate('/login', { replace: true })
  }

  const roleLabel = user?.roles?.[0]?.replace(/_/g, ' ') ?? ''

  return (
    <aside className="flex h-screen w-[260px] shrink-0 flex-col border-r border-border bg-sidebar">
      {/* Product logo */}
      <div className="border-b border-border px-5 py-4">
        <BrandLogo className="h-8" />
      </div>

      {/* Company block */}
      <div className="px-5 pb-4 pt-5">
        <div className="flex items-center gap-3">
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
            <Building2 className="h-5 w-5" />
          </div>
          <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-foreground">{company?.name ?? 'Company'}</p>
            {roleLabel && <p className="truncate text-[11px] font-semibold uppercase tracking-wide text-primary">{roleLabel}</p>}
          </div>
        </div>
        <div className="mt-4 h-px w-full bg-primary/20" />
      </div>

      {/* Nav */}
      {/* Scrolls with the wheel or touch like before, without a visible scrollbar. */}
      <nav className="flex-1 overflow-y-auto overscroll-contain px-3 pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <p className="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Main Menu</p>
        <ul className="space-y-0.5">
          {visibleNavConfig.map((item) => {
            const Icon = item.icon
            const isActive = location.pathname === item.path
            const isExpanded = expanded === item.label
            const hasChildren = !!item.children?.length

            return (
              <li key={item.label}>
                {hasChildren ? (
                  <button
                    type="button"
                    onClick={() => toggleSection(item.label)}
                    aria-expanded={isExpanded}
                    aria-controls={`nav-section-${item.label}`}
                    className={cn(
                      'flex w-full items-center gap-2.5 rounded-full px-3 py-2 text-sm font-medium transition-colors',
                      isActive ? 'bg-accent text-primary' : 'text-foreground hover:bg-accent/60',
                    )}
                  >
                    <Icon className={cn('h-4 w-4 shrink-0', isActive ? 'text-primary' : 'text-muted-foreground')} />
                    <span className="flex-1 text-left">{item.label}</span>
                    <ChevronDown
                      className={cn('h-4 w-4 transition-transform duration-300 ease-in-out motion-reduce:transition-none', !isExpanded && '-rotate-90')}
                    />
                  </button>
                ) : (
                  <Link
                    to={item.path}
                    className={cn(
                      'flex items-center gap-2.5 rounded-full px-3 py-2 text-sm font-medium transition-colors',
                      isActive ? 'bg-accent text-primary' : 'text-foreground hover:bg-accent/60',
                    )}
                  >
                    <Icon className={cn('h-4 w-4 shrink-0', isActive ? 'text-primary' : 'text-muted-foreground')} />
                    <span>{item.label}</span>
                  </Link>
                )}

                {/* Always rendered so it can animate: the grid row grows from 0fr to 1fr. */}
                {hasChildren && (
                  <div
                    id={`nav-section-${item.label}`}
                    aria-hidden={!isExpanded}
                    className={cn(
                      'grid transition-[grid-template-rows,opacity] duration-300 ease-in-out motion-reduce:transition-none',
                      isExpanded ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0',
                    )}
                  >
                  <div className="min-h-0 overflow-hidden">
                  <ul className="ml-[1.125rem] mt-0.5 space-y-0.5 border-l border-border pl-4">
                    {item.children?.map((child) => {
                      const childActive = location.pathname === child.path
                      return (
                        <li key={child.path}>
                          <Link
                            to={child.path}
                            tabIndex={isExpanded ? undefined : -1}
                            className={cn(
                              'block rounded-full px-3 py-1.5 text-sm transition-colors',
                              childActive ? 'bg-accent font-medium text-primary' : 'text-muted-foreground hover:bg-accent/60 hover:text-foreground',
                            )}
                          >
                            {child.label}
                          </Link>
                        </li>
                      )
                    })}
                  </ul>
                  </div>
                  </div>
                )}
              </li>
            )
          })}
        </ul>
      </nav>

      {/* Sticky footer */}
      <div className="shrink-0 border-t border-border p-3">
        <Button className="mb-3 w-full justify-start gap-2" size="sm" onClick={() => setSupportModalOpen(true)}>
          <Headset className="h-4 w-4" />
          Contact Support
        </Button>
        <ContactSupportModal open={supportModalOpen} onOpenChange={setSupportModalOpen} />

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button type="button" className="flex w-full items-center gap-2.5 rounded-lg p-2 text-left hover:bg-accent/60">
              <Avatar className="h-9 w-9">
                <AvatarFallback>{user ? initials(user.name) : '?'}</AvatarFallback>
              </Avatar>
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium text-foreground">{user?.name ?? 'User'}</p>
                <p className="truncate text-xs text-muted-foreground">{user?.email ?? ''}</p>
              </div>
              <ChevronDown className="h-4 w-4 shrink-0 text-muted-foreground" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuItem disabled className="opacity-100">
              <div className="flex flex-col">
                <span className="text-sm font-medium text-foreground">{user?.name}</span>
                <span className="text-xs text-muted-foreground">{user?.email}</span>
              </div>
            </DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem onClick={handleLogout} className="text-destructive focus:text-destructive">
              <LogOut className="mr-2 h-4 w-4" />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </aside>
  )
}
