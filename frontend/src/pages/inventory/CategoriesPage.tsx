import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createCategory, deleteCategory, fetchCategories, updateCategory } from '@/api/inventory'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Category } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function CategoriesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: categories, isLoading, isError } = useQuery({ queryKey: ['categories'], queryFn: fetchCategories })

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [name, setName] = useState('')
  const [parentId, setParentId] = useState<string>('none')

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setParentId(editing?.parent_id ? String(editing.parent_id) : 'none')
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, parent_id: parentId === 'none' ? null : Number(parentId) }
      return editing ? updateCategory(editing.id, payload) : createCategory(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Category updated' : 'Category created')
      queryClient.invalidateQueries({ queryKey: ['categories'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteCategory,
    onSuccess: () => {
      toast.success('Category deleted')
      queryClient.invalidateQueries({ queryKey: ['categories'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Category>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'parent_id', header: 'Parent', render: (row) => categories?.find((c) => c.id === row.parent_id)?.name ?? '—' },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: Category) => DataTableRowAction<Category>[] = (row) => [
    ...(can('categories.update') ? [{ label: 'Edit', onClick: (c: Category) => { setEditing(c); setFormOpen(true) } }] : []),
    ...(can('categories.delete') ? [{ label: 'Delete', destructive: true, onClick: (c: Category) => deleteMutation.mutate(c.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Inventory"
        title="Categories"
        action={
          can('categories.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Category
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load categories. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={categories ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No categories found"
          emptySubtext="Create a category to start organizing your products."
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
            <div className="space-y-1.5">
              <Label>Parent Category</Label>
              <Select value={parentId} onValueChange={setParentId}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None (top-level)</SelectItem>
                  {categories?.filter((c) => c.id !== editing?.id).map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                      {category.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Category'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
