import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia, type Pinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent } from 'vue'

vi.mock('../../src/adapters/http', () => ({ default: { get: vi.fn(), post: vi.fn() } }))
vi.mock('../../src/adapters/dashboard', () => ({
  dashboardAdapter: { summary: vi.fn() },
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

import { dashboardAdapter } from '../../src/adapters/dashboard'
import { useDashboardStore } from '../../src/stores/dashboard'
import { useAuthStore }      from '../../src/stores/auth'
import StudentDashboard from '../../src/views/dashboard/StudentDashboard.vue'
import AdminDashboard   from '../../src/views/dashboard/AdminDashboard.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/', name: 'home', component: defineComponent({ template: '<div />' }) },
    { path: '/bills', name: 'bills', component: defineComponent({ template: '<div />' }) },
    { path: '/notifications', name: 'notifications', component: defineComponent({ template: '<div />' }) },
    { path: '/orders', name: 'orders', component: defineComponent({ template: '<div />' }) },
    { path: '/admin/health', name: 'admin-health', component: defineComponent({ template: '<div />' }) },
    { path: '/admin/moderation', name: 'admin-moderation', component: defineComponent({ template: '<div />' }) },
    { path: '/admin/billing', name: 'admin-billing', component: defineComponent({ template: '<div />' }) },
    { path: '/admin/refunds', name: 'admin-refunds', component: defineComponent({ template: '<div />' }) },
  ],
})

function mountWithPinia(component: any, pinia: Pinia) {
  return mount(component, { global: { plugins: [pinia, router] } })
}

describe('StudentDashboard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows loading spinner while fetching', () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    vi.mocked(dashboardAdapter.summary).mockReturnValue(new Promise(() => {}))
    const store = useDashboardStore()
    store.loading = true
    const wrapper = mountWithPinia(StudentDashboard, pinia)
    expect(wrapper.text()).toContain('Loading your dashboard')
  })

  it('shows error state when fetch fails', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    vi.mocked(dashboardAdapter.summary).mockRejectedValueOnce({ message: 'Server error' })
    const wrapper = mountWithPinia(StudentDashboard, pinia)
    await new Promise((r) => setTimeout(r, 10))
    expect(wrapper.text()).toContain('Server error')
  })

  it('renders summary cards when data is available', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    vi.mocked(dashboardAdapter.summary).mockResolvedValueOnce({
      data: { data: { enrolled_sections: 3, open_bills: 2, unread_notifications: 5, pending_orders: 1 } },
    } as any)
    const wrapper = mountWithPinia(StudentDashboard, pinia)
    await new Promise((r) => setTimeout(r, 10))
    expect(wrapper.text()).toContain('Enrolled Sections')
    expect(wrapper.text()).toContain('Open Bills')
    expect(wrapper.text()).toContain('3')
  })
})

describe('AdminDashboard', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('shows circuit breaker alert when circuit is open', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    vi.mocked(dashboardAdapter.summary).mockResolvedValueOnce({
      data: { data: { circuit_status: 'open', moderation_queue_size: 0, reconciliation_flags: 0 } },
    } as any)
    const wrapper = mountWithPinia(AdminDashboard, pinia)
    await new Promise((r) => setTimeout(r, 10))
    expect(wrapper.text()).toContain('read-only mode')
  })

  it('shows moderation queue count', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)
    vi.mocked(dashboardAdapter.summary).mockResolvedValueOnce({
      data: { data: { moderation_queue_size: 7, reconciliation_flags: 3 } },
    } as any)
    const wrapper = mountWithPinia(AdminDashboard, pinia)
    await new Promise((r) => setTimeout(r, 10))
    expect(wrapper.text()).toContain('Moderation Queue')
    expect(wrapper.text()).toContain('7')
  })
})
