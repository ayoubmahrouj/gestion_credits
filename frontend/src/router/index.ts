import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      component: () => import('../layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
        { path: 'reports', name: 'reports', component: () => import('../views/ReportsView.vue') },
        { path: 'clients', name: 'clients', component: () => import('../views/ClientsView.vue') },
        { path: 'clients/:id', name: 'client-detail', component: () => import('../views/ClientDetailView.vue') },
        { path: 'credits', name: 'credits', component: () => import('../views/CreditsView.vue') },
        { path: 'payments', name: 'payments', component: () => import('../views/PaymentsView.vue') },
        { path: 'products', name: 'products', component: () => import('../views/ProductsView.vue') },
        { path: 'sales', name: 'sales', component: () => import('../views/SalesView.vue') },
        { path: 'users', name: 'users', component: () => import('../views/UsersView.vue'), meta: { adminOnly: true } },
        { path: 'settings', name: 'settings', component: () => import('../views/SettingsView.vue') },
      ],
    },
    {
      path: '/print/sale/:id',
      name: 'ticket-sale',
      component: () => import('../views/TicketView.vue'),
      meta: { requiresAuth: true, ticket: 'sale' },
    },
    {
      path: '/print/payment/:id',
      name: 'ticket-payment',
      component: () => import('../views/TicketView.vue'),
      meta: { requiresAuth: true, ticket: 'payment' },
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth) {
    if (!auth.token) return { name: 'login' }

    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        await auth.logout()
        return { name: 'login' }
      }
    }
  }

  if (to.meta.guest && auth.token) {
    return { name: 'dashboard' }
  }

  if (to.meta.adminOnly && auth.user?.role !== 'admin') {
    return { name: 'dashboard' }
  }

  return true
})

export default router
