import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createDispatch, fetchVehicles } from '@/api/logistics'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { DispatchSourceModule } from '@/types/logistics'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface Row {
  key: string
  item_description: string
  quantity: number
  unit: string
}

const SOURCE_MODULES: DispatchSourceModule[] = ['procurement', 'sales', 'transfer']

export function DispatchFormDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, enabled: open })
  const { data: vehicles } = useQuery({ queryKey: ['vehicles'], queryFn: fetchVehicles, enabled: open })

  const [branchId, setBranchId] = useState('')
  const [vehicleId, setVehicleId] = useState('none')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [sourceModule, setSourceModule] = useState<DispatchSourceModule>('sales')
  const [dispatchDate, setDispatchDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [rows, setRows] = useState<Row[]>([{ key: crypto.randomUUID(), item_description: '', quantity: 1, unit: '' }])

  useEffect(() => {
    if (open) {
      setBranchId('')
      setVehicleId('none')
      setReferenceNumber('')
      setSourceModule('sales')
      setDispatchDate(new Date().toISOString().slice(0, 10))
      setRows([{ key: crypto.randomUUID(), item_description: '', quantity: 1, unit: '' }])
    }
  }, [open])

  const addRow = () => setRows((prev) => [...prev, { key: crypto.randomUUID(), item_description: '', quantity: 1, unit: '' }])
  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))
  const updateRow = (key: string, patch: Partial<Row>) => setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const mutation = useMutation({
    mutationFn: () =>
      createDispatch({
        branch_id: Number(branchId),
        vehicle_id: vehicleId === 'none' ? null : Number(vehicleId),
        reference_number: referenceNumber,
        source_module: sourceModule,
        dispatch_date: dispatchDate,
        items: rows.filter((row) => row.item_description).map((row) => ({ item_description: row.item_description, quantity: row.quantity, unit: row.unit || null })),
      }),
    onSuccess: (dispatch) => {
      toast.success('Dispatch created')
      queryClient.invalidateQueries({ queryKey: ['dispatches'] })
      onOpenChange(false)
      navigate(`/dispatches/${dispatch.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = branchId && referenceNumber && dispatchDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>New Dispatch</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Reference Number</Label>
              <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="DSP-001" />
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
              <Label>Vehicle</Label>
              <Select value={vehicleId} onValueChange={setVehicleId}>
                <SelectTrigger>
                  <SelectValue placeholder="No vehicle" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No vehicle assigned</SelectItem>
                  {vehicles?.map((vehicle) => (
                    <SelectItem key={vehicle.id} value={String(vehicle.id)}>
                      {vehicle.registration_number}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Source Module</Label>
              <Select value={sourceModule} onValueChange={(value) => setSourceModule(value as DispatchSourceModule)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {SOURCE_MODULES.map((module) => (
                    <SelectItem key={module} value={module}>
                      {module}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Dispatch Date</Label>
            <Input type="date" value={dispatchDate} onChange={(event) => setDispatchDate(event.target.value)} />
          </div>

          <div className="space-y-2">
            <Label>Items</Label>
            {rows.map((row) => (
              <div key={row.key} className="flex items-center gap-2">
                <Input
                  value={row.item_description}
                  onChange={(event) => updateRow(row.key, { item_description: event.target.value })}
                  placeholder="Item description"
                  className="flex-1"
                />
                <Input
                  type="number"
                  min="0"
                  step="0.01"
                  value={row.quantity}
                  onChange={(event) => updateRow(row.key, { quantity: Number(event.target.value) })}
                  className="w-24"
                  placeholder="Qty"
                />
                <Input value={row.unit} onChange={(event) => updateRow(row.key, { unit: event.target.value })} className="w-24" placeholder="Unit" />
                <button type="button" onClick={() => removeRow(row.key)} className="text-muted-foreground hover:text-destructive">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={addRow}>
              <Plus className="h-4 w-4" />
              Add Item
            </Button>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            Create Dispatch
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
