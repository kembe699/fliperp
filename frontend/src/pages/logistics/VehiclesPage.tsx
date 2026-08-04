import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createVehicle, deleteVehicle, fetchVehicles, updateVehicle } from '@/api/logistics'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Vehicle, VehicleStatus } from '@/types/logistics'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const STATUSES: VehicleStatus[] = ['available', 'in_transit', 'maintenance']
const STATUS_VARIANT: Record<VehicleStatus, 'success' | 'warning' | 'neutral'> = {
  available: 'success',
  in_transit: 'warning',
  maintenance: 'neutral',
}

export function VehiclesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: vehicles, isLoading, isError } = useQuery({ queryKey: ['vehicles'], queryFn: fetchVehicles })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Vehicle | null>(null)
  const [branchId, setBranchId] = useState('')
  const [registrationNumber, setRegistrationNumber] = useState('')
  const [make, setMake] = useState('')
  const [model, setModel] = useState('')
  const [capacity, setCapacity] = useState('')
  const [status, setStatus] = useState<VehicleStatus>('available')

  useEffect(() => {
    if (!formOpen) return
    setBranchId(editing ? String(editing.branch_id) : '')
    setRegistrationNumber(editing?.registration_number ?? '')
    setMake(editing?.make ?? '')
    setModel(editing?.model ?? '')
    setCapacity(editing?.capacity ?? '')
    setStatus(editing?.status ?? 'available')
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { branch_id: Number(branchId), registration_number: registrationNumber, make: make || null, model: model || null, capacity: capacity || null, status }
      return editing ? updateVehicle(editing.id, payload) : createVehicle(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Vehicle updated' : 'Vehicle created')
      queryClient.invalidateQueries({ queryKey: ['vehicles'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteVehicle,
    onSuccess: () => {
      toast.success('Vehicle deleted')
      queryClient.invalidateQueries({ queryKey: ['vehicles'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Vehicle>[] = [
    { key: 'registration_number', header: 'Registration', accessor: (row) => row.registration_number, sortable: true },
    { key: 'make', header: 'Make', accessor: (row) => row.make ?? '—' },
    { key: 'model', header: 'Model', accessor: (row) => row.model ?? '—' },
    { key: 'capacity', header: 'Capacity', accessor: (row) => row.capacity ?? '—' },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={STATUS_VARIANT[row.status]} /> },
  ]

  const rowActions: (row: Vehicle) => DataTableRowAction<Vehicle>[] = (row) => [
    ...(can('vehicles.update') ? [{ label: 'Edit', onClick: (v: Vehicle) => { setEditing(v); setFormOpen(true) } }] : []),
    ...(can('vehicles.delete') ? [{ label: 'Delete', destructive: true, onClick: (v: Vehicle) => deleteMutation.mutate(v.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Logistics"
        title="Vehicles"
        action={
          can('vehicles.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Vehicle
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load vehicles. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={vehicles ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No vehicles found"
          emptySubtext="Register a vehicle to start dispatching deliveries."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Vehicle' : 'New Vehicle'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Registration Number</Label>
                <Input value={registrationNumber} onChange={(event) => setRegistrationNumber(event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>Branch</Label>
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
                <Label>Make</Label>
                <Input value={make} onChange={(event) => setMake(event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>Model</Label>
                <Input value={model} onChange={(event) => setModel(event.target.value)} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Capacity</Label>
                <Input value={capacity} onChange={(event) => setCapacity(event.target.value)} placeholder="e.g. 3 tons" />
              </div>
              <div className="space-y-1.5">
                <Label>Status</Label>
                <Select value={status} onValueChange={(value) => setStatus(value as VehicleStatus)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {STATUSES.map((s) => (
                      <SelectItem key={s} value={s}>
                        {s.replace('_', ' ')}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!branchId || !registrationNumber || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Vehicle'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
