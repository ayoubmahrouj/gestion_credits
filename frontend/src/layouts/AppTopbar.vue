<script setup lang="ts">
import { Globe, Menu, Moon, Sun } from 'lucide-vue-next'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const route = useRoute()
const ui = useUiStore()
const auth = useAuthStore()

const titles: Record<string, string> = {
  dashboard: 'nav.dashboard',
  reports: 'nav.reports',
  clients: 'nav.clients',
  'client-detail': 'nav.clients',
  credits: 'nav.credits',
  payments: 'nav.payments',
  sales: 'nav.sales',
  products: 'nav.products',
  users: 'nav.users',
  settings: 'nav.settings',
}

const title = computed(() => t(titles[route.name as string] ?? 'nav.dashboard'))
</script>

<template>
  <header
    class="sticky top-0 z-20 flex items-center gap-3 border-b border-line/70 bg-bg/80 px-4 py-3.5 backdrop-blur-xl sm:px-6 lg:px-8"
  >
    <button
      class="grid size-9 place-items-center rounded-xl border border-line text-muted transition hover:text-ink lg:hidden"
      @click="ui.toggleSidebar()"
    >
      <Menu class="size-4" />
    </button>

    <div class="min-w-0 flex-1">
      <p class="text-[10px] uppercase tracking-[0.2em] text-muted/70">{{ t('app.name') }}</p>
      <h1 class="truncate text-base font-semibold">{{ title }}</h1>
    </div>

    <button
      class="flex items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-xs font-semibold text-muted transition hover:border-gold/40 hover:text-gold"
      @click="ui.toggleLocale()"
    >
      <Globe class="size-4" />
      {{ ui.locale === 'fr' ? 'FR' : 'ع' }}
    </button>

    <button
      class="grid size-9 place-items-center rounded-xl border border-line text-muted transition hover:border-gold/40 hover:text-gold"
      @click="ui.toggleTheme()"
    >
      <Sun v-if="ui.theme === 'dark'" class="size-4" />
      <Moon v-else class="size-4" />
    </button>

    <div class="hidden items-center gap-3 border-s border-line ps-3 sm:flex">
      <div class="grid size-9 place-items-center rounded-full border border-gold/40 bg-goldsoft text-xs font-bold text-gold">
        {{ auth.initials || 'NA' }}
      </div>
      <div class="leading-tight">
        <p class="text-xs font-semibold">{{ auth.user?.name }}</p>
        <p class="text-[10px] uppercase tracking-widest text-muted">{{ auth.user?.role }}</p>
      </div>
    </div>
  </header>
</template>
