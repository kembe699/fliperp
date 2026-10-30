import { useAuthStore } from '@/lib/auth-store'

/**
 * The modules the platform has switched on for the signed-in company. Menus
 * and pages already follow permissions (a switched-off module's permissions
 * are withheld); this is for pieces of other screens that belong to a
 * module, like the SOF and cost centre fields on a purchase order line.
 */
export function useModules() {
  const enabled = useAuthStore((state) => state.company?.enabled_modules)

  // No list (not signed in yet, or a session from before modules existed): everything on.
  const hasModule = (key: string) => !enabled || enabled.includes(key)

  return { enabledModules: enabled, hasModule }
}
