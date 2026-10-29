import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createCostCentre, deleteCostCentre, fetchCostCentres, updateCostCentre } from '@/api/grants'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CostCentre } from '@/types/grants'

import { PageHeader } from '@/components/layout/PageHeader'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function CostCentresPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const { data, isLoading, isError } = useQuery({ queryKey: ['cost-centres'], queryFn: () => fetchCostCentres() })
  const centres = data?.data ?? []

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<CostCentre | null>(null)
  const [code, setCode] = useState('')
  const [name, setName] = useState('')

  useEffect(() => {
    if (!formOpen) return
    setCode(editing?.code ?? '')
    setName(editing?.name ?? '')
  }, [formOpen, editing])

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['cost-centres'] })
    queryClient.invalidateQueries({ queryKey: ['dimensions'] })
  }

  const saveMutation = useMutation({
    mutationFn: () => (editing ? updateCostCentre(editing.id, { code, name }) : createCostCentre({ code, name })),
    onSuccess: () => {
      toast.success(editing ? 'Cost centre updated' : 'Cost centre added')
      invalidate()
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const activeMutation = useMutation({
    mutationFn: (centre: CostCentre) => updateCostCentre(centre.id, { is_active: !centre.is_active }),
    onSuccess: (centre) => {
      toast.success(centre.is_active ? 'Cost centre reactivated' : 'Cost centre marked inactive')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteCostCentre,
    onSuccess: () => {
      toast.success('Cost centre deleted')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<CostCentre>[] = [
    { key: 'code', header: 'Code', accessor: (row) => row.code, sortable: true },
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    {
      key: 'is_active',
      header: 'Status',
      accessor: (row) => (row.is_active ? 'Active' : 'Inactive'),
      render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} />,
    },
  ]

  const rowActions = (): DataTableRowAction<CostCentre>[] => [
    ...(can('cost-centres.update')
      ? [
          { label: 'Edit', onClick: (row: CostCentre) => { setEditing(row); setFormOpen(true) } },
          { label: 'Toggle active', onClick: (row: CostCentre) => activeMutation.mutate(row) },
        ]
      : []),
    ...(can('cost-centres.delete') ? [{ label: 'Delete', destructive: true, onClick: (row: CostCentre) => deleteMutation.mutate(row.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Grants"
        title="Cost Centres"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('cost-centres.csv', csvColumnsFromDataTable(columns), centres)} />
            {can('cost-centres.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Cost Centre
              </Button>
            )}
          </div>
        }
      />

      <p className="mb-4 text-sm text-muted-foreground">
        Offices, field sites or functions that transactions are coded to, alongside the SOF, DEA and project. A cost centre that has been used can be made
        inactive but not deleted.
      </p>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load cost centres. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={centres}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No cost centres yet"
          emptySubtext="Add the offices and sites you code transactions to, e.g. CC-JUB Juba office."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Cost Centre' : 'New Cost Centre'}</DialogTitle>
          </DialogHeader>
          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label required>Code</Label>
              <Input value={code} onChange={(event) => setCode(event.target.value)} placeholder="CC-JUB" />
            </div>
            <div className="col-span-2 space-y-1.5">
              <Label required>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="Juba office" />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!code.trim() || !name.trim() || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Add Cost Centre'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
