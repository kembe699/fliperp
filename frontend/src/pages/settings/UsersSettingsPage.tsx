import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { deleteUser, fetchUsers } from '@/api/settings'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { SettingsUser } from '@/types/settings'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { UserFormDialog } from '@/components/settings/UserFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function UsersSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<SettingsUser | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['users', 'all'], queryFn: () => fetchUsers({ per_page: 2000 }) })

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || (status === 'active' ? row.is_active : !row.is_active))
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()) || row.email.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

  const deleteMutation = useMutation({
    mutationFn: deleteUser,
    onSuccess: () => {
      toast.success('User deleted')
      queryClient.invalidateQueries({ queryKey: ['users', 'all'] })
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
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('users.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('users.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New User
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'name', label: 'Name' },
            { value: 'email', label: 'Email' },
          ]}
          placeholder="Search users…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load users. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No users found"
          emptySubtext="Create a user to give your team access to this company."
        />
      )}

      <UserFormDialog open={formOpen} onOpenChange={setFormOpen} user={editing} />
    </div>
  )
}
