import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { CacheStore } from '@/offline/cache'
import { PendingQueue } from '@/offline/queue'
import http from '@/adapters/http'

export interface PendingAction {
  id: string
  endpoint: string
  method: string
  payload: unknown
  idempotencyKey: string
  retries: number
  lastAttempt: number | null
  error: string | null
}

const cache = new CacheStore('campuslearn')
const queue = new PendingQueue('campuslearn_queue')

export const useOfflineStore = defineStore('offline', () => {
  const isReadOnly     = ref(false)
  const pendingActions = ref<PendingAction[]>([])
  const retryBanner    = ref(false)

  const pendingCount = computed(() => pendingActions.value.length)

  function setReadOnly(val: boolean) {
    isReadOnly.value = val
    retryBanner.value = val && pendingActions.value.length > 0
  }

  async function enqueueAction(action: Omit<PendingAction, 'retries' | 'lastAttempt' | 'error'>) {
    const entry: PendingAction = { ...action, retries: 0, lastAttempt: null, error: null }
    pendingActions.value.push(entry)
    await queue.enqueue(entry)
    retryBanner.value = true
  }

  async function removeAction(id: string) {
    pendingActions.value = pendingActions.value.filter((a) => a.id !== id)
    await queue.dequeue(id)
    if (pendingActions.value.length === 0) retryBanner.value = false
  }

  async function loadQueue() {
    const stored = await queue.getAll()
    pendingActions.value = stored
    retryBanner.value = stored.length > 0
  }

  async function replayQueue(): Promise<{ replayed: number; failed: number }> {
    let replayed = 0
    let failed = 0
    const snapshot = [...pendingActions.value]
    for (const action of snapshot) {
      try {
        await http.request({
          url: action.endpoint,
          method: action.method as any,
          data: action.payload,
          headers: { 'Idempotency-Key': action.idempotencyKey },
        })
        await removeAction(action.id)
        replayed++
      } catch (err: any) {
        failed++
        await queue.update(action.id, {
          retries: action.retries + 1,
          lastAttempt: Date.now(),
          error: err?.message ?? 'replay_failed',
        })
        const updated = pendingActions.value.find((a) => a.id === action.id)
        if (updated) {
          updated.retries = action.retries + 1
          updated.lastAttempt = Date.now()
          updated.error = err?.message ?? 'replay_failed'
        }
      }
    }
    if (pendingActions.value.length === 0) retryBanner.value = false
    return { replayed, failed }
  }

  async function cacheRead<T>(key: string, data: T) {
    await cache.set(key, data)
  }

  async function getCached<T>(key: string): Promise<T | null> {
    return cache.get<T>(key)
  }

  return {
    isReadOnly, pendingActions, retryBanner, pendingCount,
    setReadOnly, enqueueAction, removeAction, loadQueue, replayQueue,
    cacheRead, getCached,
  }
})
