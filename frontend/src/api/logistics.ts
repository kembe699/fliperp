import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { DeliveryTracking, Dispatch, DispatchSourceModule, Vehicle, VehicleStatus } from '@/types/logistics'

// Vehicles
export async function fetchVehicles(): Promise<Vehicle[]> {
  const { data } = await api.get<PaginatedResponse<Vehicle>>('/vehicles', { params: { per_page: 100 } })
  return data.data
}

export interface VehicleInput {
  branch_id: number
  registration_number: string
  make?: string | null
  model?: string | null
  capacity?: string | null
  status?: VehicleStatus
}

export async function createVehicle(values: VehicleInput): Promise<Vehicle> {
  const { data } = await api.post<ApiResponse<Vehicle>>('/vehicles', values)
  return data.data
}

export async function updateVehicle(id: number, values: Partial<VehicleInput>): Promise<Vehicle> {
  const { data } = await api.put<ApiResponse<Vehicle>>(`/vehicles/${id}`, values)
  return data.data
}

export async function deleteVehicle(id: number): Promise<void> {
  await api.delete(`/vehicles/${id}`)
}

// Dispatches
export async function fetchDispatches(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<Dispatch>> {
  const { data } = await api.get<PaginatedResponse<Dispatch>>('/dispatches', { params })
  return data
}

export async function fetchDispatch(id: number): Promise<Dispatch> {
  const { data } = await api.get<ApiResponse<Dispatch>>(`/dispatches/${id}`)
  return data.data
}

export interface DispatchInput {
  branch_id: number
  vehicle_id?: number | null
  reference_number: string
  source_module: DispatchSourceModule
  source_id?: number | null
  dispatch_date: string
  items?: { item_description: string; quantity: number; unit?: string | null }[]
}

export async function createDispatch(values: DispatchInput): Promise<Dispatch> {
  const { data } = await api.post<ApiResponse<Dispatch>>('/dispatches', values)
  return data.data
}

export async function trackDispatch(id: number, values: { status: string; location?: string | null; notes?: string | null }): Promise<DeliveryTracking> {
  const { data } = await api.post<ApiResponse<DeliveryTracking>>(`/dispatches/${id}/track`, values)
  return data.data
}
