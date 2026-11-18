import { useNavigate } from 'react-router-dom'
import { LogOut, Menu, ShieldCheck, UserCircle } from 'lucide-react'

import { useAuthStore } from '@/lib/auth-store'
import { useUiStore } from '@/lib/ui-store'
import { disconnectEcho } from '@/lib/echo'
import { logout as logoutRequest } from '@/api/auth'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { BranchPicker } from '@/components/layout/BranchPicker'
import { NotificationBell } from '@/components/layout/NotificationBell'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (first + last).toUpperCase()
}

export function AppTopbar() {
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const company = useAuthStore((state) => state.company)
  const clearAuth = useAuthStore((state) => state.clearAuth)
  const setMobileNavOpen = useUiStore((state) => state.setMobileNavOpen)

  const handleLogout = async () => {
    try {
      await logoutRequest()
    } catch {
      // Even if the API call fails, still clear local state and redirect.
    }
    disconnectEcho()
    clearAuth()
    navigate('/login', { replace: true })
  }

  return (
    <header className="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-border bg-card px-3 sm:h-16 sm:px-6">
      <div className="flex min-w-0 items-center gap-2 sm:gap-3">
        {/* Phones and tablets: opens the main menu, which slides in from the left. */}
        <button
          type="button"
          onClick={() => setMobileNavOpen(true)}
          aria-label="Open menu"
          className="-ml-1 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-foreground hover:bg-muted lg:hidden"
        >
          <Menu className="h-5 w-5" />
        </button>
        <div className="hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground sm:flex">
          {company?.name?.[0]?.toUpperCase() ?? 'F'}
        </div>
        <span className="truncate text-sm font-semibold text-foreground sm:text-base">{company?.name ?? 'FlipERP'}</span>
      </div>

      <div className="flex shrink-0 items-center gap-1.5 sm:gap-3">
        <BranchPicker className="hidden w-40 sm:flex md:w-48" />

        <NotificationBell />

        {user?.is_platform_staff && (
          <Button
            variant="outline"
            size="sm"
            className="gap-1.5 rounded-full"
            onClick={() => navigate('/platform-admin')}
            aria-label="Platform Admin"
          >
            <ShieldCheck className="h-4 w-4" />
            <span className="hidden md:inline">Platform Admin</span>
          </Button>
        )}

        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button type="button" aria-label="User menu">
              <Avatar className="h-9 w-9">
                <AvatarFallback>{user ? initials(user.name) : '?'}</AvatarFallback>
              </Avatar>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-48">
            <DropdownMenuItem onClick={() => navigate('/profile')}>
              <UserCircle className="mr-2 h-4 w-4" />
              My Profile
            </DropdownMenuItem>
            <DropdownMenuItem onClick={handleLogout} className="text-destructive focus:text-destructive">
              <LogOut className="mr-2 h-4 w-4" />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  )
}
