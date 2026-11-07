<script setup lang="ts">
import { computed } from 'vue'
import { num } from '../../lib/format'

const props = defineProps<{
  data: { label: string; total: number }[]
  height?: number
}>()

const max = computed(() => Math.max(1, ...props.data.map((d) => d.total)))

function barHeight(value: number): string {
  if (value <= 0) return '3px'

  return `${Math.max(4, (value / max.value) * 100)}%`
}
</script>

<template>
  <div class="relative">
    <div class="pointer-events-none absolute inset-x-0 top-0 flex flex-col justify-between" :style="{ height: `${height ?? 180}px` }">
      <div v-for="i in 4" :key="i" class="border-t border-dashed border-line/60" />
    </div>

    <div class="relative flex items-end gap-2 sm:gap-4">
      <div v-for="item in data" :key="item.label" class="group flex flex-1 flex-col items-center">
        <div class="flex w-full flex-col items-center justify-end" :style="{ height: `${height ?? 180}px` }">
          <span class="mb-1.5 text-[10px] font-semibold text-muted transition-colors group-hover:text-gold">
            {{ num(item.total) }}
          </span>
          <div
            class="w-full max-w-[44px] rounded-t-md transition-all duration-300"
            :class="item.total > 0
              ? 'bg-gradient-to-t from-gold/60 via-gold/85 to-goldstrong shadow-[0_0_28px_-8px_var(--gold)] group-hover:to-[#f0dcae]'
              : 'bg-line'"
            :style="{ height: barHeight(item.total) }"
          />
        </div>
        <span class="mt-2.5 text-[10px] font-medium uppercase tracking-widest text-muted">{{ item.label }}</span>
      </div>
    </div>
  </div>
</template>
