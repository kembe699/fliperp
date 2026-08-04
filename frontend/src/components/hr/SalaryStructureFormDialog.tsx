import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createSalaryStructure, fetchEmployees } from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

export function SalaryStructureFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }), enabled: open })

  const [employeeId, setEmployeeId] = useState<string | null>(null)
  const [basicSalary, setBasicSalary] = useState('')
  const [effectiveDate, setEffectiveDate] = useState('')

  useEffect(() => {
    if (open) {
      setEmployeeId(null)
      setBasicSalary('')
      setEffectiveDate('')
    }
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createSalaryStructure({
        employee_id: Number(employeeId),
        basic_salary: Number(basicSalary),
        effective_date: effectiveDate,
      }),
    onSuccess: () => {
      toast.success('Salary structure created')
      queryClient.invalidateQueries({ queryKey: ['salary-structures'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = employeeId && basicSalary && effectiveDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>New Salary Structure</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Employee</Label>
            <SearchableSelect
              options={(employees?.data ?? []).map((employee) => ({ value: String(employee.id), label: `${employee.first_name} ${employee.last_name}`, sublabel: employee.employee_code }))}
              value={employeeId}
              onChange={setEmployeeId}
              placeholder="Select employee"
            />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Basic Salary</Label>
              <Input type="number" min="0" step="0.01" value={basicSalary} onChange={(event) => setBasicSalary(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Effective Date</Label>
              <Input type="date" value={effectiveDate} onChange={(event) => setEffectiveDate(event.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Salary Structure
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
