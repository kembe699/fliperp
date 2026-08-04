import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createWarehouse, deleteWarehouse, fetchWarehouses, updateWarehouse } from '@/api/inventory'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Warehouse } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function WarehousesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: warehouses, isLoading, isError } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Warehouse | null>(null)
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [branchId, setBranchId] = useState('')
  const [isDefault, setIsDefault] = useState(false)

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setCode(editing?.code ?? '')
    setBranchId(editing?.branch_id ? String(editing.branch_id) : '')
    setIsDefault(editing?.is_default ?? false)
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, code, branch_id: Number(branchId), is_default: isDefault }
      return editing ? updateWarehouse(editing.id, payload) : createWarehouse(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Warehouse updated' : 'Warehouse created')
      queryClient.invalidateQueries({ queryKey: ['warehouses'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteWarehouse,
    onSuccess: () => {
      toast.success('Warehouse deleted')
      queryClient.invalidateQueries({ queryKey: ['warehouses'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Warehouse>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'code', header: 'Code', accessor: (row) => row.code },
    { key: 'branch_id', header: 'Branch', render: (row) => branches?.find((b) => b.id === row.branch_id)?.name ?? '—' },
    { key: 'is_default', header: 'Default', render: (row) => (row.is_default ? <StatusBadge label="Default" variant="info" /> : '—') },
  ]

  const rowActions: (row: Warehouse) => DataTableRowAction<Warehouse>[] = (row) => [
    ...(can('warehouses.update') ? [{ label: 'Edit', onClick: (w: Warehouse) => { setEditing(w); setFormOpen(true) } }] : []),
    ...(can('warehouses.delete') ? [{ label: 'Delete', destructive: true, onClick: (w: Warehouse) => deleteMutation.mutate(w.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Inventory"
        title="Warehouses"
        action={
          can('warehouses.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Warehouse
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load warehouses. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={warehouses ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No warehouses found"
          emptySubtext="Create a warehouse to start tracking stock."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Warehouse' : 'New Warehouse'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Name</Label>
                <Input value={name} onChange={(event) => setName(event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label>Code</Label>
                <Input value={code} onChange={(event) => setCode(event.target.value)} />
              </div>
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
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" checked={isDefault} onChange={(event) => setIsDefault(event.target.checked)} className="h-4 w-4 rounded border-input" />
              Default warehouse for this branch
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || !code || !branchId || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Warehouse'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
