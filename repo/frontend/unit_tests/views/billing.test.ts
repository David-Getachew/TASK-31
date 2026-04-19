import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'

vi.mock('../../src/adapters/http', () => ({
  default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
  generateIdempotencyKey: vi.fn(() => 'test-idempotency-key'),
}))
vi.mock('../../src/adapters/bills', () => ({
  billsAdapter: {
    mine:           vi.fn(),
    get:            vi.fn(),
    adminList:      vi.fn(),
    adminGenerate:  vi.fn(),
    listSchedules:  vi.fn(),
    listRefunds:    vi.fn(),
    getRefund:      vi.fn(),
    createRefund:   vi.fn(),
    reasonCodes:    vi.fn().mockResolvedValue({ data: { data: [] } }),
  },
}))

import { billsAdapter }    from '../../src/adapters/bills'
import { useBillingStore } from '../../src/stores/billing'

describe('BillingStore', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('fetchMyBills populates bills on success', async () => {
    vi.mocked(billsAdapter.mine).mockResolvedValueOnce({
      data: {
        data: [{ id: 1, user_id: 1, type: 'initial', status: 'open', total_cents: 5000, paid_cents: 0, refunded_cents: 0, subtotal_cents: 4500, tax_cents: 500, issued_on: '2026-01-01', due_on: '2026-01-15', lines: [] }],
        meta: { page: 1, per_page: 20, total: 1, last_page: 1 },
      },
    } as any)
    const store = useBillingStore()
    await store.fetchMyBills()
    expect(store.bills).toHaveLength(1)
    expect(store.bills[0].total_cents).toBe(5000)
  })

  it('requestRefund sets conflict on REFUND_EXCEEDS_BALANCE', async () => {
    vi.mocked(billsAdapter.createRefund).mockRejectedValueOnce({
      code: 'REFUND_EXCEEDS_BALANCE', message: 'Exceeds', httpStatus: 422,
    })
    const store  = useBillingStore()
    const result = await store.requestRefund(1, { amount_cents: 99999, reason_code: 'OVERPAYMENT' })
    expect(result).toBeNull()
    expect(store.conflict).toContain('balance')
  })

  it('requestRefund sets conflict on IDEMPOTENCY_KEY_CONFLICT', async () => {
    vi.mocked(billsAdapter.createRefund).mockRejectedValueOnce({
      code: 'IDEMPOTENCY_KEY_CONFLICT', message: 'Duplicate', httpStatus: 409,
    })
    const store  = useBillingStore()
    const result = await store.requestRefund(1, { amount_cents: 100, reason_code: 'X' })
    expect(result).toBeNull()
    expect(store.conflict).toContain('already submitted')
  })

  it('generateBill sets conflict on IDEMPOTENCY_KEY_CONFLICT', async () => {
    vi.mocked(billsAdapter.adminGenerate).mockRejectedValueOnce({
      code: 'IDEMPOTENCY_KEY_CONFLICT', message: 'Duplicate', httpStatus: 409,
    })
    const store  = useBillingStore()
    const result = await store.generateBill({ user_id: 1, type: 'supplemental' })
    expect(result).toBeNull()
    expect(store.conflict).toContain('already generated')
  })
})
