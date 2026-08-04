import type { ReactNode } from 'react'
import { ShieldAlert } from 'lucide-react'

import { usePermissions } from '@/hooks/use-permissions'

/**
 * Gates a route on an actual permission, not just a hidden sidebar link —
 * a user who guesses/bookmarks the URL for a page they can't see in the
 * sidebar still hits this instead of a page that renders broken (the
 * underlying API calls would 403 anyway; this just explains why instead of
 * showing empty tables and failed-request toasts).
 */
export function RequirePermission({ permission, children }: { permission: string; children: ReactNode }) {
  const { can } = usePermissions()

  if (!can(permission)) {
    return (
      <div className="flex flex-col items-center justify-center gap-3 py-24 text-center">
        <ShieldAlert className="h-12 w-12 text-muted-foreground" />
        <p className="text-base font-semibold text-foreground">You don't have access to this page</p>
        <p className="max-w-sm text-sm text-muted-foreground">Contact an administrator if you believe you should be able to see this.</p>
      </div>
    )
  }

  return <>{children}</>
}
