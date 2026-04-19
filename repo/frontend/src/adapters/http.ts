import axios, { type AxiosInstance, type AxiosError } from 'axios'
import type { ApiError, ApiErrorCode } from '@/types'

// ── Correlation-ID ────────────────────────────────────────────────────────────

function generateCorrelationId(): string {
  return `cl-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`
}

// ── Idempotency key ───────────────────────────────────────────────────────────

export function generateIdempotencyKey(): string {
  return `idem-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`
}

// ── Normalized error shaping ──────────────────────────────────────────────────

export interface NormalizedError {
  code: ApiErrorCode
  message: string
  details?: Record<string, string[]>
  blocked_terms?: Array<{ term: string; start: number; end: number }>
  httpStatus?: number
}

export function normalizeError(err: unknown): NormalizedError {
  const axiosErr = err as AxiosError<{ error: ApiError }>
  const status   = axiosErr.response?.status
  const body     = axiosErr.response?.data?.error

  if (body?.code) {
    return {
      code:          body.code,
      message:       body.message,
      details:       body.details,
      blocked_terms: body.blocked_terms,
      httpStatus:    status,
    }
  }

  if (status === 401) return { code: 'UNAUTHENTICATED', message: 'Authentication required.', httpStatus: 401 }
  if (status === 403) return { code: 'FORBIDDEN', message: 'You do not have permission to perform this action.', httpStatus: 403 }
  if (status === 404) return { code: 'NOT_FOUND', message: 'The requested resource was not found.', httpStatus: 404 }
  if (status === 409) return { code: 'IDEMPOTENCY_KEY_CONFLICT', message: 'Duplicate request detected.', httpStatus: 409 }
  if (status === 422) return { code: 'VALIDATION_FAILED', message: 'Validation failed.', httpStatus: 422 }
  if (status === 429) return { code: 'RATE_LIMITED', message: 'Too many requests. Please wait and try again.', httpStatus: 429 }
  if (status === 503) return { code: 'SERVICE_UNAVAILABLE', message: 'The service is temporarily unavailable.', httpStatus: 503 }

  return { code: 'INTERNAL_ERROR', message: 'An unexpected error occurred.', httpStatus: status }
}

// ── Circuit-break detection ───────────────────────────────────────────────────

let _circuitOpenCallback: (() => void) | null = null

export function onCircuitOpen(cb: () => void) {
  _circuitOpenCallback = cb
}

// ── Axios instance ────────────────────────────────────────────────────────────

const http: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
})

// Request: attach auth token + correlation ID
http.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  config.headers['X-Correlation-ID'] = generateCorrelationId()
  return config
})

// Retry config for transient failures (exponential backoff + jitter).
const RETRY_MAX_ATTEMPTS = 3
const RETRY_BASE_DELAY_MS = 500
const RETRY_SAFE_METHODS = new Set(['get', 'head', 'options'])

function isTransientStatus(status?: number): boolean {
  return status === 502 || status === 503 || status === 504
}

function shouldRetry(error: AxiosError): boolean {
  const cfg = error.config as (typeof error.config & { __retryCount?: number; __noRetry?: boolean }) | undefined
  if (!cfg || cfg.__noRetry) return false
  const method = (cfg.method ?? 'get').toLowerCase()
  const status = error.response?.status
  const hasIdempotencyKey = Boolean(cfg.headers?.['Idempotency-Key'] || cfg.headers?.['idempotency-key'])
  const safe = RETRY_SAFE_METHODS.has(method) || hasIdempotencyKey
  if (!safe) return false
  // Retry on network / timeout / transient 5xx
  if (!error.response) return true
  return isTransientStatus(status)
}

// Response: normalize errors, detect circuit-break, handle 401, retry transient errors
http.interceptors.response.use(
  (response) => response,
  async (error: AxiosError<{ error: ApiError }>) => {
    const status = error.response?.status
    const cfg = error.config as (typeof error.config & { __retryCount?: number }) | undefined

    if (cfg && shouldRetry(error)) {
      cfg.__retryCount = (cfg.__retryCount ?? 0) + 1
      if (cfg.__retryCount <= RETRY_MAX_ATTEMPTS) {
        const delay =
          RETRY_BASE_DELAY_MS *
          Math.pow(2, cfg.__retryCount - 1) *
          (0.9 + Math.random() * 0.2)
        await new Promise((res) => setTimeout(res, delay))
        return http.request(cfg)
      }
    }

    // Trigger circuit-break callback on 503
    if (status === 503 && _circuitOpenCallback) {
      _circuitOpenCallback()
    }

    // Auto-clear session on 401 (but don't redirect here — let router guard handle it)
    if (status === 401) {
      localStorage.removeItem('auth_token')
      localStorage.removeItem('auth_expires_at')
    }

    // Reject with normalized error so callers don't need to parse raw axios errors
    return Promise.reject(normalizeError(error))
  },
)

export default http

// ── Exponential-backoff retry helper ─────────────────────────────────────────
// Exposed for call-site wrappers that need extra control; the default axios
// instance already retries transient (5xx/network) failures for safe or
// idempotency-keyed requests via the response interceptor above.

export async function withRetry<T>(
  fn: () => Promise<T>,
  maxAttempts = 3,
  baseDelayMs = 500,
): Promise<T> {
  let attempt = 0
  while (true) {
    try {
      return await fn()
    } catch (err: any) {
      attempt++
      // Do not retry on client-side errors or auth failures
      const status = (err as NormalizedError).httpStatus ?? 0
      if (attempt >= maxAttempts || status === 401 || status === 403 || status === 409 || status === 422) {
        throw err
      }
      const delay = baseDelayMs * Math.pow(2, attempt - 1) * (0.9 + Math.random() * 0.2)
      await new Promise((res) => setTimeout(res, delay))
    }
  }
}
