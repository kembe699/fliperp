import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchDepartments, fetchEmployees, fetchPositions } from '@/api/hr'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Employee, EmployeeStatus } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { EmployeeFormDialog } from '@/components/hr/EmployeeFormDialog'
import { EmployeeAvatar } from '@/components/hr/EmployeeAvatar'

const STATUS_VARIANT: Record<EmployeeStatus, 'success' | 'warning' | 'neutral'> = {
  active: 'success',
  on_leave: 'warning',
  terminated: 'neutral',
}

export function EmployeesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Employee | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['employees', page], queryFn: () => fetchEmployees({ page, per_page: 15 }) })
  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments })
  const { data: positions } = useQuery({ queryKey: ['positions'], queryFn: fetchPositions })

  const departmentName = (id: number) => departments?.find((d) => d.id === id)?.name ?? `#${id}`
  const positionTitle = (id: number) => positions?.find((p) => p.id === id)?.title ?? `#${id}`

  const columns: DataTableColumn<Employee>[] = [
    {
      key: 'photo',
      header: '',
      render: (row) => <EmployeeAvatar src={row.photo_url} name={`${row.first_name} ${row.last_name}`} />,
      className: 'w-12',
    },
    { key: 'employee_code', header: 'Code', accessor: (row) => row.employee_code, sortable: true },
    { key: 'name', header: 'Name', accessor: (row) => `${row.first_name} ${row.last_name}` },
    { key: 'department_id', header: 'Department', accessor: (row) => departmentName(row.department_id) },
    { key: 'position_id', header: 'Position', accessor: (row) => positionTitle(row.position_id) },
    { key: 'employment_type', header: 'Type', accessor: (row) => row.employment_type.replace('_', ' ') },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={STATUS_VARIANT[row.status]} /> },
  ]

  const rowActions: (row: Employee) => DataTableRowAction<Employee>[] = (row) => [
    { label: 'View', onClick: (e: Employee) => navigate(`/employees/${e.id}`) },
    ...(can('employees.update') ? [{ label: 'Edit', onClick: (e: Employee) => { setEditing(e); setFormOpen(true) } }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Employees"
        action={
          <div className="flex gap-2">
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchEmployees({ per_page: 10000 })
                exportToCsv('employees.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('employees.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Employee
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load employees. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No employees found"
          emptySubtext="Add an employee to start tracking HR and payroll."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <EmployeeFormDialog open={formOpen} onOpenChange={setFormOpen} employee={editing} />
    </div>
  )
}
