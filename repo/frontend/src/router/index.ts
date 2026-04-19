import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RoleName } from '@/types'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    roles?: RoleName[]
  }
}

const routes: RouteRecordRaw[] = [
  // ── Public ───────────────────────────────────────────────────────────────
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/LoginView.vue'),
    meta: { requiresAuth: false },
  },
  {
    path: '/unauthorized',
    name: 'unauthorized',
    component: () => import('@/views/UnauthorizedView.vue'),
    meta: { requiresAuth: false },
  },

  // ── Authenticated shell ───────────────────────────────────────────────────
  {
    path: '/',
    component: () => import('@/layouts/AuthLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
      },

      // Discussions
      {
        path: 'sections/:sectionId/threads',
        name: 'thread-list',
        component: () => import('@/views/discussions/ThreadListView.vue'),
      },
      {
        path: 'sections/:sectionId/threads/:threadId',
        name: 'thread-detail',
        component: () => import('@/views/discussions/ThreadDetailView.vue'),
      },

      // Notifications
      {
        path: 'notifications',
        name: 'notifications',
        component: () => import('@/views/notifications/NotificationCenterView.vue'),
      },
      {
        path: 'notifications/preferences',
        name: 'notification-preferences',
        component: () => import('@/views/notifications/NotificationPreferencesView.vue'),
      },

      // Orders & billing (student/all)
      {
        path: 'catalog',
        name: 'catalog',
        component: () => import('@/views/orders/CatalogView.vue'),
      },
      {
        path: 'orders',
        name: 'orders',
        component: () => import('@/views/orders/OrderListView.vue'),
      },
      {
        path: 'orders/:id',
        name: 'order-detail',
        component: () => import('@/views/orders/OrderDetailView.vue'),
      },
      {
        path: 'orders/:id/payment',
        name: 'order-payment',
        component: () => import('@/views/orders/PaymentView.vue'),
      },
      {
        path: 'orders/:id/receipt',
        name: 'order-receipt',
        component: () => import('@/views/orders/ReceiptView.vue'),
      },
      {
        path: 'bills',
        name: 'bills',
        component: () => import('@/views/billing/BillListView.vue'),
      },
      {
        path: 'bills/:id',
        name: 'bill-detail',
        component: () => import('@/views/billing/BillDetailView.vue'),
      },
      {
        path: 'bills/:id/refund',
        name: 'bill-refund',
        component: () => import('@/views/billing/RefundRequestView.vue'),
      },

      // Academic — teacher-scoped
      {
        path: 'grade-items',
        name: 'grade-items',
        component: () => import('@/views/academic/GradeItemsView.vue'),
        meta: { requiresAuth: true, roles: ['teacher', 'administrator'] },
      },

      // Academic — registrar-scoped
      {
        path: 'roster-import',
        name: 'roster-import',
        component: () => import('@/views/academic/RosterImportView.vue'),
        meta: { requiresAuth: true, roles: ['registrar', 'administrator'] },
      },

      // Admin
      {
        path: 'admin/moderation',
        name: 'admin-moderation',
        component: () => import('@/views/admin/ModerationQueueView.vue'),
        meta: { requiresAuth: true, roles: ['administrator'] },
      },
      {
        path: 'admin/billing',
        name: 'admin-billing',
        component: () => import('@/views/admin/BillingOversightView.vue'),
        meta: { requiresAuth: true, roles: ['administrator', 'registrar'] },
      },
      {
        path: 'admin/refunds',
        name: 'admin-refunds',
        component: () => import('@/views/admin/RefundReconciliationView.vue'),
        meta: { requiresAuth: true, roles: ['administrator', 'registrar'] },
      },
      {
        path: 'admin/health',
        name: 'admin-health',
        component: () => import('@/views/admin/HealthView.vue'),
        meta: { requiresAuth: true, roles: ['administrator'] },
      },
      {
        path: 'admin/diagnostics',
        name: 'admin-diagnostics',
        component: () => import('@/views/admin/DiagnosticsAdminView.vue'),
        meta: { requiresAuth: true, roles: ['administrator'] },
      },
      {
        path: 'admin/backups',
        name: 'admin-backups',
        component: () => import('@/views/admin/BackupAdminView.vue'),
        meta: { requiresAuth: true, roles: ['administrator'] },
      },
      {
        path: 'admin/dr',
        name: 'admin-dr',
        component: () => import('@/views/admin/DRAdminView.vue'),
        meta: { requiresAuth: true, roles: ['administrator'] },
      },
    ],
  },

  // ── Fallback ──────────────────────────────────────────────────────────────
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'home' },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Expired session: clear and redirect
  if (auth.isSessionExpired) {
    auth.clearSession()
    if (to.name !== 'login') return { name: 'login' }
    return
  }

  // Unauthenticated access to guarded route
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  // Hydrate user if token exists but user not loaded
  if (to.meta.requiresAuth && auth.isAuthenticated && !auth.user) {
    await auth.initSession()
    if (!auth.isAuthenticated) return { name: 'login' }
  }

  // Role-restricted route check
  if (to.meta.roles && to.meta.roles.length > 0) {
    const allowed = (to.meta.roles as RoleName[]).some((r) => auth.hasRole(r))
    if (!allowed) return { name: 'unauthorized' }
  }

  // Already-authenticated users skip login
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'home' }
  }
})

export default router
