import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createDepartment, deleteDepartment, fetchDepartments, updateDepartment } from '@/api/hr'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Department } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const NO_PARENT = 'none'

export function DepartmentsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const { data: departments = [], isLoading, isError } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Department | null>(null)
  const [name, setName] = useState('')
  const [parentId, setParentId] = useState(NO_PARENT)

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setParentId(editing?.parent_id ? String(editing.parent_id) : NO_PARENT)
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name: name.trim(), parent_id: parentId === NO_PARENT ? null : Number(parentId) }
      return editing ? updateDepartment(editing.id, payload) : createDepartment(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Department updated' : 'Department added')
      queryClient.invalidateQueries({ queryKey: ['departments'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteDepartment,
    onSuccess: () => {
      toast.success('Department deleted')
      queryClient.invalidateQueries({ queryKey: ['departments'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const nameOf = (id: number | null) => (id ? departments.find((d) => d.id === id)?.name ?? `#${id}` : '—')

  const columns: DataTableColumn<Department>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'parent_id', header: 'Parent Department', accessor: (row) => nameOf(row.parent_id) },
  ]

  const rowActions = (): DataTableRowAction<Department>[] => [
    ...(can('departments.update') ? [{ label: 'Edit', onClick: (row: Department) => { setEditing(row); setFormOpen(true) } }] : []),
    ...(can('departments.delete') ? [{ label: 'Delete', destructive: true, onClick: (row: Department) => deleteMutation.mutate(row.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Departments"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('departments.csv', csvColumnsFromDataTable(columns), departments)} />
            {can('departments.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Department
              </Button>
            )}
          </div>
        }
      />

      <p className="mb-4 text-sm text-muted-foreground">
        Employees work in a department, and every product or service belongs to one, so sales, costs and stock can be reported per department in the finance
        reports. A department that still has products or services can't be deleted.
      </p>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load departments. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={departments}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No departments yet"
          emptySubtext="Add the departments you run, e.g. Pharmacy, Laboratory, Sales."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Department' : 'New Department'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label required>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="Pharmacy" />
            </div>
            <div className="space-y-1.5">
              <Label>Parent Department</Label>
              <Select value={parentId} onValueChange={setParentId}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={NO_PARENT}>None</SelectItem>
                  {departments
                    .filter((d) => d.id !== editing?.id)
                    .map((d) => (
                      <SelectItem key={d.id} value={String(d.id)}>
                        {d.name}
                      </SelectItem>
                    ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name.trim() || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Add Department'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
