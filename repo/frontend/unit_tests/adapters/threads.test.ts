import { describe, it, expect, vi, beforeEach } from 'vitest'

vi.mock('../../src/adapters/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
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
import { threadsAdapter } from '../../src/adapters/threads'

describe('threadsAdapter', () => {
  beforeEach(() => {
    vi.mocked(http.request).mockReset()
    vi.mocked(http.get).mockReset()
    enqueueAction.mockReset()
  })

  it('list calls GET /threads', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: { data: [] } } as any)
    await threadsAdapter.list()
    expect(http.get).toHaveBeenCalledWith('/threads', expect.anything())
  })

  it('create calls POST /threads via offlineSafeWrite', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: { data: {} } } as any)
    const payload = { section_id: 1, type: 'discussion', title: 'T', body: 'B' }
    await threadsAdapter.create(payload)
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'post',
      url: '/threads',
      data: payload,
    }))
  })

  it('update calls PATCH /threads/:id via offlineSafeWrite', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: { data: {} } } as any)
    await threadsAdapter.update(5, { title: 'Updated' })
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'patch',
      url: '/threads/5',
      data: { title: 'Updated' },
    }))
  })

  it('deletePost calls DELETE with correct path via offlineSafeWrite', async () => {
    vi.mocked(http.request).mockResolvedValueOnce({ data: {} } as any)
    await threadsAdapter.deletePost(1, 2)
    expect(http.request).toHaveBeenCalledWith(expect.objectContaining({
      method: 'delete',
      url: '/threads/1/posts/2',
    }))
  })

  it('create enqueues action when a transient failure occurs', async () => {
    vi.mocked(http.request).mockRejectedValueOnce({ httpStatus: 503, code: 'SERVICE_UNAVAILABLE', message: 'down' })
    await threadsAdapter.create({ section_id: 1, type: 'discussion', title: 'T', body: 'B' })
    expect(enqueueAction).toHaveBeenCalledTimes(1)
    expect(enqueueAction).toHaveBeenCalledWith(expect.objectContaining({ endpoint: '/threads', method: 'post' }))
  })
})
