import { navConfig } from '@/nav/nav-config'

const flatEntries = navConfig.flatMap((item) => item.children ?? [item])

/**
 * Resolves which permission (if any) gates a given pathname, reusing the
 * exact same requiredPermission data the sidebar filters on — so route
 * guarding and sidebar visibility can never drift apart into two different
 * answers for "can this user reach this page." Detail/form routes (e.g.
 * /invoices/42, /invoices/new) aren't listed individually in nav-config;
 * they inherit their list page's permission via longest-prefix match.
 */
export function permissionForPath(pathname: string): string | undefined {
  const match = flatEntries
    .filter((entry) => entry.requiredPermission && (pathname === entry.path || pathname.startsWith(`${entry.path}/`)))
    .sort((a, b) => b.path.length - a.path.length)[0]

  return match?.requiredPermission
}
