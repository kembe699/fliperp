import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchAttendance, fetchDepartments, fetchEmployees } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Attendance, AttendanceStatus } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { AttendanceFormDialog } from '@/components/hr/AttendanceFormDialog'
import { AttendanceQrCodeSection } from '@/components/hr/AttendanceQrCodeSection'
import { AttendanceGeofencesSection } from '@/components/hr/AttendanceGeofencesSection'

const STATUSES: AttendanceStatus[] = ['present', 'absent', 'late', 'on_leave']
const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function AttendancePage() {
  const { can } = usePermissions()
  const [page, setPage] = useState(1)
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [status, setStatus] = useState('all')
  const [departmentId, setDepartmentId] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['attendance', page, dateFrom, dateTo, status, departmentId, search],
    queryFn: () =>
      fetchAttendance({
        page,
        per_page: 15,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
        status: status === 'all' ? undefined : (status as AttendanceStatus),
        department_id: departmentId === 'all' ? undefined : Number(departmentId),
        search: search || undefined,
      }),
  })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })
  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const [selectedBranchId, setSelectedBranchId] = useState<number | null>(null)
  useEffect(() => {
    if (!selectedBranchId && branches && branches.length > 0) {
      setSelectedBranchId(branches[0].id)
    }
  }, [branches, selectedBranchId])

  const employeeName = (id: number) => {
    const employee = employees?.data.find((e) => e.id === id)
    return employee ? `${employee.first_name} ${employee.last_name}` : `#${id}`
  }

  const columns: DataTableColumn<Attendance>[] = [
    { key: 'date', header: 'Date', accessor: (row) => row.date, sortable: true, render: (row) => formatDate(row.date) },
    { key: 'employee_id', header: 'Employee', render: (row) => employeeName(row.employee_id) },
    { key: 'clock_in', header: 'Clock In', accessor: (row) => row.clock_in ?? '—' },
    { key: 'clock_out', header: 'Clock Out', accessor: (row) => row.clock_out ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={row.status === 'present' ? 'success' : row.status === 'absent' ? 'danger' : 'warning'} /> },
  ]

  const currentFilters = {
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    status: status === 'all' ? undefined : (status as AttendanceStatus),
    department_id: departmentId === 'all' ? undefined : Number(departmentId),
    search: search || undefined,
  }

  const selectedBranch = branches?.find((branch) => branch.id === selectedBranchId)

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Attendance"
        action={
          can('attendance.create') && (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="h-4 w-4" />
              Record Attendance
            </Button>
          )
        }
      />

      <Tabs defaultValue="records">
        <TabsList>
          <TabsTrigger value="records">Records</TabsTrigger>
          <TabsTrigger value="qr-locations">QR Code &amp; Locations</TabsTrigger>
        </TabsList>

        <TabsContent value="records">
          <FilterBar>
            <div className="flex items-center gap-2">
              <Input type="date" value={dateFrom} onChange={(event) => { setDateFrom(event.target.value); setPage(1) }} className="h-8 w-auto rounded-full" placeholder="From" />
              <span className="text-sm text-muted-foreground">to</span>
              <Input type="date" value={dateTo} onChange={(event) => { setDateTo(event.target.value); setPage(1) }} className="h-8 w-auto rounded-full" placeholder="To" />
            </div>

            <Select value={departmentId} onValueChange={(value) => { setDepartmentId(value); setPage(1) }}>
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

            <Select value={status} onValueChange={(value) => { setStatus(value); setPage(1) }}>
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

            <ExportCsvButton
              onExport={async () => {
                const all = await fetchAttendance({ per_page: 10000, ...currentFilters })
                exportToCsv(
                  'attendance.csv',
                  csvColumnsFromDataTable(columns).concat([{ header: 'Employee', accessor: (row: Attendance) => employeeName(row.employee_id) }]),
                  all.data,
                )
              }}
            />
          </FilterBar>

          <div className="mb-4">
            <SearchBar
              options={[{ value: 'employee', label: 'Employee name/code' }]}
              placeholder="Search by employee name or code…"
              onSearch={(_by, query) => { setSearch(query); setPage(1) }}
              onClear={() => { setSearch(''); setPage(1) }}
            />
          </div>

          {isError ? (
            <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load attendance records. Please try again.</p>
          ) : (
            <DataTable
              columns={columns}
              data={data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={isLoading}
              emptyTitle="No attendance records found"
              emptySubtext="Try adjusting your filters, or record attendance to get started."
              page={data?.meta.current_page}
              pageCount={data?.meta.last_page}
              totalRows={data?.meta.total}
              onPageChange={setPage}
            />
          )}
        </TabsContent>

        <TabsContent value="qr-locations" className="space-y-6">
          <FilterBar>
            <Select value={selectedBranchId ? String(selectedBranchId) : ''} onValueChange={(value) => setSelectedBranchId(Number(value))}>
              <SelectTrigger className={pillTrigger}>
                <SelectValue placeholder="Branch" />
              </SelectTrigger>
              <SelectContent>
                {branches?.map((branch) => (
                  <SelectItem key={branch.id} value={String(branch.id)}>
                    {branch.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </FilterBar>

          {selectedBranchId && selectedBranch ? (
            <>
              <AttendanceQrCodeSection branchId={selectedBranchId} branchName={selectedBranch.name} />
              <AttendanceGeofencesSection branchId={selectedBranchId} />
            </>
          ) : (
            <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">Select a branch to manage its QR code and geofences.</p>
          )}
        </TabsContent>
      </Tabs>

      <AttendanceFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
