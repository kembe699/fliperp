import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Upload, X } from 'lucide-react'

import { createEmployee, deleteEmployeePhoto, fetchDepartments, fetchPositions, updateEmployee, uploadEmployeePhoto } from '@/api/hr'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { Employee, EmploymentType } from '@/types/hr'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { EmployeeAvatar } from '@/components/hr/EmployeeAvatar'

const EMPLOYMENT_TYPES: EmploymentType[] = ['full_time', 'part_time', 'contract']

export function EmployeeFormDialog({
  open,
  onOpenChange,
  employee,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  employee: Employee | null
}) {
  const queryClient = useQueryClient()
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, enabled: open })
  const { data: departments } = useQuery({ queryKey: ['departments'], queryFn: fetchDepartments, enabled: open })
  const { data: positions } = useQuery({ queryKey: ['positions'], queryFn: fetchPositions, enabled: open })

  const [branchId, setBranchId] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [positionTitle, setPositionTitle] = useState('')
  const [employeeCode, setEmployeeCode] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [hireDate, setHireDate] = useState('')
  const [employmentType, setEmploymentType] = useState<EmploymentType>('full_time')

  const fileInputRef = useRef<HTMLInputElement>(null)
  const [photoFile, setPhotoFile] = useState<File | null>(null)
  const [photoFilePreview, setPhotoFilePreview] = useState<string | null>(null)
  const [removeExistingPhoto, setRemoveExistingPhoto] = useState(false)

  useEffect(() => {
    if (!open) return
    setBranchId(employee ? String(employee.branch_id) : '')
    setDepartmentId(employee ? String(employee.department_id) : '')
    setPositionTitle('')
    setEmployeeCode(employee?.employee_code ?? '')
    setFirstName(employee?.first_name ?? '')
    setLastName(employee?.last_name ?? '')
    setPhone(employee?.phone ?? '')
    setEmail(employee?.email ?? '')
    setHireDate(employee?.hire_date ?? new Date().toISOString().slice(0, 10))
    setEmploymentType(employee?.employment_type ?? 'full_time')
    setPhotoFile(null)
    setPhotoFilePreview(null)
    setRemoveExistingPhoto(false)
  }, [open, employee])

  const handlePhotoSelect = (file: File | null) => {
    setPhotoFile(file)
    setPhotoFilePreview(file ? URL.createObjectURL(file) : null)
    setRemoveExistingPhoto(false)
  }

  const clearPhoto = () => {
    setPhotoFile(null)
    setPhotoFilePreview(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
    setRemoveExistingPhoto(true)
  }

  const filteredPositions = positions?.filter((position) => !departmentId || position.department_id === Number(departmentId))

  // Editing: show the employee's current position title once the list is loaded.
  useEffect(() => {
    if (!open || !employee || !positions) return
    setPositionTitle((current) => current || (positions.find((position) => position.id === employee.position_id)?.title ?? ''))
  }, [open, employee, positions])

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        branch_id: Number(branchId),
        department_id: Number(departmentId),
        position_title: positionTitle.trim(),
        employee_code: employeeCode,
        first_name: firstName,
        last_name: lastName,
        phone: phone || null,
        email: email || null,
        hire_date: hireDate,
        employment_type: employmentType,
      }
      const saved = employee ? await updateEmployee(employee.id, payload) : await createEmployee(payload)

      if (photoFile) {
        return uploadEmployeePhoto(saved.id, photoFile)
      }
      if (removeExistingPhoto && employee) {
        return deleteEmployeePhoto(saved.id)
      }

      return saved
    },
    onSuccess: () => {
      toast.success(employee ? 'Employee updated' : 'Employee created')
      queryClient.invalidateQueries({ queryKey: ['employees'] })
      // A typed title may have created a new position; the list shows titles from it.
      queryClient.invalidateQueries({ queryKey: ['positions'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = branchId && departmentId && positionTitle.trim() && employeeCode && firstName && lastName && hireDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{employee ? 'Edit Employee' : 'New Employee'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label>Photo</Label>
            <div className="flex items-center gap-4">
              <EmployeeAvatar
                src={removeExistingPhoto ? null : (photoFilePreview ?? employee?.photo_url)}
                name={firstName || lastName ? `${firstName} ${lastName}` : 'Employee'}
                className="h-16 w-16 text-lg"
              />
              <div className="flex-1 space-y-1.5">
                <div className="flex items-center gap-2">
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(event) => handlePhotoSelect(event.target.files?.[0] ?? null)}
                  />
                  <Button type="button" variant="outline" size="sm" onClick={() => fileInputRef.current?.click()}>
                    <Upload className="h-4 w-4" />
                    Choose Photo
                  </Button>
                  {!removeExistingPhoto && (photoFilePreview || employee?.photo_url) && (
                    <button type="button" onClick={clearPhoto} className="flex items-center gap-1 text-xs text-muted-foreground hover:text-destructive">
                      <X className="h-3 w-3" />
                      Remove
                    </button>
                  )}
                </div>
                <p className="text-xs text-muted-foreground">JPG, PNG or WEBP, max 2MB.</p>
              </div>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label required>First Name</Label>
              <Input value={firstName} onChange={(event) => setFirstName(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label required>Last Name</Label>
              <Input value={lastName} onChange={(event) => setLastName(event.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label required>Employee Code</Label>
              <Input value={employeeCode} onChange={(event) => setEmployeeCode(event.target.value)} placeholder="EMP-006" />
            </div>
            <div className="space-y-1.5">
              <Label required>Branch</Label>
              <Select value={branchId} onValueChange={setBranchId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select branch" />
                </SelectTrigger>
                <SelectContent>
                  {branches?.map((branch) => (
                    <SelectItem key={branch.id} value={String(branch.id)}>
                      {branch.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label required>Department</Label>
              <Select value={departmentId} onValueChange={setDepartmentId}>
                <SelectTrigger>
                  <SelectValue placeholder="Select department" />
                </SelectTrigger>
                <SelectContent>
                  {departments?.map((department) => (
                    <SelectItem key={department.id} value={String(department.id)}>
                      {department.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label required>Position</Label>
              {/* Typed, not picked: a new title becomes a position in the department. */}
              <Input
                value={positionTitle}
                onChange={(event) => setPositionTitle(event.target.value)}
                placeholder="e.g. Accountant"
                list="employee-position-titles"
                maxLength={255}
              />
              <datalist id="employee-position-titles">
                {filteredPositions?.map((position) => (
                  <option key={position.id} value={position.title} />
                ))}
              </datalist>
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Phone</Label>
              <Input value={phone} onChange={(event) => setPhone(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label required>Hire Date</Label>
              <Input type="date" value={hireDate} onChange={(event) => setHireDate(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label required>Employment Type</Label>
              <Select value={employmentType} onValueChange={(value) => setEmploymentType(value as EmploymentType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {EMPLOYMENT_TYPES.map((type) => (
                    <SelectItem key={type} value={type}>
                      {type.replace('_', ' ')}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            {employee ? 'Save Changes' : 'Create Employee'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
