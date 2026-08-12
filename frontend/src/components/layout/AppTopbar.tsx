import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { LogOut, ShieldCheck, UserCircle } from 'lucide-react'

import { useAuthStore } from '@/lib/auth-store'
import { useUiStore } from '@/lib/ui-store'
import { disconnectEcho } from '@/lib/echo'
import { fetchBranches } from '@/api/branches'
import { logout as logoutRequest } from '@/api/auth'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
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
  const selectedBranchId = useUiStore((state) => state.selectedBranchId)
  const setSelectedBranchId = useUiStore((state) => state.setSelectedBranchId)

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

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
    <header className="flex h-16 shrink-0 items-center justify-between border-b border-border bg-card px-6">
      <div className="flex items-center gap-3">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-foreground">
          {company?.name?.[0]?.toUpperCase() ?? 'F'}
        </div>
        <span className="text-base font-semibold text-foreground">{company?.name ?? 'FlipErp'}</span>
      </div>

      <div className="flex items-center gap-3">
        <Select
          value={selectedBranchId ? String(selectedBranchId) : 'all'}
          onValueChange={(value) => setSelectedBranchId(value === 'all' ? null : Number(value))}
        >
          <SelectTrigger className="h-9 w-48 rounded-full text-sm">
            <SelectValue placeholder="All branches" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All branches</SelectItem>
            {branches?.map((branch) => (
              <SelectItem key={branch.id} value={String(branch.id)}>
                {branch.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <NotificationBell />

        {user?.is_platform_staff && (
          <Button
            variant="outline"
            size="sm"
            className="gap-1.5 rounded-full"
            onClick={() => navigate('/platform-admin')}
          >
            <ShieldCheck className="h-4 w-4" />
            Platform Admin
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
