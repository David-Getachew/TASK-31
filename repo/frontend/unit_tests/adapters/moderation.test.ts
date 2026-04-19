import { describe, it, expect, vi } from 'vitest'

vi.mock('../../src/adapters/http', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

import http from '../../src/adapters/http'
import { moderationAdapter } from '../../src/adapters/moderation'

describe('moderationAdapter', () => {
  it('queue calls GET /admin/moderation/queue', async () => {
    vi.mocked(http.get).mockResolvedValueOnce({ data: { data: [] } } as any)
    await moderationAdapter.queue()
    expect(http.get).toHaveBeenCalledWith('/admin/moderation/queue', expect.objectContaining({ params: undefined }))
  })

  it('hideThread calls POST /admin/threads/{id}/hide', async () => {
    vi.mocked(http.post).mockResolvedValueOnce({ data: { data: {} } } as any)
    await moderationAdapter.hideThread(10, 'violates policy')
    expect(http.post).toHaveBeenCalledWith('/admin/threads/10/hide', { reason: 'violates policy' })
  })

  it('lockThread calls POST /admin/threads/{id}/lock', async () => {
    vi.mocked(http.post).mockResolvedValueOnce({ data: { data: {} } } as any)
    await moderationAdapter.lockThread(10, 'spam')
    expect(http.post).toHaveBeenCalledWith('/admin/threads/10/lock', { reason: 'spam' })
  })

  it('reportPost posts to /posts/{postId}/reports with { reason } payload and does not include thread id', async () => {
    vi.mocked(http.post).mockResolvedValueOnce({ data: { data: { reported: true } } } as any)
    await moderationAdapter.reportPost(42, 'harassment')
    expect(http.post).toHaveBeenCalledWith('/posts/42/reports', { reason: 'harassment' })
    expect(http.post).toHaveBeenCalledTimes(1)
    const [, payload] = vi.mocked(http.post).mock.calls[0] as [string, any]
    expect(payload).toEqual({ reason: 'harassment' })
    expect(payload).not.toHaveProperty('thread_id')
  })

  it('hidePost calls POST /admin/threads/{threadId}/posts/{postId}/hide', async () => {
    vi.mocked(http.post).mockResolvedValueOnce({ data: { data: {} } } as any)
    await moderationAdapter.hidePost(1, 2, 'noise')
    expect(http.post).toHaveBeenCalledWith('/admin/threads/1/posts/2/hide', { reason: 'noise' })
  })

  it('restorePost calls POST /admin/threads/{threadId}/posts/{postId}/restore', async () => {
    vi.mocked(http.post).mockResolvedValueOnce({ data: { data: {} } } as any)
    await moderationAdapter.restorePost(1, 2)
    expect(http.post).toHaveBeenCalledWith('/admin/threads/1/posts/2/restore', {})
  })
})
