<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../lib/cn'

const props = withDefaults(
  defineProps<{
    label: string
    value: string
    hint?: string
    tone?: 'default' | 'gold' | 'danger' | 'success'
    icon?: 'sales' | 'wallet' | 'alert' | 'cash' | 'clock' | 'users' | 'box' | 'star'
  }>(),
  { tone: 'default', icon: 'sales' },
)

const tones = {
  default: 'text-ink',
  gold: 'text-gold',
  danger: 'text-danger',
  success: 'text-success',
}

const iconWrap = computed(() => {
  const map = {
    default: 'bg-surface2 text-muted',
    gold: 'bg-goldsoft text-gold',
    danger: 'bg-danger/12 text-danger',
    success: 'bg-success/12 text-success',
  }

  return cn('grid size-10 place-items-center rounded-xl', map[props.tone])
})
</script>

<template>
  <div class="card card-hover group relative overflow-hidden p-5">
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="truncate text-[11px] font-semibold uppercase tracking-[0.16em] text-muted">
          {{ label }}
        </p>
        <p class="mt-2 text-2xl font-bold tracking-tight" :class="tones[tone]">{{ value }}</p>
        <p v-if="hint" class="mt-1 truncate text-xs text-muted">{{ hint }}</p>
      </div>

      <div :class="iconWrap">
        <svg v-if="icon === 'sales'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M3 3v18h18" stroke-linecap="round" />
          <path d="M7 14l4-4 3 3 5-6" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg v-else-if="icon === 'wallet'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="6" width="18" height="13" rx="3" />
          <path d="M16 12.5h2.5" stroke-linecap="round" />
        </svg>
        <svg v-else-if="icon === 'alert'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M12 4l9 16H3L12 4z" stroke-linejoin="round" />
          <path d="M12 10v4M12 17h.01" stroke-linecap="round" />
        </svg>
        <svg v-else-if="icon === 'cash'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="2.5" y="7" width="19" height="10" rx="2.5" />
          <circle cx="12" cy="12" r="2.4" />
        </svg>
        <svg v-else-if="icon === 'clock'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="12" cy="12" r="8.5" />
          <path d="M12 7.5V12l3 2" stroke-linecap="round" />
        </svg>
        <svg v-else-if="icon === 'users'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <circle cx="9" cy="8" r="3.2" />
          <path d="M3.5 19c.6-3.1 2.8-5 5.5-5s4.9 1.9 5.5 5" stroke-linecap="round" />
          <path d="M16 5.5a3 3 0 0 1 0 5.6M17.5 14.2c2 .6 3.2 2.2 3.6 4.8" stroke-linecap="round" />
        </svg>
        <svg v-else-if="icon === 'box'" class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" stroke-linejoin="round" />
          <path d="M4 7.5l8 4.5 8-4.5M12 12v9" />
        </svg>
        <svg v-else class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.8-5.2 2.8 1-5.8L3.5 9.7l5.9-.9L12 3.5z" stroke-linejoin="round" />
        </svg>
      </div>
    </div>

    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-px bg-gradient-to-r from-transparent via-gold/40 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" />
  </div>
</template>
