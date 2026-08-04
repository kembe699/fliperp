import { portalApi } from '@/lib/portal-api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { LeaveRequest, LeaveRequestStatus, LeaveSummary, PortalEmployee, PortalMe, PortalUser } from '@/types/employee-portal'

export interface PortalLoginPayload {
  token: string
  user: PortalUser
  employee: PortalEmployee
}

export async function portalLogin(email: string, password: string): Promise<PortalLoginPayload> {
  const { data } = await portalApi.post<ApiResponse<PortalLoginPayload>>('/employee-portal/login', { email, password })
  return data.data
}

export async function portalLogout(): Promise<void> {
  await portalApi.post('/employee-portal/logout')
}

export async function fetchPortalMe(): Promise<PortalMe> {
  const { data } = await portalApi.get<ApiResponse<PortalMe>>('/employee-portal/me')
  return data.data
}

export async function fetchPortalLeaveSummary(): Promise<LeaveSummary> {
  const { data } = await portalApi.get<ApiResponse<LeaveSummary>>('/employee-portal/leave-summary')
  return data.data
}

export interface PortalLeaveType {
  id: number
  name: string
  days_per_year: number
  is_paid: boolean
}

export async function fetchPortalLeaveTypes(): Promise<PortalLeaveType[]> {
  const { data } = await portalApi.get<ApiResponse<PortalLeaveType[]>>('/employee-portal/leave-types')
  return data.data
}

export interface ClockPayload {
  branch_id: number
  token: string
  latitude: number
  longitude: number
}

export async function portalCheckLocation(payload: ClockPayload): Promise<{ within_range: boolean; reason: string | null }> {
  const { data } = await portalApi.post<ApiResponse<{ within_range: boolean; reason: string | null }>>(
    '/employee-portal/check-location',
    payload,
  )
  return data.data
}

export async function portalClockIn(payload: ClockPayload) {
  const { data } = await portalApi.post('/employee-portal/clock-in', payload)
  return data.data
}

export async function portalClockOut(payload: ClockPayload) {
  const { data } = await portalApi.post('/employee-portal/clock-out', payload)
  return data.data
}

export async function fetchPortalLeaveRequests(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<LeaveRequest>> {
  const { data } = await portalApi.get<PaginatedResponse<LeaveRequest>>('/employee-portal/leave-requests', { params })
  return data
}

export interface PortalLeaveRequestInput {
  leave_type_id: number
  start_date: string
  end_date: string
  reason?: string | null
}

export async function createPortalLeaveRequest(values: PortalLeaveRequestInput): Promise<LeaveRequest> {
  const { data } = await portalApi.post<ApiResponse<LeaveRequest>>('/employee-portal/leave-requests', values)
  return data.data
}

export type { LeaveRequestStatus }
