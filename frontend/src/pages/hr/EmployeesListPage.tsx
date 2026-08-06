import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchDepartments, fetchEmployees, fetchPositions } from '@/api/hr'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Employee, EmployeeStatus } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { EmployeeFormDialog } from '@/components/hr/EmployeeFormDialog'
import { EmployeeAvatar } from '@/components/hr/EmployeeAvatar'

const STATUS_VARIANT: Record<EmployeeStatus, 'success' | 'warning' | 'neutral'> = {
  active: 'success',
  on_leave: 'warning',
  terminated: 'neutral',
}
const STATUSES: EmployeeStatus[] = ['active', 'on_leave', 'terminated']
const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function EmployeesListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [departmentId, setDepartmentId] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Employee | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['employees', 'all'], queryFn: () => fetchEmployees({ per_page: 2000 }) })
  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments })
  const { data: positions } = useQuery({ queryKey: ['positions'], queryFn: fetchPositions })

  const filtered = useMemo(() => {
    return (data?.data ?? [])
      .filter((row) => status === 'all' || row.status === status)
      .filter((row) => departmentId === 'all' || row.department_id === Number(departmentId))
      .filter(
        (row) =>
          !search ||
          `${row.first_name} ${row.last_name}`.toLowerCase().includes(search.toLowerCase()) ||
          row.employee_code.toLowerCase().includes(search.toLowerCase()),
      )
  }, [data, status, departmentId, search])

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
            <ExportCsvButton onExport={async () => exportToCsv('employees.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('employees.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Employee
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
            {STATUSES.map((s) => (
              <SelectItem key={s} value={s}>
                {s.replace('_', ' ')}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={departmentId} onValueChange={setDepartmentId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Department" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All departments</SelectItem>
            {departments?.map((department) => (
              <SelectItem key={department.id} value={String(department.id)}>
                {department.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'name', label: 'Name' },
            { value: 'employee_code', label: 'Code' },
          ]}
          placeholder="Search employees…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load employees. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No employees found"
          emptySubtext="Add an employee to start tracking HR and payroll."
        />
      )}

      <EmployeeFormDialog open={formOpen} onOpenChange={setFormOpen} employee={editing} />
    </div>
  )
}
