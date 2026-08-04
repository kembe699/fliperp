import { useAuthStore } from '@/lib/auth-store'

export function usePermissions() {
  const permissions = useAuthStore((state) => state.permissions)
  const roles = useAuthStore((state) => state.roles)

  const can = (permission: string) => permissions.includes(permission)
  const canAny = (perms: string[]) => perms.some((permission) => permissions.includes(permission))
  const hasRole = (role: string) => roles.includes(role)

  return { permissions, roles, can, canAny, hasRole }
}
