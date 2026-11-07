<script setup lang="ts">
import { computed } from 'vue'
import { money } from '../../lib/format'

const props = defineProps<{
  data: Record<string, number>
  labels: Record<string, string>
}>()

const palette = ['#34d399', '#fbbf24', '#fb923c', '#f87171', '#c8a45c']

const entries = computed(() =>
  Object.entries(props.data).map(([key, value], index) => ({
    key,
    value,
    label: props.labels[key] ?? key,
    color: palette[index % palette.length],
  })),
)

const total = computed(() => entries.value.reduce((sum, e) => sum + e.value, 0))

const radius = 42
const circumference = 2 * Math.PI * radius

const segments = computed(() => {
  let offset = 0

  return entries.value
    .filter((e) => e.value > 0)
    .map((e) => {
      const fraction = total.value > 0 ? e.value / total.value : 0
      const dash = fraction * circumference
      const segment = { ...e, dash, offset }
      offset += dash

      return segment
    })
})
</script>

<template>
  <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:gap-6">
    <div class="relative size-40 shrink-0">
      <svg viewBox="0 0 100 100" class="size-full -rotate-90">
        <circle cx="50" cy="50" :r="radius" fill="none" stroke="var(--line)" stroke-width="9" />
        <circle
          v-for="segment in segments"
          :key="segment.key"
          cx="50"
          cy="50"
          :r="radius"
          fill="none"
          :stroke="segment.color"
          stroke-width="9"
          stroke-linecap="round"
          :stroke-dasharray="`${segment.dash} ${circumference - segment.dash}`"
          :stroke-dashoffset="-segment.offset"
        />
      </svg>
      <div class="absolute inset-0 grid place-items-center text-center">
        <div>
          <p class="text-[10px] uppercase tracking-widest text-muted">{{ $t('common.total') }}</p>
          <p class="text-sm font-bold">{{ money(total) }}</p>
        </div>
      </div>
    </div>

    <ul class="w-full space-y-2">
      <li v-for="segment in entries" :key="segment.key" class="flex items-center justify-between gap-3 text-xs">
        <span class="flex items-center gap-2 text-muted">
          <span class="size-2 rounded-full" :style="{ background: segment.color }" />
          {{ segment.label }}
        </span>
        <span class="font-semibold">{{ money(segment.value) }}</span>
      </li>
    </ul>
  </div>
</template>
