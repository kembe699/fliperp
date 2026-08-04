export type VehicleStatus = 'available' | 'in_transit' | 'maintenance'

export interface Vehicle {
  id: number
  company_id: number
  branch_id: number
  registration_number: string
  make: string | null
  model: string | null
  capacity: string | null
  status: VehicleStatus
}

export type DispatchStatus = 'pending' | 'in_transit' | 'delivered' | 'cancelled'
export type DispatchSourceModule = 'procurement' | 'sales' | 'transfer'

export interface DispatchItem {
  id: number
  item_description: string
  quantity: number
  unit: string | null
}

export interface DeliveryTracking {
  id: number
  dispatch_id: number
  status: string
  location: string | null
  notes: string | null
  recorded_by: number | null
  recorded_at: string
}

export interface Dispatch {
  id: number
  company_id: number
  branch_id: number
  vehicle_id: number | null
  reference_number: string
  source_module: DispatchSourceModule
  source_id: number | null
  dispatched_by: number
  dispatch_date: string
  status: DispatchStatus
  items: DispatchItem[]
  tracking: DeliveryTracking[]
}
