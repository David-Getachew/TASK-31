import http from './http'
import { offlineSafeWrite } from './offlineCapture'
import type { Order, PaymentAttempt, Receipt } from '../types/api'
import type { ApiResponse, PaginatedResponse } from '../types'

export const ordersAdapter = {
  list: () =>
    http.get<PaginatedResponse<Order>>('/orders'),

  get: (id: number) =>
    http.get<ApiResponse<Order>>(`/orders/${id}`),

  create: (lines: Array<{ catalog_item_id: number; quantity: number }>) =>
    offlineSafeWrite<ApiResponse<Order>>('post', '/orders', { lines }),

  cancel: (id: number) =>
    offlineSafeWrite('delete', `/orders/${id}`),

  timeline: (id: number) =>
    http.get<ApiResponse<unknown[]>>(`/orders/${id}/timeline`),

  initiatePayment: (orderId: number, data: { method: string; kiosk_id?: string }, idempotencyKey: string) =>
    offlineSafeWrite<ApiResponse<PaymentAttempt>>('post', `/orders/${orderId}/payment`, data, { idempotencyKey }),

  completePayment: (orderId: number, attemptId: number, idempotencyKey: string) =>
    offlineSafeWrite<ApiResponse<Order>>('post', `/orders/${orderId}/payment/complete`, { attempt_id: attemptId }, { idempotencyKey }),

  getReceipt: (orderId: number) =>
    http.get<ApiResponse<Receipt>>(`/orders/${orderId}/receipt`),
}
