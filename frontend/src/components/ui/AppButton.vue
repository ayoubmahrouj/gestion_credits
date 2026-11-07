<script setup lang="ts">
import { computed } from 'vue'
import { cn } from '../../lib/cn'

const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'ghost' | 'outline' | 'danger' | 'subtle'
    size?: 'sm' | 'md' | 'lg'
    type?: 'button' | 'submit'
    loading?: boolean
    block?: boolean
    disabled?: boolean
  }>(),
  { variant: 'primary', size: 'md', type: 'button', loading: false, block: false, disabled: false },
)

const classes = computed(() => {
  const base =
    'inline-flex items-center justify-center gap-2 rounded-xl font-semibold transition-all duration-200 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gold/60'

  const variants = {
    primary:
      'bg-gradient-to-b from-goldstrong to-gold text-[#0a0e13] shadow-[0_10px_30px_-12px_rgba(200,164,92,0.8)] hover:brightness-110 active:brightness-95',
    ghost: 'text-ink hover:bg-surface2',
    outline: 'border border-line bg-transparent text-ink hover:border-gold/60 hover:bg-goldsoft',
    danger: 'bg-danger/15 text-danger hover:bg-danger/25',
    subtle: 'bg-surface2 text-ink hover:bg-line/40',
  }

  const sizes = {
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2.5 text-sm',
    lg: 'px-5 py-3 text-sm',
  }

  return cn(base, variants[props.variant], sizes[props.size], props.block && 'w-full')
})
</script>

<template>
  <button :type="type" :class="classes" :disabled="disabled || loading">
    <svg
      v-if="loading"
      class="size-4 animate-spin"
      viewBox="0 0 24 24"
      fill="none"
      aria-hidden="true"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
      <path class="opacity-90" d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" />
    </svg>
    <slot />
  </button>
</template>
