import { describe, it, expect, vi, beforeEach } from 'vitest'

vi.mock('../../src/adapters/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    request: vi.fn(),
  },
  generateIdempotencyKey: () => 'test-idem-key',
}))

const enqueueAction = vi.fn()
vi.mock('@/stores/offline', () => ({
  useOfflineStore: () => ({
    isReadOnly: false,
    enqueueAction,
    setReadOnly: vi.fn(),
  }),
}))

import http from '../../src/adapters/http'
import { ordersAdapter } from '../../src/adapters/orders'

describe('ordersAdapter', () => {
  beforeEach(() => {
    vi.mocked(http.request).mockReset()
    vi.mocked(http.get).mockReset()
    enqueueAction.mockReset()
  })

  it('list calls GET /orders', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: { data: [] } } as any)
    await ordersAdapter.list()
    expect(http.get).toHaveBeenCalledWith('/orders')
  })

  it('create calls POST /orders via offlineSafeWrite with lines payload', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: { data: {} } } as any)
    const lines = [{ catalog_item_id: 1, quantity: 2 }]
    await ordersAdapter.create(lines)
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'post',
      url: '/orders',
      data: { lines },
      headers: expect.objectContaining({ 'Idempotency-Key': expect.any(String) }),
    }))
  })

  it('initiatePayment includes Idempotency-Key header', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: { data: {} } } as any)
    await ordersAdapter.initiatePayment(5, { method: 'cash' }, 'idem-key-123')
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'post',
      url: '/orders/5/payment',
      headers: expect.objectContaining({ 'Idempotency-Key': 'idem-key-123' }),
    }))
  })

  it('cancel calls DELETE /orders/:id via offlineSafeWrite', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: {} } as any)
    await ordersAdapter.cancel(3)
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'delete',
      url: '/orders/3',
    }))
  })

  it('create enqueues action when a transient 503 is encountered', async () => {
    vi.mocked(http.request).mockRejectedValueOnce({ httpStatus: 503, code: 'SERVICE_UNAVAILABLE', message: 'down' })
    const lines = [{ catalog_item_id: 1, quantity: 1 }]
    const result = await ordersAdapter.create(lines)
    expect(enqueueAction).toHaveBeenCalledTimes(1)
    expect(enqueueAction).toHaveBeenCalledWith(expect.objectContaining({
      endpoint: '/orders',
      method: 'post',
      payload: { lines },
    }))
    expect((result as any).queued).toBe(true)
  })
})
