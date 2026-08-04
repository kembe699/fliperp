export interface ApiResponse<T> {
  success: boolean
  data: T
  message: string
  errors: Record<string, string[]> | null
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: PaginationMeta
}
