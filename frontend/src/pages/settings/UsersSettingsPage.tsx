import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { deleteUser, fetchUsers } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { SettingsUser } from '@/types/settings'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { UserFormDialog } from '@/components/settings/UserFormDialog'

export function UsersSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<SettingsUser | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['users', page], queryFn: () => fetchUsers({ page, per_page: 15 }) })

  const deleteMutation = useMutation({
    mutationFn: deleteUser,
    onSuccess: () => {
      toast.success('User deleted')
      queryClient.invalidateQueries({ queryKey: ['users'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<SettingsUser>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'email', header: 'Email', accessor: (row) => row.email },
    { key: 'roles', header: 'Roles', render: (row) => (row.roles.length > 0 ? row.roles.join(', ') : '—') },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: SettingsUser) => DataTableRowAction<SettingsUser>[] = (row) => [
    ...(can('users.update') ? [{ label: 'Edit', onClick: (u: SettingsUser) => { setEditing(u); setFormOpen(true) } }] : []),
    ...(can('users.delete') ? [{ label: 'Delete', destructive: true, onClick: (u: SettingsUser) => deleteMutation.mutate(u.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Users"
        action={
          can('users.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New User
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load users. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No users found"
          emptySubtext="Create a user to give your team access to this company."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <UserFormDialog open={formOpen} onOpenChange={setFormOpen} user={editing} />
    </div>
  )
}
