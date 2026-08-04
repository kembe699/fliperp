import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'

import { fetchActiveProducts, fetchCategories, fetchStockLevels } from '@/api/products'
import { formatCurrency } from '@/lib/currency'
import { cn } from '@/lib/utils'

import { Input } from '@/components/ui/input'
import { EmptyState } from '@/components/shared/EmptyState'
import { Skeleton } from '@/components/ui/skeleton'
import { ProductImage } from '@/components/inventory/ProductImage'

interface ProductGridProps {
  warehouseId: number | null
  onAddProduct: (productId: number) => void
}

export function ProductGrid({ warehouseId, onAddProduct }: ProductGridProps) {
  const [search, setSearch] = useState('')
  const [categoryId, setCategoryId] = useState<number | null>(null)

  const { data: products, isLoading } = useQuery({ queryKey: ['pos-products'], queryFn: () => fetchActiveProducts() })
  const { data: categories } = useQuery({ queryKey: ['pos-categories'], queryFn: fetchCategories })
  const { data: stockLevels } = useQuery({
    queryKey: ['pos-stock-levels', warehouseId],
    queryFn: () => fetchStockLevels(warehouseId ?? undefined),
    enabled: !!warehouseId,
  })

  const stockByProduct = useMemo(() => {
    const map = new Map<number, number>()
    stockLevels?.forEach((level) => map.set(level.product_id, level.quantity_on_hand))
    return map
  }, [stockLevels])

  const filteredProducts = useMemo(() => {
    return (products ?? [])
      .filter((product) => product.is_active)
      .filter((product) => (categoryId ? product.category_id === categoryId : true))
      .filter((product) =>
        search
          ? product.name.toLowerCase().includes(search.toLowerCase()) || product.sku.toLowerCase().includes(search.toLowerCase())
          : true,
      )
  }, [products, categoryId, search])

  return (
    <div className="flex h-full flex-col">
      <div className="mb-3 space-y-3">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search products by name or SKU…"
            className="pl-9"
          />
        </div>

        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setCategoryId(null)}
            className={cn(
              'rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
              categoryId === null ? 'border-primary bg-accent text-primary' : 'border-border bg-card text-muted-foreground',
            )}
          >
            All
          </button>
          {categories?.map((category) => (
            <button
              key={category.id}
              type="button"
              onClick={() => setCategoryId(category.id)}
              className={cn(
                'rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors',
                categoryId === category.id ? 'border-primary bg-accent text-primary' : 'border-border bg-card text-muted-foreground',
              )}
            >
              {category.name}
            </button>
          ))}
        </div>
      </div>

      <div className="flex-1 overflow-y-auto">
        {isLoading ? (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {Array.from({ length: 8 }).map((_, index) => (
              <Skeleton key={index} className="h-44 rounded-xl" />
            ))}
          </div>
        ) : filteredProducts.length === 0 ? (
          <EmptyState title="No products found" subtext="Try a different search term or category." />
        ) : (
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            {filteredProducts.map((product) => {
              const stock = stockByProduct.get(product.id)
              const outOfStock = product.track_inventory && stock !== undefined && stock <= 0

              return (
                <button
                  key={product.id}
                  type="button"
                  onClick={() => onAddProduct(product.id)}
                  disabled={outOfStock}
                  className={cn(
                    'flex flex-col rounded-xl border border-border bg-card p-3 text-left transition-colors hover:border-primary/50 hover:bg-accent/40',
                    outOfStock && 'cursor-not-allowed opacity-50 hover:border-border hover:bg-card',
                  )}
                >
                  <ProductImage src={product.image_url} alt={product.name} className="mb-2 h-28 w-full rounded-lg" />
                  <p className="truncate text-sm font-medium text-foreground">{product.name}</p>
                  <p className="text-xs text-muted-foreground">{product.sku}</p>
                  <div className="mt-1.5 flex items-center justify-between">
                    <span className="text-sm font-semibold text-primary">{formatCurrency(product.selling_price)}</span>
                    {product.track_inventory && stock !== undefined && (
                      <span className={cn('text-xs', outOfStock ? 'text-danger' : 'text-muted-foreground')}>
                        {outOfStock ? 'Out of stock' : `${stock} left`}
                      </span>
                    )}
                  </div>
                </button>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}
