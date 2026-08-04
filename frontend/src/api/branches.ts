import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export interface Branch {
  id: number
  company_id: number
  name: string
  code: string
  address: string | null
  phone: string | null
  is_main: boolean
  is_active: boolean
}

export async function fetchBranches(): Promise<Branch[]> {
  const { data } = await api.get<PaginatedResponse<Branch>>('/branches', { params: { per_page: 50 } })
  return data.data
}

export interface BranchInput {
  name: string
  code: string
  address?: string | null
  phone?: string | null
  is_main?: boolean
  is_active?: boolean
}

export async function createBranch(values: BranchInput): Promise<Branch> {
  const { data } = await api.post<ApiResponse<Branch>>('/branches', values)
  return data.data
}

export async function updateBranch(id: number, values: Partial<BranchInput>): Promise<Branch> {
  const { data } = await api.put<ApiResponse<Branch>>(`/branches/${id}`, values)
  return data.data
}

export async function deleteBranch(id: number): Promise<void> {
  await api.delete(`/branches/${id}`)
}
