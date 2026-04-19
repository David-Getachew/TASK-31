import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia, createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { defineComponent } from 'vue'

vi.mock('../../src/adapters/http', () => ({
  default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('../../src/offline/cache', () => ({
  CacheStore: vi.fn().mockImplementation(() => ({ set: vi.fn(), get: vi.fn().mockResolvedValue(null) })),
}))
vi.mock('../../src/offline/queue', () => ({
  PendingQueue: vi.fn().mockImplementation(() => ({
    enqueue: vi.fn(), dequeue: vi.fn(), getAll: vi.fn().mockResolvedValue([]), update: vi.fn(),
  })),
}))
vi.mock('../../src/adapters/notifications', () => ({
  notificationsAdapter: { unreadCount: vi.fn().mockResolvedValue({ data: { data: {} } }) },
}))

import http from '../../src/adapters/http'
import { useAuthStore } from '../../src/stores/auth'
import GradeItemsView  from '../../src/views/academic/GradeItemsView.vue'
import RosterImportView from '../../src/views/academic/RosterImportView.vue'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', name: 'home', component: defineComponent({ template: '<div />' }) }],
})

describe('GradeItemsView', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders empty state when no items', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const auth = useAuthStore()
    auth.setSession('test-token', new Date(Date.now() + 60_000).toISOString())
    auth.setUser({
      id: 1,
      name: 'Teacher',
      email: 'teacher@example.com',
      roles: [{ name: 'teacher', scope_type: 'section', scope_id: 1 }],
    })

    vi.mocked(http.get).mockResolvedValue({
      data: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    } as any)

    const wrapper = mount(GradeItemsView, { global: { plugins: [pinia, router] } })
    await new Promise((r) => setTimeout(r, 20))
    expect(wrapper.text()).toContain('No grade items')
  })

  it('shows Publish button for draft items', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const auth = useAuthStore()
    auth.setSession('test-token', new Date(Date.now() + 60_000).toISOString())
    auth.setUser({
      id: 1,
      name: 'Teacher',
      email: 'teacher@example.com',
      roles: [{ name: 'teacher', scope_type: 'section', scope_id: 1 }],
    })

    vi.mocked(http.get).mockResolvedValue({
      data: {
        data: [{
          id: 1, title: 'Midterm', max_score: 100, weight_bps: 3000, state: 'draft',
          published_at: null, section_id: 1,
        }],
        meta: { page: 1, per_page: 20, total: 1, last_page: 1 },
      },
    } as any)

    const wrapper = mount(GradeItemsView, { global: { plugins: [pinia, router] } })
    await new Promise((r) => setTimeout(r, 20))
    expect(wrapper.text()).toContain('Publish')
  })
})

describe('RosterImportView', () => {
  beforeEach(() => setActivePinia(createPinia()))

  it('renders file input for upload', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const auth = useAuthStore()
    auth.setSession('test-token', new Date(Date.now() + 60_000).toISOString())
    auth.setUser({
      id: 2,
      name: 'Registrar',
      email: 'registrar@example.com',
      roles: [{ name: 'registrar', scope_type: 'global', scope_id: null }],
    })

    vi.mocked(http.get).mockResolvedValue({
      data: { data: [], meta: { page: 1, per_page: 20, total: 0, last_page: 1 } },
    } as any)

    const wrapper = mount(RosterImportView, { global: { plugins: [pinia, router] } })
    await new Promise((r) => setTimeout(r, 20))
    expect(wrapper.find('input[type="file"]').exists()).toBe(true)
  })

  it('shows error table when import has errors', async () => {
    const pinia = createPinia()
    setActivePinia(pinia)

    const auth = useAuthStore()
    auth.setSession('test-token', new Date(Date.now() + 60_000).toISOString())
    auth.setUser({
      id: 2,
      name: 'Registrar',
      email: 'registrar@example.com',
      roles: [{ name: 'registrar', scope_type: 'global', scope_id: null }],
    })

    vi.mocked(http.get).mockResolvedValue({
      data: {
        data: [{
          id: 1, source_filename: 'roster.csv', term_id: 1, status: 'completed',
          success_count: 8, row_count: 10, error_count: 2,
          completed_at: new Date().toISOString(),
          errors: [{ row: 3, field: 'email', message: 'invalid format' }],
        }],
        meta: { page: 1, per_page: 20, total: 1, last_page: 1 },
      },
    } as any)

    const wrapper = mount(RosterImportView, { global: { plugins: [pinia, router] } })
    await new Promise((r) => setTimeout(r, 20))
    expect(wrapper.text()).toContain('View errors')
  })
})
