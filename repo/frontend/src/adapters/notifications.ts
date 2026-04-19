import http from './http'
import type { Notification } from '../types/api'
import type { ApiResponse, PaginatedResponse } from '../types'

export const notificationsAdapter = {
  list: (params?: { category?: string; unread_only?: boolean }) =>
    http.get<PaginatedResponse<Notification>>('/notifications', { params }),

  unreadCount: () =>
    http.get<ApiResponse<Record<string, number>>>('/notifications/unread-count'),

  markRead: (data: { ids?: number[]; category?: string }) =>
    http.post<ApiResponse<{ marked: boolean }>>('/notifications/mark-read', data),

  markOneRead: (id: number) =>
    http.post<ApiResponse<{ marked: boolean }>>(`/notifications/${id}/read`),

  getPreferences: () =>
    http.get<ApiResponse<Record<string, boolean>>>('/notifications/preferences'),

  updatePreferences: (preferences: Record<string, boolean>) =>
    http.put<ApiResponse<{ updated: boolean }>>('/notifications/preferences', { preferences }),
}
