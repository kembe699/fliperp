import { api } from '@/lib/api'
import type { PaginatedResponse } from '@/types/api'
import type { Product, StockLevel } from '@/types/product'

export async function fetchActiveProducts(params: { category_id?: number; search?: string } = {}): Promise<Product[]> {
  const { data } = await api.get<PaginatedResponse<Product>>('/products', {
    params: { ...params, is_active: true, per_page: 100 },
  })
  return data.data
}

export async function fetchStockLevels(warehouseId?: number): Promise<StockLevel[]> {
  const { data } = await api.get<PaginatedResponse<StockLevel>>('/stock-levels', {
    params: { warehouse_id: warehouseId, per_page: 200 },
  })
  return data.data
}

export interface Category {
  id: number
  company_id: number
  name: string
  parent_id: number | null
  is_active: boolean
}

export async function fetchCategories(): Promise<Category[]> {
  const { data } = await api.get<PaginatedResponse<Category>>('/categories', { params: { per_page: 100 } })
  return data.data
}
