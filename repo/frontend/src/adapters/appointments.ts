import http from './http'
import { offlineSafeWrite } from './offlineCapture'
import type { Appointment } from '../types/api'
import type { ApiResponse, PaginatedResponse } from '../types'

export const appointmentsAdapter = {
  list: () =>
    http.get<PaginatedResponse<Appointment>>('/appointments'),

  get: (id: number) =>
    http.get<ApiResponse<Appointment>>(`/appointments/${id}`),

  create: (data: {
    owner_user_id: number
    resource_type: string
    resource_ref?: string
    scheduled_start: string
    scheduled_end: string
    notes?: string
  }) =>
    offlineSafeWrite<ApiResponse<Appointment>>('post', '/appointments', data),

  update: (id: number, data: Partial<{
    resource_type: string
    resource_ref: string
    scheduled_start: string
    scheduled_end: string
    notes: string
    status: string
  }>) =>
    offlineSafeWrite<ApiResponse<Appointment>>('patch', `/appointments/${id}`, data),

  cancel: (id: number) =>
    offlineSafeWrite('delete', `/appointments/${id}`),
}
