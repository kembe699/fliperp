import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type {
  Category,
  Product,
  ProductFormValues,
  ProductVariant,
  StockAdjustment,
  StockAdjustmentStatus,
  StockMovement,
  StockTransfer,
  StockTransferStatus,
  UnitOfMeasure,
  Warehouse,
} from '@/types/inventory'

// Products
export interface ProductFilters {
  page?: number
  per_page?: number
  category_id?: number
  is_active?: boolean
  low_stock?: boolean
  search?: string
}

export async function fetchProducts(filters: ProductFilters): Promise<PaginatedResponse<Product>> {
  const { data } = await api.get<PaginatedResponse<Product>>('/products', { params: filters })
  return data
}

export async function fetchProduct(id: number): Promise<Product> {
  const { data } = await api.get<ApiResponse<Product>>(`/products/${id}`)
  return data.data
}

export async function createProduct(values: ProductFormValues): Promise<Product> {
  const { data } = await api.post<ApiResponse<Product>>('/products', values)
  return data.data
}

export async function updateProduct(id: number, values: Partial<ProductFormValues>): Promise<Product> {
  const { data } = await api.put<ApiResponse<Product>>(`/products/${id}`, values)
  return data.data
}

export async function uploadProductImage(id: number, file: File): Promise<Product> {
  const formData = new FormData()
  formData.append('image', file)
  const { data } = await api.post<ApiResponse<Product>>(`/products/${id}/image`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function deleteProductImage(id: number): Promise<Product> {
  const { data } = await api.delete<ApiResponse<Product>>(`/products/${id}/image`)
  return data.data
}

export async function fetchProductStockMovements(id: number, page = 1): Promise<PaginatedResponse<StockMovement>> {
  const { data } = await api.get<PaginatedResponse<StockMovement>>(`/products/${id}/stock-movements`, { params: { page, per_page: 15 } })
  return data
}

export async function createProductVariant(
  productId: number,
  values: { name: string; sku: string; barcode?: string | null; price_adjustment?: number },
): Promise<ProductVariant> {
  const { data } = await api.post<ApiResponse<ProductVariant>>(`/products/${productId}/variants`, values)
  return data.data
}

export async function deleteProductVariant(productId: number, variantId: number): Promise<void> {
  await api.delete(`/products/${productId}/variants/${variantId}`)
}

// Categories
export async function fetchCategories(): Promise<Category[]> {
  const { data } = await api.get<PaginatedResponse<Category>>('/categories', { params: { per_page: 100 } })
  return data.data
}

export async function createCategory(values: { name: string; parent_id?: number | null; is_active?: boolean; sort_order?: number }): Promise<Category> {
  const { data } = await api.post<ApiResponse<Category>>('/categories', values)
  return data.data
}

export async function updateCategory(id: number, values: { name?: string; parent_id?: number | null; is_active?: boolean; sort_order?: number }): Promise<Category> {
  const { data } = await api.put<ApiResponse<Category>>(`/categories/${id}`, values)
  return data.data
}

export async function deleteCategory(id: number): Promise<void> {
  await api.delete(`/categories/${id}`)
}

// Units of measure
export async function fetchUnitsOfMeasure(): Promise<UnitOfMeasure[]> {
  const { data } = await api.get<PaginatedResponse<UnitOfMeasure>>('/units-of-measure', { params: { per_page: 100 } })
  return data.data
}

// Warehouses
export async function fetchWarehouses(): Promise<Warehouse[]> {
  const { data } = await api.get<PaginatedResponse<Warehouse>>('/warehouses', { params: { per_page: 100 } })
  return data.data
}

export async function createWarehouse(values: { name: string; code: string; branch_id: number; is_default?: boolean }): Promise<Warehouse> {
  const { data } = await api.post<ApiResponse<Warehouse>>('/warehouses', values)
  return data.data
}

export async function updateWarehouse(id: number, values: Partial<{ name: string; code: string; branch_id: number; is_default: boolean }>): Promise<Warehouse> {
  const { data } = await api.put<ApiResponse<Warehouse>>(`/warehouses/${id}`, values)
  return data.data
}

export async function deleteWarehouse(id: number): Promise<void> {
  await api.delete(`/warehouses/${id}`)
}

// Stock levels (reused across modules)
export interface StockLevel {
  id: number
  product_id: number
  product_name: string
  product_sku: string
  reorder_level: number
  product_variant_id: number | null
  warehouse_id: number
  warehouse_name: string
  quantity_on_hand: number
  quantity_reserved: number
  is_low_stock: boolean
}

export async function fetchStockLevels(params: { warehouse_id?: number; product_id?: number; low_stock?: boolean } = {}): Promise<StockLevel[]> {
  const { data } = await api.get<PaginatedResponse<StockLevel>>('/stock-levels', { params: { ...params, per_page: 200 } })
  return data.data
}

// Stock transfers
export interface StockTransferFilters {
  page?: number
  per_page?: number
  status?: StockTransferStatus
  from_warehouse_id?: number
  to_warehouse_id?: number
}

export async function fetchStockTransfers(filters: StockTransferFilters): Promise<PaginatedResponse<StockTransfer>> {
  const { data } = await api.get<PaginatedResponse<StockTransfer>>('/stock-transfers', { params: filters })
  return data
}

export async function fetchStockTransfer(id: number): Promise<StockTransfer> {
  const { data } = await api.get<ApiResponse<StockTransfer>>(`/stock-transfers/${id}`)
  return data.data
}

export interface StockTransferInput {
  from_warehouse_id: number
  to_warehouse_id: number
  reference_number: string
  items: { product_id: number; product_variant_id?: number | null; quantity: number }[]
}

export async function createStockTransfer(values: StockTransferInput): Promise<StockTransfer> {
  const { data } = await api.post<ApiResponse<StockTransfer>>('/stock-transfers', values)
  return data.data
}

export async function markStockTransferInTransit(id: number): Promise<StockTransfer> {
  const { data } = await api.post<ApiResponse<StockTransfer>>(`/stock-transfers/${id}/mark-in-transit`)
  return data.data
}

export async function completeStockTransfer(id: number, allowNegativeStock = false): Promise<StockTransfer> {
  const { data } = await api.post<ApiResponse<StockTransfer>>(`/stock-transfers/${id}/complete`, { allow_negative_stock: allowNegativeStock })
  return data.data
}

export async function cancelStockTransfer(id: number): Promise<StockTransfer> {
  const { data } = await api.post<ApiResponse<StockTransfer>>(`/stock-transfers/${id}/cancel`)
  return data.data
}

// Stock adjustments
export interface StockAdjustmentFilters {
  page?: number
  per_page?: number
  status?: StockAdjustmentStatus
  warehouse_id?: number
}

export async function fetchStockAdjustments(filters: StockAdjustmentFilters): Promise<PaginatedResponse<StockAdjustment>> {
  const { data } = await api.get<PaginatedResponse<StockAdjustment>>('/stock-adjustments', { params: filters })
  return data
}

export async function fetchStockAdjustment(id: number): Promise<StockAdjustment> {
  const { data } = await api.get<ApiResponse<StockAdjustment>>(`/stock-adjustments/${id}`)
  return data.data
}

export interface StockAdjustmentInput {
  warehouse_id: number
  reference_number: string
  reason?: string
  items: { product_id: number; product_variant_id?: number | null; counted_quantity: number }[]
}

export async function createStockAdjustment(values: StockAdjustmentInput): Promise<StockAdjustment> {
  const { data } = await api.post<ApiResponse<StockAdjustment>>('/stock-adjustments', values)
  return data.data
}

export async function approveStockAdjustment(id: number): Promise<StockAdjustment> {
  const { data } = await api.post<ApiResponse<StockAdjustment>>(`/stock-adjustments/${id}/approve`)
  return data.data
}
