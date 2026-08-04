import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createAssetCategory, deleteAssetCategory, fetchAssetCategories, updateAssetCategory } from '@/api/assets'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { AssetCategory, DepreciationMethod } from '@/types/assets'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const METHODS: DepreciationMethod[] = ['straight_line', 'reducing_balance']

export function AssetCategoriesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: categories, isLoading, isError } = useQuery({ queryKey: ['asset-categories'], queryFn: fetchAssetCategories })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<AssetCategory | null>(null)
  const [name, setName] = useState('')
  const [method, setMethod] = useState<DepreciationMethod>('straight_line')
  const [usefulLife, setUsefulLife] = useState('5')

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setMethod(editing?.depreciation_method ?? 'straight_line')
    setUsefulLife(editing ? String(editing.useful_life_years) : '5')
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, depreciation_method: method, useful_life_years: Number(usefulLife) }
      return editing ? updateAssetCategory(editing.id, payload) : createAssetCategory(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Asset category updated' : 'Asset category created')
      queryClient.invalidateQueries({ queryKey: ['asset-categories'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteAssetCategory,
    onSuccess: () => {
      toast.success('Asset category deleted')
      queryClient.invalidateQueries({ queryKey: ['asset-categories'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<AssetCategory>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'depreciation_method', header: 'Depreciation Method', accessor: (row) => row.depreciation_method.replace('_', ' ') },
    { key: 'useful_life_years', header: 'Useful Life (yrs)', accessor: (row) => row.useful_life_years },
  ]

  const rowActions: (row: AssetCategory) => DataTableRowAction<AssetCategory>[] = (row) => [
    ...(can('asset-categories.update') ? [{ label: 'Edit', onClick: (c: AssetCategory) => { setEditing(c); setFormOpen(true) } }] : []),
    ...(can('asset-categories.delete') ? [{ label: 'Delete', destructive: true, onClick: (c: AssetCategory) => deleteMutation.mutate(c.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Assets"
        title="Asset Categories"
        action={
          can('asset-categories.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Category
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load asset categories. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={categories ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No asset categories found"
          emptySubtext="Create a category to define depreciation rules for your assets."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Category' : 'New Category'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Depreciation Method</Label>
                <Select value={method} onValueChange={(value) => setMethod(value as DepreciationMethod)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {METHODS.map((m) => (
                      <SelectItem key={m} value={m}>
                        {m.replace('_', ' ')}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Useful Life (years)</Label>
                <Input type="number" min="1" value={usefulLife} onChange={(event) => setUsefulLife(event.target.value)} />
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || !usefulLife || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Category'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
