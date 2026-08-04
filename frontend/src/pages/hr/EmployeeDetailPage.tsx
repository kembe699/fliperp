import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import {
  createSalaryStructure,
  fetchAttendance,
  fetchDepartments,
  fetchEmployee,
  fetchEmployeeContracts,
  fetchLeaveRequests,
  fetchLeaveTypes,
  fetchPositions,
  fetchSalaryStructures,
} from '@/api/hr'
import { formatCurrency, formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { downloadPdf } from '@/lib/pdf-download'
import { usePermissions } from '@/hooks/use-permissions'
import type { EmployeeContract } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { EmployeeAvatar } from '@/components/hr/EmployeeAvatar'
import { ContractFlowDialog } from '@/components/hr/ContractFlowDialog'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const CONTRACT_STATUS_VARIANT: Record<EmployeeContract['status'], 'neutral' | 'info' | 'success'> = {
  draft: 'neutral',
  generated: 'info',
  signed: 'success',
}

export function EmployeeDetailPage() {
  const { id } = useParams<{ id: string }>()
  const employeeId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [contractFlowOpen, setContractFlowOpen] = useState(false)
  const [resumeContract, setResumeContract] = useState<EmployeeContract | null>(null)

  const [salaryOpen, setSalaryOpen] = useState(false)
  const [basicSalary, setBasicSalary] = useState('')
  const [effectiveDate, setEffectiveDate] = useState('')

  const { data: employee, isLoading } = useQuery({ queryKey: ['employee', employeeId], queryFn: () => fetchEmployee(employeeId), enabled: !!employeeId })
  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments })
  const { data: positions } = useQuery({ queryKey: ['positions'], queryFn: fetchPositions })
  const { data: contracts } = useQuery({ queryKey: ['employee-contracts', employeeId], queryFn: () => fetchEmployeeContracts(employeeId), enabled: !!employeeId })
  const { data: attendance } = useQuery({
    queryKey: ['attendance', employeeId],
    queryFn: () => fetchAttendance({ employee_id: employeeId, per_page: 20 }),
    enabled: !!employeeId,
  })
  const { data: leaveRequests } = useQuery({
    queryKey: ['leave-requests', employeeId],
    queryFn: () => fetchLeaveRequests({ employee_id: employeeId, per_page: 100 }),
    enabled: !!employeeId,
  })
  const { data: leaveTypes } = useQuery({ queryKey: ['leave-types'], queryFn: fetchLeaveTypes })
  const { data: salaryHistory } = useQuery({
    queryKey: ['salary-structures', employeeId],
    queryFn: () => fetchSalaryStructures({ employee_id: employeeId, per_page: 50 }),
    enabled: !!employeeId,
  })

  const salaryMutation = useMutation({
    mutationFn: () =>
      createSalaryStructure({
        employee_id: employeeId,
        basic_salary: Number(basicSalary),
        effective_date: effectiveDate,
      }),
    onSuccess: () => {
      toast.success('Salary structure added')
      queryClient.invalidateQueries({ queryKey: ['salary-structures', employeeId] })
      setSalaryOpen(false)
      setBasicSalary('')
      setEffectiveDate('')
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !employee) {
    return <div className="p-6 text-sm text-muted-foreground">Loading employee…</div>
  }

  const currentYear = new Date().getFullYear()
  const leaveBalances = (leaveTypes ?? []).map((leaveType) => {
    const usedDays = (leaveRequests?.data ?? [])
      .filter((lr) => lr.leave_type_id === leaveType.id && lr.status === 'approved' && new Date(lr.start_date).getFullYear() === currentYear)
      .reduce((sum, lr) => sum + lr.days_count, 0)
    return { leaveType, usedDays, remaining: Math.max(leaveType.days_per_year - usedDays, 0) }
  })

  const contractColumns: DataTableColumn<EmployeeContract>[] = [
    { key: 'contract_type', header: 'Type', accessor: (row) => row.contract_type },
    { key: 'start_date', header: 'Start', accessor: (row) => row.start_date, render: (row) => formatDate(row.start_date) },
    { key: 'end_date', header: 'End', render: (row) => (row.end_date ? formatDate(row.end_date) : 'Ongoing') },
    { key: 'base_salary', header: 'Base Salary', render: (row) => formatCurrency(row.base_salary) },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status} variant={CONTRACT_STATUS_VARIANT[row.status]} />,
    },
  ]

  const contractRowActions: (row: EmployeeContract) => DataTableRowAction<EmployeeContract>[] = (row) => [
    ...(row.document_url
      ? [{ label: 'View / Download PDF', onClick: (c: EmployeeContract) => downloadPdf(`/employee-contracts/${c.id}/pdf`, `contract-${employee?.last_name ?? c.id}.pdf`) }]
      : []),
    ...(row.status !== 'signed' && can('employee-contracts.update')
      ? [{ label: 'Continue', onClick: (c: EmployeeContract) => { setResumeContract(c); setContractFlowOpen(true) } }]
      : []),
  ]

  const attendanceColumns: DataTableColumn<NonNullable<typeof attendance>['data'][number]>[] = [
    { key: 'date', header: 'Date', accessor: (row) => row.date, render: (row) => formatDate(row.date) },
    { key: 'clock_in', header: 'Clock In', accessor: (row) => row.clock_in ?? '—' },
    { key: 'clock_out', header: 'Clock Out', accessor: (row) => row.clock_out ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={row.status === 'present' ? 'success' : row.status === 'absent' ? 'danger' : 'warning'} /> },
  ]

  const salaryColumns: DataTableColumn<NonNullable<typeof salaryHistory>['data'][number]>[] = [
    { key: 'effective_date', header: 'Effective Date', accessor: (row) => row.effective_date, render: (row) => formatDate(row.effective_date) },
    { key: 'basic_salary', header: 'Basic Salary', render: (row) => formatCurrency(row.basic_salary) },
    { key: 'allowances', header: 'Allowances', render: (row) => formatCurrency(Object.values(row.allowances ?? {}).reduce((a, b) => a + b, 0)) },
  ]

  return (
    <div>
      <PageHeader parent="Employees" title={`${employee.first_name} ${employee.last_name}`} />

      <Card className="mb-6">
        <CardContent className="flex items-center gap-6 p-6">
          <EmployeeAvatar src={employee.photo_url} name={`${employee.first_name} ${employee.last_name}`} className="h-16 w-16 text-lg" />
          <div className="grid flex-1 grid-cols-2 gap-6 sm:grid-cols-4">
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Employee Code</p>
            <p className="mt-1 text-sm font-medium text-foreground">{employee.employee_code}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Department</p>
            <p className="mt-1 text-sm text-foreground">{departments?.find((d) => d.id === employee.department_id)?.name ?? '—'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Position</p>
            <p className="mt-1 text-sm text-foreground">{positions?.find((p) => p.id === employee.position_id)?.title ?? '—'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
            <p className="mt-1 text-sm text-foreground">{employee.status.replace('_', ' ')}</p>
          </div>
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="contracts">
        <TabsList>
          <TabsTrigger value="contracts">Contracts</TabsTrigger>
          <TabsTrigger value="attendance">Attendance</TabsTrigger>
          <TabsTrigger value="leave">Leave Balance</TabsTrigger>
          <TabsTrigger value="salary">Salary History</TabsTrigger>
        </TabsList>

        <TabsContent value="contracts">
          <div className="mb-3 flex justify-end">
            {can('employee-contracts.create') && (
              <Button size="sm" onClick={() => { setResumeContract(null); setContractFlowOpen(true) }}>
                <Plus className="h-4 w-4" />
                Add Contract
              </Button>
            )}
          </div>
          <DataTable
            columns={contractColumns}
            data={contracts ?? []}
            rowKey={(row) => row.id}
            rowActions={contractRowActions}
            emptyTitle="No contracts"
            emptySubtext="No contracts recorded for this employee yet."
          />
        </TabsContent>

        <TabsContent value="attendance">
          <DataTable columns={attendanceColumns} data={attendance?.data ?? []} rowKey={(row) => row.id} emptyTitle="No attendance records" emptySubtext="No attendance has been recorded for this employee yet." />
        </TabsContent>

        <TabsContent value="leave">
          <Card>
            <CardContent className="p-6">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                    <th className="py-2">Leave Type</th>
                    <th className="py-2 text-right">Days/Year</th>
                    <th className="py-2 text-right">Used ({currentYear})</th>
                    <th className="py-2 text-right">Remaining</th>
                  </tr>
                </thead>
                <tbody>
                  {leaveBalances.map(({ leaveType, usedDays, remaining }) => (
                    <tr key={leaveType.id} className="border-b border-border last:border-b-0">
                      <td className="py-2 text-foreground">{leaveType.name}</td>
                      <td className="py-2 text-right text-foreground">{leaveType.days_per_year}</td>
                      <td className="py-2 text-right text-foreground">{usedDays}</td>
                      <td className="py-2 text-right font-medium text-foreground">{remaining}</td>
                    </tr>
                  ))}
                  {leaveBalances.length === 0 && (
                    <tr>
                      <td colSpan={4} className="py-6 text-center text-muted-foreground">
                        No leave types configured.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="salary">
          <div className="mb-3 flex justify-end">
            {can('salary-structures.create') && (
              <Button size="sm" onClick={() => setSalaryOpen(true)}>
                <Plus className="h-4 w-4" />
                Add Salary Structure
              </Button>
            )}
          </div>
          <DataTable columns={salaryColumns} data={salaryHistory?.data ?? []} rowKey={(row) => row.id} emptyTitle="No salary history" emptySubtext="No salary structures recorded for this employee yet." />
        </TabsContent>
      </Tabs>

      {employee && (
        <ContractFlowDialog
          open={contractFlowOpen}
          onOpenChange={setContractFlowOpen}
          employee={employee}
          resumeContract={resumeContract}
        />
      )}

      <Dialog open={salaryOpen} onOpenChange={setSalaryOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Salary Structure</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Basic Salary</Label>
              <Input type="number" min="0" step="0.01" value={basicSalary} onChange={(event) => setBasicSalary(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Effective Date</Label>
              <Input type="date" value={effectiveDate} onChange={(event) => setEffectiveDate(event.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSalaryOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!basicSalary || !effectiveDate || salaryMutation.isPending} onClick={() => salaryMutation.mutate()}>
              Add Salary Structure
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
