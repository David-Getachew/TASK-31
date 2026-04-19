export type RoleName = 'student' | 'teacher' | 'registrar' | 'administrator'

export interface Role {
  name: RoleName
  scope_type?: string
  scope_id?: number | null
}

export interface User {
  id: number
  name: string
  email: string
  roles: Role[]
  last_login_at?: string | null
}

export interface ApiResponse<T> {
  data: T
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    page: number
    per_page: number
    total: number
    last_page: number
  }
}

export type ApiErrorCode =
  | 'BAD_REQUEST'
  | 'IDEMPOTENCY_KEY_REQUIRED'
  | 'UNAUTHENTICATED'
  | 'INVALID_CREDENTIALS'
  | 'ACCOUNT_LOCKED'
  | 'FORBIDDEN'
  | 'NOT_FOUND'
  | 'METHOD_NOT_ALLOWED'
  | 'IDEMPOTENCY_KEY_CONFLICT'
  | 'INVALID_STATE_TRANSITION'
  | 'VALIDATION_FAILED'
  | 'SENSITIVE_WORDS_BLOCKED'
  | 'REFUND_EXCEEDS_BALANCE'
  | 'EDIT_WINDOW_EXPIRED'
  | 'RATE_LIMITED'
  | 'SERVICE_UNAVAILABLE'
  | 'INTERNAL_ERROR'

export interface ApiError {
  code: ApiErrorCode
  message: string
  details?: Record<string, string[]>
  blocked_terms?: Array<{ term: string; start: number; end: number }>
}
