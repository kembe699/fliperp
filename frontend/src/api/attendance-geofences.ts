import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { AttendanceGeofence } from '@/types/hr'

export async function fetchAttendanceGeofences(branchId: number): Promise<AttendanceGeofence[]> {
  const { data } = await api.get<PaginatedResponse<AttendanceGeofence>>('/attendance-geofences', {
    params: { branch_id: branchId, per_page: 100 },
  })
  return data.data
}

export interface AttendanceGeofenceInput {
  branch_id: number
  name: string
  latitude: number
  longitude: number
  radius_meters: number
  is_active?: boolean
}

export async function createAttendanceGeofence(values: AttendanceGeofenceInput): Promise<AttendanceGeofence> {
  const { data } = await api.post<ApiResponse<AttendanceGeofence>>('/attendance-geofences', values)
  return data.data
}

export async function updateAttendanceGeofence(id: number, values: Partial<AttendanceGeofenceInput>): Promise<AttendanceGeofence> {
  const { data } = await api.put<ApiResponse<AttendanceGeofence>>(`/attendance-geofences/${id}`, values)
  return data.data
}

export async function deleteAttendanceGeofence(id: number): Promise<void> {
  await api.delete(`/attendance-geofences/${id}`)
}

export async function regenerateAttendanceQrToken(branchId: number): Promise<{ token: string; created_at: string }> {
  const { data } = await api.post<ApiResponse<{ branch_id: number; token: string; created_at: string }>>(
    `/attendance-geofences/${branchId}/regenerate-qr-token`,
  )
  return data.data
}

/**
 * The QR endpoint requires the bearer token (a plain <img src> can't carry
 * it), and returns raw SVG markup rather than a binary image, so it's
 * fetched as text and embedded inline — no blob URL needed, and the same
 * markup can be reused verbatim in the print window.
 */
export async function fetchAttendanceQrSvg(branchId: number): Promise<string> {
  const { data } = await api.get<string>(`/attendance-geofences/${branchId}/qr-code`, { responseType: 'text' })
  return data
}
