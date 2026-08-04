import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { fetchCategories, fetchProducts, fetchStockLevels, updateProduct } from '@/api/inventory'
import { formatCurrency } from '@/lib/currency'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { Product } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar, FilterPill } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { ProductFormDialog } from '@/components/inventory/ProductFormDialog'
import { ProductImage } from '@/components/inventory/ProductImage'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function ProductsListPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [categoryId, setCategoryId] = useState('all')
  const [activeStatus, setActiveStatus] = useState('all')
  const [lowStockOnly, setLowStockOnly] = useState(false)
  const [search, setSearch] = useState('')

  const [formOpen, setFormOpen] = useState(false)
  const [editingProduct, setEditingProduct] = useState<Product | null>(null)

  const { data: categories } = useQuery({ queryKey: ['categories'], queryFn: fetchCategories })
  const { data: stockLevels } = useQuery({ queryKey: ['stock-levels-all'], queryFn: () => fetchStockLevels() })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['products', page, categoryId, activeStatus, lowStockOnly, search],
    queryFn: () =>
      fetchProducts({
        page,
        per_page: 15,
        category_id: categoryId === 'all' ? undefined : Number(categoryId),
        is_active: activeStatus === 'all' ? undefined : activeStatus === 'active',
        low_stock: lowStockOnly || undefined,
        search: search || undefined,
      }),
  })

  const stockByProduct = useMemo(() => {
    const map = new Map<number, number>()
    stockLevels?.forEach((level) => map.set(level.product_id, (map.get(level.product_id) ?? 0) + level.quantity_on_hand))
    return map
  }, [stockLevels])

  const deactivateMutation = useMutation({
    mutationFn: (product: Product) => updateProduct(product.id, { is_active: false }),
    onSuccess: () => {
      toast.success('Product deactivated')
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
    onError: () => toast.error('Could not deactivate this product'),
  })

  const columns: DataTableColumn<Product>[] = [
    { key: 'image', header: '', render: (row) => <ProductImage src={row.image_url} alt={row.name} />, className: 'w-12' },
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'sku', header: 'SKU', accessor: (row) => row.sku },
    { key: 'category_id', header: 'Category', render: (row) => categories?.find((c) => c.id === row.category_id)?.name ?? '—' },
    { key: 'cost_price', header: 'Cost Price', accessor: (row) => row.cost_price, render: (row) => formatCurrency(row.cost_price) },
    { key: 'selling_price', header: 'Selling Price', accessor: (row) => row.selling_price, render: (row) => formatCurrency(row.selling_price) },
    {
      key: 'stock',
      header: 'Stock on Hand',
      render: (row) => (row.track_inventory ? (stockByProduct.get(row.id) ?? 0) : 'Not tracked'),
    },
    {
      key: 'is_active',
      header: 'Status',
      render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} />,
    },
  ]

  const rowActions: (row: Product) => DataTableRowAction<Product>[] = (row) => [
    ...(can('products.update') ? [{ label: 'Edit', onClick: (p: Product) => { setEditingProduct(p); setFormOpen(true) } }] : []),
    { label: 'View Movements', onClick: (p: Product) => navigate(`/products/${p.id}`) },
    ...(can('products.update') && row.is_active
      ? [{ label: 'Deactivate', destructive: true, onClick: (p: Product) => deactivateMutation.mutate(p) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Inventory"
        title="Products"
        action={
          <div className="flex gap-2">
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchProducts({
                  per_page: 10000,
                  category_id: categoryId === 'all' ? undefined : Number(categoryId),
                  is_active: activeStatus === 'all' ? undefined : activeStatus === 'active',
                  low_stock: lowStockOnly || undefined,
                  search: search || undefined,
                })
                exportToCsv('products.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('products.create') && (
              <Button onClick={() => { setEditingProduct(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Product
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={categoryId} onValueChange={(value) => { setCategoryId(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Category" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All categories</SelectItem>
            {categories?.map((category) => (
              <SelectItem key={category.id} value={String(category.id)}>
                {category.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select value={activeStatus} onValueChange={(value) => { setActiveStatus(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>

        <FilterPill
          label="Low Stock Only"
          withChevron={false}
          active={lowStockOnly}
          onClick={() => { setLowStockOnly((prev) => !prev); setPage(1) }}
        />
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[
            { value: 'name', label: 'Name' },
            { value: 'sku', label: 'SKU' },
            { value: 'barcode', label: 'Barcode' },
          ]}
          placeholder="Search products…"
          onSearch={(_by, query) => { setSearch(query); setPage(1) }}
          onClear={() => { setSearch(''); setPage(1) }}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load products. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No products found"
          emptySubtext="Try adjusting your filters, or create a new product."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <ProductFormDialog open={formOpen} onOpenChange={setFormOpen} product={editingProduct} />
    </div>
  )
}
