import http, { generateIdempotencyKey, type NormalizedError } from './http'
import { useOfflineStore } from '@/stores/offline'
import type { AxiosResponse } from 'axios'

type Method = 'post' | 'put' | 'patch' | 'delete'

interface QueuedResult {
  queued: true
  queuedId: string
  idempotencyKey: string
}

export type WriteResult<T> = AxiosResponse<T> | QueuedResult

// Transient-failure classification — the conditions under which we should
// capture a failed write into the offline queue instead of surfacing the error.
function shouldQueue(err: NormalizedError | undefined): boolean {
  if (!err) return true // network-level / no response
  const s = err.httpStatus
  return s === undefined || s === 0 || s === 503 || s === 504 || s === 502
}

/**
 * Perform a write request that is safe to retry. If the client is currently in
 * read-only mode (circuit open) or the request fails with a transient error,
 * the action is queued to IndexedDB and will be replayed on reconnect.
 */
export async function offlineSafeWrite<T = unknown>(
  method: Method,
  endpoint: string,
  payload: unknown = null,
  options: { idempotencyKey?: string } = {},
): Promise<WriteResult<T>> {
  const offline = useOfflineStore()
  const idempotencyKey = options.idempotencyKey ?? generateIdempotencyKey()
  const id = `pending-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`

  // Short-circuit: already read-only → queue directly, don't hit the network.
  if (offline.isReadOnly) {
    await offline.enqueueAction({ id, endpoint, method, payload, idempotencyKey })
    return { queued: true, queuedId: id, idempotencyKey }
  }

  try {
    return await http.request<T>({
      url: endpoint,
      method,
      data: payload,
      headers: { 'Idempotency-Key': idempotencyKey },
    })
  } catch (err) {
    const normalized = err as NormalizedError
    if (shouldQueue(normalized)) {
      await offline.enqueueAction({ id, endpoint, method, payload, idempotencyKey })
      offline.setReadOnly(true)
      return { queued: true, queuedId: id, idempotencyKey }
    }
    throw err
  }
}

export function isQueued<T>(result: WriteResult<T>): result is QueuedResult {
  return (result as QueuedResult).queued === true
}
