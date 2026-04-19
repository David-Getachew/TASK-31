import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent } from 'vue'

vi.mock('../../src/adapters/http', () => ({
  default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
  generateIdempotencyKey: vi.fn(() => 'test-idempotency-key'),
}))
vi.mock('../../src/adapters/orders', () => ({
  ordersAdapter: {
    list:            vi.fn(),
    get:             vi.fn(),
    create:          vi.fn(),
    cancel:          vi.fn(),
    timeline:        vi.fn().mockResolvedValue({ data: { data: [] } }),
    initiatePayment: vi.fn(),
    completePayment: vi.fn(),
    getReceipt:      vi.fn(),
  },
}))
vi.mock('../../src/adapters/notifications', () => ({
  notificationsAdapter: { unreadCount: vi.fn().mockResolvedValue({ data: { data: {} } }) },
}))
vi.mock('../../src/offline/cache', () => ({
  CacheStore: vi.fn().mockImplementation(() => ({ set: vi.fn(), get: vi.fn().mockResolvedValue(null) })),
}))
vi.mock('../../src/offline/queue', () => ({
  PendingQueue: vi.fn().mockImplementation(() => ({
    enqueue: vi.fn(), dequeue: vi.fn(), getAll: vi.fn().mockResolvedValue([]), update: vi.fn(),
  })),
}))

import { ordersAdapter } from '../../src/adapters/orders'
import { useOrdersStore } from '../../src/stores/orders'
import StatusChip from '../../src/components/ui/StatusChip.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/orders', name: 'orders', component: defineComponent({ template: '<div />' }) },
    { path: '/orders/:id', name: 'order-detail', component: defineComponent({ template: '<div />' }) },
    { path: '/orders/:id/payment', name: 'order-payment', component: defineComponent({ template: '<div />' }) },
    { path: '/orders/:id/receipt', name: 'order-receipt', component: defineComponent({ template: '<div />' }) },
    { path: '/catalog', name: 'catalog', component: defineComponent({ template: '<div />' }) },
  ],
})

describe('StatusChip', () => {
  it.each([
    ['pending_payment', 'warning'],
    ['paid',            'success'],
    ['canceled',        'neutral'],
    ['refunded',        'info'],
    ['redeemed',        'info'],
  ])('renders correct variant for status=%s', (status, variant) => {
    const wrapper = mount(StatusChip, { props: { status } })
    expect(wrapper.classes()).toContain(`status-chip--${variant}`)
  })
})

describe('OrdersStore payment flow', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('completePayment sets conflict on IDEMPOTENCY_KEY_CONFLICT', async () => {
    vi.mocked(ordersAdapter.completePayment).mockRejectedValueOnce({
      code: 'IDEMPOTENCY_KEY_CONFLICT', message: 'Duplicate', httpStatus: 409,
    })
    const store  = useOrdersStore()
    const result = await store.completePayment(1, 1, 'idem-key')
    expect(result).toBeNull()
    expect(store.conflict).toContain('already processed')
  })

  it('completePayment sets conflict on INVALID_STATE_TRANSITION', async () => {
    vi.mocked(ordersAdapter.completePayment).mockRejectedValueOnce({
      code: 'INVALID_STATE_TRANSITION', message: 'Not payable', httpStatus: 422,
    })
    const store  = useOrdersStore()
    const result = await store.completePayment(1, 1, 'idem-key')
    expect(result).toBeNull()
    expect(store.conflict).toContain('no longer in a payable state')
  })

  it('initiatePayment returns attempt and idempotencyKey on success', async () => {
    const mockAttempt = { id: 5, order_id: 1, method: 'cash', operator_user_id: 1, amount_cents: 1000, status: 'pending', completed_at: null }
    vi.mocked(ordersAdapter.initiatePayment).mockResolvedValueOnce({ data: { data: mockAttempt } } as any)
    const store  = useOrdersStore()
    const result = await store.initiatePayment(1, 'cash')
    expect(result).not.toBeNull()
    expect(result?.attempt.id).toBe(5)
    expect(result?.idempotencyKey).toBeTruthy()
  })
})
