import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'

import { fetchProduct, fetchStockLevels } from '@/api/inventory'
import { fetchProductStockMovements } from '@/api/inventory'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { MOVEMENT_TYPE_VARIANT } from '@/components/sales/inventory-status-variants'
import type { StockMovement } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ProductImage } from '@/components/inventory/ProductImage'

export function ProductDetailPage() {
  const { id } = useParams<{ id: string }>()
  const productId = Number(id)
  const [page, setPage] = useState(1)

  const { data: product, isLoading } = useQuery({ queryKey: ['product', productId], queryFn: () => fetchProduct(productId), enabled: !!productId })
  const { data: stockLevels } = useQuery({ queryKey: ['stock-levels-product', productId], queryFn: () => fetchStockLevels({ product_id: productId }), enabled: !!productId })
  const { data: movements, isLoading: movementsLoading } = useQuery({
    queryKey: ['product-movements', productId, page],
    queryFn: () => fetchProductStockMovements(productId, page),
    enabled: !!productId,
  })

  const totalOnHand = stockLevels?.reduce((sum, level) => sum + level.quantity_on_hand, 0) ?? 0

  const columns: DataTableColumn<StockMovement>[] = [
    { key: 'moved_at', header: 'Date', accessor: (row) => row.moved_at, sortable: true, render: (row) => formatDate(row.moved_at) },
    {
      key: 'movement_type',
      header: 'Type',
      render: (row) => <StatusBadge label={row.movement_type.replace(/_/g, ' ')} variant={MOVEMENT_TYPE_VARIANT[row.movement_type] ?? 'neutral'} />,
    },
    {
      key: 'quantity',
      header: 'Quantity',
      accessor: (row) => row.quantity,
      render: (row) => <span className={row.quantity < 0 ? 'text-danger' : 'text-success'}>{row.quantity > 0 ? '+' : ''}{row.quantity}</span>,
    },
    { key: 'warehouse_name', header: 'Warehouse', accessor: (row) => row.warehouse_name },
    { key: 'reference_type', header: 'Reference', render: (row) => (row.reference_type ? `${row.reference_type.split('\\').pop()} #${row.reference_id}` : row.reason ?? '—') },
  ]

  if (isLoading || !product) {
    return <div className="p-6 text-sm text-muted-foreground">Loading product…</div>
  }

  return (
    <div>
      <PageHeader parent="Products" title={product.name} />

      <Card className="mb-6">
        <CardContent className="flex gap-6 p-6">
          <ProductImage src={product.image_url} alt={product.name} className="h-24 w-24 shrink-0" />
          <div className="grid flex-1 grid-cols-2 gap-6 sm:grid-cols-4">
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">SKU</p>
              <p className="mt-1 text-sm font-medium text-foreground">{product.sku}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Cost Price</p>
              <p className="mt-1 text-sm font-medium text-foreground">{formatCurrency(product.cost_price)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Selling Price</p>
              <p className="mt-1 text-sm font-medium text-foreground">{formatCurrency(product.selling_price)}</p>
            </div>
            <div>
              <p className="text-xs font-semibold uppercase text-muted-foreground">Total Stock on Hand</p>
              <p className="mt-1 text-lg font-bold text-primary">{product.track_inventory ? totalOnHand : 'Not tracked'}</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {product.track_inventory && stockLevels && stockLevels.length > 0 && (
        <Card className="mb-6">
          <CardContent className="p-6">
            <p className="mb-3 text-sm font-semibold text-foreground">Stock by Warehouse</p>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
              {stockLevels.map((level) => (
                <div key={level.id} className="rounded-lg border border-border p-3">
                  <p className="text-xs text-muted-foreground">{level.warehouse_name}</p>
                  <p className="text-lg font-semibold text-foreground">{level.quantity_on_hand}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      <p className="mb-3 text-sm font-semibold text-foreground">Stock Movement History</p>
      <DataTable
        columns={columns}
        data={movements?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={movementsLoading}
        emptyTitle="No stock movements"
        emptySubtext="Movements will appear here once this product is sold, purchased or adjusted."
        page={movements?.meta.current_page}
        pageCount={movements?.meta.last_page}
        totalRows={movements?.meta.total}
        onPageChange={setPage}
      />
    </div>
  )
}
