import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, UserPlus, XCircle } from 'lucide-react'

import {
  assignAsset,
  createAssetMaintenanceLog,
  disposeAsset,
  fetchAsset,
  fetchAssetCategories,
  fetchAssetDepreciationSchedules,
  fetchAssetMaintenanceLogs,
} from '@/api/assets'
import { fetchEmployees } from '@/api/hr'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { AssetStatus } from '@/types/assets'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

const STATUS_VARIANT: Record<AssetStatus, 'success' | 'neutral' | 'warning' | 'danger'> = {
  in_use: 'success',
  in_storage: 'neutral',
  under_maintenance: 'warning',
  disposed: 'danger',
}

export function AssetDetailPage() {
  const { id } = useParams<{ id: string }>()
  const assetId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [assignOpen, setAssignOpen] = useState(false)
  const [employeeId, setEmployeeId] = useState<string | null>(null)

  const [logOpen, setLogOpen] = useState(false)
  const [maintenanceDate, setMaintenanceDate] = useState('')
  const [description, setDescription] = useState('')
  const [cost, setCost] = useState('')

  const { data: asset, isLoading } = useQuery({ queryKey: ['asset', assetId], queryFn: () => fetchAsset(assetId), enabled: !!assetId })
  const { data: categories } = useQuery({ queryKey: ['asset-categories'], queryFn: fetchAssetCategories })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })
  const { data: schedules } = useQuery({ queryKey: ['asset-depreciation', assetId], queryFn: () => fetchAssetDepreciationSchedules(assetId), enabled: !!assetId })
  const { data: logs } = useQuery({ queryKey: ['asset-maintenance', assetId], queryFn: () => fetchAssetMaintenanceLogs(assetId), enabled: !!assetId })

  const invalidateAsset = () => {
    queryClient.invalidateQueries({ queryKey: ['asset', assetId] })
    queryClient.invalidateQueries({ queryKey: ['assets'] })
  }

  const assignMutation = useMutation({
    mutationFn: () => assignAsset(assetId, Number(employeeId)),
    onSuccess: () => {
      toast.success('Asset assigned')
      invalidateAsset()
      setAssignOpen(false)
      setEmployeeId(null)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const disposeMutation = useMutation({
    mutationFn: () => disposeAsset(assetId),
    onSuccess: () => {
      toast.success('Asset disposed')
      invalidateAsset()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const logMutation = useMutation({
    mutationFn: () => createAssetMaintenanceLog(assetId, { maintenance_date: maintenanceDate, description, cost: cost ? Number(cost) : null }),
    onSuccess: () => {
      toast.success('Maintenance log recorded')
      queryClient.invalidateQueries({ queryKey: ['asset-maintenance', assetId] })
      setLogOpen(false)
      setMaintenanceDate('')
      setDescription('')
      setCost('')
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !asset) {
    return <div className="p-6 text-sm text-muted-foreground">Loading asset…</div>
  }

  const assignedEmployeeName = asset.assigned_to_employee_id
    ? (() => {
        const employee = employees?.data.find((e) => e.id === asset.assigned_to_employee_id)
        return employee ? `${employee.first_name} ${employee.last_name}` : `#${asset.assigned_to_employee_id}`
      })()
    : null

  const canAssign = can('assets.assign') && asset.status !== 'disposed'
  const canDispose = can('assets.dispose') && asset.status !== 'disposed'

  const scheduleColumns: DataTableColumn<NonNullable<typeof schedules>[number]>[] = [
    { key: 'period_date', header: 'Period', accessor: (row) => row.period_date, render: (row) => formatDate(row.period_date) },
    { key: 'depreciation_amount', header: 'Depreciation', render: (row) => formatCurrency(row.depreciation_amount) },
    { key: 'accumulated_depreciation', header: 'Accumulated', render: (row) => formatCurrency(row.accumulated_depreciation) },
    { key: 'book_value', header: 'Book Value', render: (row) => formatCurrency(row.book_value) },
  ]

  const logColumns: DataTableColumn<NonNullable<typeof logs>[number]>[] = [
    { key: 'maintenance_date', header: 'Date', accessor: (row) => row.maintenance_date, render: (row) => formatDate(row.maintenance_date) },
    { key: 'description', header: 'Description', accessor: (row) => row.description },
    { key: 'cost', header: 'Cost', render: (row) => formatCurrency(row.cost) },
    { key: 'performed_by', header: 'Performed By', accessor: (row) => row.performed_by ?? '—' },
  ]

  return (
    <div>
      <PageHeader
        parent="Assets"
        title={asset.name}
        action={
          <div className="flex gap-2">
            {canAssign && (
              <Button variant="outline" onClick={() => setAssignOpen(true)}>
                <UserPlus className="h-4 w-4" />
                Assign
              </Button>
            )}
            {canDispose && (
              <Button variant="outline" className="text-destructive" disabled={disposeMutation.isPending} onClick={() => disposeMutation.mutate()}>
                <XCircle className="h-4 w-4" />
                Dispose
              </Button>
            )}
          </div>
        }
      />

      <Card className="mb-6">
        <CardContent className="grid grid-cols-2 gap-6 p-6 sm:grid-cols-4">
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Asset Code</p>
            <p className="mt-1 text-sm font-medium text-foreground">{asset.asset_code}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Category</p>
            <p className="mt-1 text-sm text-foreground">{categories?.find((c) => c.id === asset.asset_category_id)?.name ?? '—'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Purchase Cost</p>
            <p className="mt-1 text-sm text-foreground">{formatCurrency(asset.purchase_cost)}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Current Value</p>
            <p className="mt-1 text-lg font-bold text-primary">{formatCurrency(asset.current_value)}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
            <StatusBadge label={asset.status.replace('_', ' ')} variant={STATUS_VARIANT[asset.status]} />
          </div>
          <div>
            <p className="text-xs font-semibold uppercase text-muted-foreground">Assigned To</p>
            <p className="mt-1 text-sm text-foreground">{assignedEmployeeName ?? 'Unassigned'}</p>
          </div>
        </CardContent>
      </Card>

      <div className="mb-6">
        <p className="mb-3 text-sm font-semibold text-foreground">Depreciation Schedule</p>
        <DataTable columns={scheduleColumns} data={schedules ?? []} rowKey={(row) => row.id} emptyTitle="No depreciation recorded" emptySubtext="Run depreciation from the Assets list to generate a schedule." />
      </div>

      <div>
        <div className="mb-3 flex items-center justify-between">
          <p className="text-sm font-semibold text-foreground">Maintenance Logs</p>
          {can('assets.update') && (
            <Button size="sm" onClick={() => setLogOpen(true)}>
              <Plus className="h-4 w-4" />
              Add Log
            </Button>
          )}
        </div>
        <DataTable columns={logColumns} data={logs ?? []} rowKey={(row) => row.id} emptyTitle="No maintenance logs" emptySubtext="No maintenance has been recorded for this asset yet." />
      </div>

      <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Assign Asset</DialogTitle>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label>Employee</Label>
            <SearchableSelect
              options={(employees?.data ?? []).map((employee) => ({ value: String(employee.id), label: `${employee.first_name} ${employee.last_name}`, sublabel: employee.employee_code }))}
              value={employeeId}
              onChange={setEmployeeId}
              placeholder="Select employee"
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setAssignOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!employeeId || assignMutation.isPending} onClick={() => assignMutation.mutate()}>
              Assign
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={logOpen} onOpenChange={setLogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Maintenance Log</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Date</Label>
              <Input type="date" value={maintenanceDate} onChange={(event) => setMaintenanceDate(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Description</Label>
              <Input value={description} onChange={(event) => setDescription(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Cost</Label>
              <Input type="number" min="0" step="0.01" value={cost} onChange={(event) => setCost(event.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setLogOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!maintenanceDate || !description || logMutation.isPending} onClick={() => logMutation.mutate()}>
              Add Log
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
