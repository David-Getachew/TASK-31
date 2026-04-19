import { defineStore } from 'pinia'
import { ref } from 'vue'
import { ordersAdapter } from '@/adapters/orders'
import type { Order, PaymentAttempt, Receipt, CatalogItem } from '@/types/api'
import http from '@/adapters/http'
import type { PaginatedResponse, ApiResponse } from '@/types'
import { generateIdempotencyKey } from '@/adapters/http'

function extractCollection<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) return payload as T[]
  if (payload && typeof payload === 'object') {
    const nested = (payload as { data?: unknown }).data
    if (Array.isArray(nested)) return nested as T[]
  }
  return []
}

export const useOrdersStore = defineStore('orders', () => {
  const orders       = ref<Order[]>([])
  const activeOrder  = ref<Order | null>(null)
  const timeline     = ref<unknown[]>([])
  const receipt      = ref<Receipt | null>(null)
  const catalog      = ref<CatalogItem[]>([])
  const loading      = ref(false)
  const error        = ref<string | null>(null)
  const submitting   = ref(false)
  const conflict     = ref<string | null>(null)

  async function fetchCatalog() {
    loading.value = true
    error.value   = null
    try {
      const res    = await http.get<PaginatedResponse<CatalogItem>>('/catalog')
      catalog.value = extractCollection<CatalogItem>(res.data.data)
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load catalog'
    } finally {
      loading.value = false
    }
  }

  async function fetchOrders() {
    loading.value = true
    error.value   = null
    try {
      const res    = await ordersAdapter.list()
      orders.value = extractCollection<Order>(res.data.data)
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load orders'
    } finally {
      loading.value = false
    }
  }

  async function fetchOrder(id: number) {
    loading.value = true
    error.value   = null
    try {
      const res         = await ordersAdapter.get(id)
      activeOrder.value = res.data.data
    } catch (e: any) {
      error.value = e?.message ?? 'Order not found'
    } finally {
      loading.value = false
    }
  }

  async function fetchTimeline(id: number) {
    try {
      const res    = await ordersAdapter.timeline(id)
      timeline.value = res.data.data as unknown[]
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to load timeline'
    }
  }

  async function createOrder(lines: Array<{ catalog_item_id: number; quantity: number }>) {
    submitting.value = true
    error.value      = null
    conflict.value   = null
    try {
      const res          = await ordersAdapter.create(lines)
      activeOrder.value  = res.data.data
      orders.value.unshift(res.data.data)
      return res.data.data
    } catch (e: any) {
      error.value = e?.message ?? 'Failed to create order'
      return null
    } finally {
      submitting.value = false
    }
  }

  async function initiatePayment(orderId: number, method: string, kioskId?: string) {
    submitting.value = true
    error.value      = null
    conflict.value   = null
    const key = generateIdempotencyKey()
    try {
      const res = await ordersAdapter.initiatePayment(orderId, { method, kiosk_id: kioskId }, key)
      return { attempt: res.data.data as PaymentAttempt, idempotencyKey: key }
    } catch (e: any) {
      if (e?.code === 'IDEMPOTENCY_KEY_CONFLICT') {
        conflict.value = 'A payment is already in progress for this order.'
      } else {
        error.value = e?.message ?? 'Failed to initiate payment'
      }
      return null
    } finally {
      submitting.value = false
    }
  }

  async function completePayment(orderId: number, attemptId: number, idempotencyKey: string) {
    submitting.value = true
    error.value      = null
    conflict.value   = null
    try {
      const res          = await ordersAdapter.completePayment(orderId, attemptId, idempotencyKey)
      activeOrder.value  = res.data.data
      return res.data.data
    } catch (e: any) {
      if (e?.code === 'IDEMPOTENCY_KEY_CONFLICT') {
        conflict.value = 'This payment was already processed. Refresh to see the current status.'
      } else if (e?.code === 'INVALID_STATE_TRANSITION') {
        conflict.value = 'This order is no longer in a payable state.'
      } else {
        error.value = e?.message ?? 'Payment completion failed'
      }
      return null
    } finally {
      submitting.value = false
    }
  }

  async function fetchReceipt(orderId: number) {
    loading.value = true
    try {
      const res      = await ordersAdapter.getReceipt(orderId)
      receipt.value  = res.data.data
    } catch (e: any) {
      error.value = e?.message ?? 'Receipt not available'
    } finally {
      loading.value = false
    }
  }

  function reset() {
    activeOrder.value = null
    timeline.value    = []
    receipt.value     = null
    error.value       = null
    conflict.value    = null
  }

  return {
    orders, activeOrder, timeline, receipt, catalog,
    loading, error, submitting, conflict,
    fetchCatalog, fetchOrders, fetchOrder, fetchTimeline,
    createOrder, initiatePayment, completePayment, fetchReceipt, reset,
  }
})
