import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createCategory, deleteCategory, fetchCategories, updateCategory } from '@/api/inventory'
import { fetchChartOfAccounts } from '@/api/reports'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { ChartOfAccount, ChartOfAccountType } from '@/types/accounting'
import type { Category } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

// The accounts a category's products post to. Left on "Default", a product
// posts to the standard chart code shown.
const ACCOUNT_FIELDS: { key: 'revenue_account_id' | 'cogs_account_id' | 'inventory_account_id'; label: string; type: ChartOfAccountType; fallback: string }[] = [
  { key: 'revenue_account_id', label: 'Revenue Account', type: 'revenue', fallback: '4000' },
  { key: 'cogs_account_id', label: 'Cost of Sales Account', type: 'expense', fallback: '5300' },
  { key: 'inventory_account_id', label: 'Inventory Account', type: 'asset', fallback: '1200' },
]
const DEFAULT_ACCOUNT = 'default'

type AccountChoice = Record<(typeof ACCOUNT_FIELDS)[number]['key'], string>
const accountLabel = (account?: ChartOfAccount) => (account ? `${account.code} · ${account.name}` : '—')

export function CategoriesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: categories, isLoading, isError } = useQuery({ queryKey: ['categories'], queryFn: fetchCategories })
  const { data: accounts = [] } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts })

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Category | null>(null)
  const [name, setName] = useState('')
  const [parentId, setParentId] = useState<string>('none')
  const [accountChoice, setAccountChoice] = useState<AccountChoice>({ revenue_account_id: DEFAULT_ACCOUNT, cogs_account_id: DEFAULT_ACCOUNT, inventory_account_id: DEFAULT_ACCOUNT })

  const filtered = useMemo(() => {
    return (categories ?? [])
      .filter((row) => status === 'all' || (status === 'active' ? row.is_active : !row.is_active))
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [categories, status, search])

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setParentId(editing?.parent_id ? String(editing.parent_id) : 'none')
    setAccountChoice({
      revenue_account_id: editing?.revenue_account_id ? String(editing.revenue_account_id) : DEFAULT_ACCOUNT,
      cogs_account_id: editing?.cogs_account_id ? String(editing.cogs_account_id) : DEFAULT_ACCOUNT,
      inventory_account_id: editing?.inventory_account_id ? String(editing.inventory_account_id) : DEFAULT_ACCOUNT,
    })
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const toId = (value: string) => (value === DEFAULT_ACCOUNT ? null : Number(value))
      const payload = {
        name,
        parent_id: parentId === 'none' ? null : Number(parentId),
        revenue_account_id: toId(accountChoice.revenue_account_id),
        cogs_account_id: toId(accountChoice.cogs_account_id),
        inventory_account_id: toId(accountChoice.inventory_account_id),
      }
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

  const defaultLabel = (code: string) => {
    const account = accounts.find((a) => a.code === code)
    return account ? accountLabel(account) : code
  }

  const columns: DataTableColumn<Category>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'parent_id', header: 'Parent', render: (row) => categories?.find((c) => c.id === row.parent_id)?.name ?? '—' },
    ...ACCOUNT_FIELDS.map((field) => ({
      key: field.key,
      header: field.label.replace(' Account', ''),
      accessor: (row: Category) => {
        const id = row[field.key]
        return id ? accountLabel(accounts.find((a) => a.id === id)) : `Default (${defaultLabel(field.fallback)})`
      },
    })),
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
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('categories.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('categories.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Category
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search categories…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load categories. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
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
              <Label required>Name</Label>
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
            <div className="space-y-3 rounded-lg border border-border p-3">
              <p className="text-xs text-muted-foreground">
                Sales, cost of sales and stock for every product or service in this category post to these accounts, so the finance reports show them on
                their own lines. Leave one on Default to use the standard account.
              </p>
              {ACCOUNT_FIELDS.map((field) => (
                <div key={field.key} className="space-y-1.5">
                  <Label>{field.label}</Label>
                  <Select value={accountChoice[field.key]} onValueChange={(value) => setAccountChoice((current) => ({ ...current, [field.key]: value }))}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={DEFAULT_ACCOUNT}>
                        Default ({defaultLabel(field.fallback)})
                      </SelectItem>
                      {accounts
                        .filter((account) => account.type === field.type && (account.is_active || String(account.id) === accountChoice[field.key]))
                        .map((account) => (
                          <SelectItem key={account.id} value={String(account.id)}>
                            {accountLabel(account)}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                </div>
              ))}
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
