import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { AppNotification } from '@/types/notification'

export interface NotificationFilters {
  page?: number
  per_page?: number
  status?: 'read' | 'unread'
  category?: string
}

export async function fetchNotifications(filters: NotificationFilters = {}): Promise<PaginatedResponse<AppNotification>> {
  const { data } = await api.get<PaginatedResponse<AppNotification>>('/notifications', { params: filters })
  return data
}

export async function fetchUnreadNotificationCount(): Promise<number> {
  const { data } = await api.get<ApiResponse<{ count: number }>>('/notifications/unread-count')
  return data.data.count
}

export async function markNotificationRead(id: string): Promise<AppNotification> {
  const { data } = await api.post<ApiResponse<AppNotification>>(`/notifications/${id}/read`)
  return data.data
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all')
}
