import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { deleteRole, fetchRoles } from '@/api/settings'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Role } from '@/types/settings'

import { PageHeader } from '@/components/layout/PageHeader'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { RoleFormDialog } from '@/components/settings/RoleFormDialog'

export function RolesSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: roles, isLoading, isError } = useQuery({ queryKey: ['roles'], queryFn: fetchRoles })

  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Role | null>(null)

  const filtered = useMemo(() => {
    return (roles ?? []).filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [roles, search])

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
    { key: 'users_count', header: 'Users', render: (row) => row.users_count ?? '—' },
    { key: 'permissions', header: 'Permissions', render: (row) => `${row.permissions.length} granted` },
  ]

  const rowActions: (row: Role) => DataTableRowAction<Role>[] = (row) => [
    ...(can('roles.update') ? [{ label: 'Edit Permissions', onClick: (r: Role) => { setEditing(r); setFormOpen(true) } }] : []),
    ...(can('roles.delete') ? [{ label: 'Delete', destructive: true, onClick: (r: Role) => deleteMutation.mutate(r.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Roles"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('roles.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('roles.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Role
              </Button>
            )}
          </div>
        }
      />

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search roles…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load roles. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
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
