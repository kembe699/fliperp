import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { deleteRole, fetchRoles } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Role } from '@/types/settings'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { RoleFormDialog } from '@/components/settings/RoleFormDialog'

export function RolesSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: roles, isLoading, isError } = useQuery({ queryKey: ['roles'], queryFn: fetchRoles })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Role | null>(null)

  const deleteMutation = useMutation({
    mutationFn: deleteRole,
    onSuccess: () => {
      toast.success('Role deleted')
      queryClient.invalidateQueries({ queryKey: ['roles'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Role>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'permissions', header: 'Permissions', render: (row) => `${row.permissions.length} granted` },
  ]

  const rowActions: (row: Role) => DataTableRowAction<Role>[] = (row) => [
    ...(can('roles.update') ? [{ label: 'Edit', onClick: (r: Role) => { setEditing(r); setFormOpen(true) } }] : []),
    ...(can('roles.delete') ? [{ label: 'Delete', destructive: true, onClick: (r: Role) => deleteMutation.mutate(r.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Roles"
        action={
          can('roles.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Role
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load roles. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={roles ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No roles found"
          emptySubtext="Create a role to define a reusable set of permissions."
        />
      )}

      <RoleFormDialog open={formOpen} onOpenChange={setFormOpen} role={editing} />
    </div>
  )
}
