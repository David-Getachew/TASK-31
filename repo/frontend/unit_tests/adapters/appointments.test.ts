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
import { appointmentsAdapter } from '../../src/adapters/appointments'

describe('appointmentsAdapter', () => {
  beforeEach(() => {
    vi.mocked(http.request).mockReset()
    vi.mocked(http.get).mockReset()
    enqueueAction.mockReset()
  })

  it('list calls GET /appointments', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: { data: [] } } as any)
    await appointmentsAdapter.list()
    expect(http.get).toHaveBeenCalledWith('/appointments')
  })

  it('create calls POST /appointments via offlineSafeWrite', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: { data: {} } } as any)
    const payload = {
      owner_user_id: 1,
      resource_type: 'course',
      scheduled_start: '2025-09-01T09:00:00Z',
      scheduled_end: '2025-09-01T10:00:00Z',
    }
    await appointmentsAdapter.create(payload)
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'post',
      url: '/appointments',
      data: payload,
    }))
  })

  it('cancel calls DELETE /appointments/{id} via offlineSafeWrite', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: {} } as any)
    await appointmentsAdapter.cancel(7)
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'delete',
      url: '/appointments/7',
    }))
  })
})
