<script setup lang="ts">
import {
  BarChart3,
  LayoutDashboard,
  LogOut,
  Package,
  Receipt,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Users,
  Wallet,
} from 'lucide-vue-next'
import { computed } from 'vue'
import type { Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const ui = useUiStore()
const auth = useAuthStore()

interface NavItem {
  name: string
  to: string
  label: string
  icon: Component
  adminOnly?: boolean
}

const items: NavItem[] = [
  { name: 'dashboard', to: '/', label: 'nav.dashboard', icon: LayoutDashboard },
  { name: 'reports', to: '/reports', label: 'nav.reports', icon: BarChart3 },
  { name: 'clients', to: '/clients', label: 'nav.clients', icon: Users },
  { name: 'credits', to: '/credits', label: 'nav.credits', icon: Wallet },
  { name: 'payments', to: '/payments', label: 'nav.payments', icon: Receipt },
  { name: 'sales', to: '/sales', label: 'nav.sales', icon: ShoppingCart },
  { name: 'products', to: '/products', label: 'nav.products', icon: Package },
  { name: 'users', to: '/users', label: 'nav.users', icon: ShieldCheck, adminOnly: true },
  { name: 'settings', to: '/settings', label: 'nav.settings', icon: Settings },
]

const visibleItems = computed(() =>
  items.filter((item) => !item.adminOnly || auth.user?.role === 'admin'),
)

function isActive(name: string): boolean {
  return route.name === name
}

async function logout(): Promise<void> {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-200"
    enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-200"
    leave-to-class="opacity-0"
  >
    <div
      v-if="ui.sidebarOpen"
      class="fixed inset-0 z-30 bg-black/60 backdrop-blur-sm lg:hidden"
      @click="ui.closeSidebar()"
    />
  </Transition>

  <aside
    class="fixed inset-y-0 start-0 z-40 flex w-[276px] flex-col border-e border-line bg-surface/95 backdrop-blur-xl transition-transform duration-300 lg:static lg:translate-x-0"
    :class="ui.sidebarOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full lg:!translate-x-0'"
  >
    <div class="flex items-center gap-3 px-5 py-6">
      <div class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-goldstrong to-gold text-lg font-extrabold text-[#0a0e13] shadow-[0_10px_30px_-12px_rgba(200,164,92,0.9)]">
        C
      </div>
      <div class="min-w-0">
        <p class="truncate text-sm font-bold tracking-wide">{{ t('app.name') }}</p>
        <p class="truncate text-[11px] text-muted">{{ t('app.tagline') }}</p>
      </div>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
      <p class="px-3 pb-2 text-[10px] font-semibold uppercase tracking-[0.22em] text-muted/70">
        {{ t('app.sub') }}
      </p>

      <RouterLink
        v-for="item in visibleItems"
        :key="item.name"
        :to="item.to"
        class="group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-muted transition-all duration-200 hover:bg-surface2 hover:text-ink"
        :class="isActive(item.name) && 'bg-goldsoft text-gold'"
        @click="ui.closeSidebar()"
      >
        <span
          v-if="isActive(item.name)"
          class="absolute inset-y-2 start-0 w-[3px] rounded-full bg-gold shadow-[0_0_12px_var(--gold)]"
        />
        <component
          :is="item.icon"
          class="size-[18px] transition-transform duration-200 group-hover:scale-105"
          :class="isActive(item.name) ? 'text-gold' : 'text-muted'"
        />
        <span>{{ t(item.label) }}</span>
      </RouterLink>
    </nav>

    <div class="border-t border-line/70 p-3">
      <div class="flex items-center gap-3 rounded-xl bg-surface2/70 p-3">
        <div class="grid size-9 place-items-center rounded-full border border-gold/40 bg-goldsoft text-xs font-bold text-gold">
          {{ auth.initials || 'NA' }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-semibold">{{ auth.user?.name }}</p>
          <p class="truncate text-[10px] uppercase tracking-widest text-muted">{{ auth.user?.role }}</p>
        </div>
        <button
          class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-danger/15 hover:text-danger"
          :title="t('common.close')"
          @click="logout"
        >
          <LogOut class="size-4" />
        </button>
      </div>
      <p class="mt-3 text-center text-[10px] text-muted/60">v0.1 · Premium Edition</p>
    </div>
  </aside>
</template>
