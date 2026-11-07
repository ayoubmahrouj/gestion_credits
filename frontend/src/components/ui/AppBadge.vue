<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ status: string }>()

const map: Record<string, string> = {
  active: 'bg-success/12 text-success border-success/25',
  paid: 'bg-success/12 text-success border-success/25',
  settled: 'bg-success/12 text-success border-success/25',
  partial: 'bg-warn/12 text-warn border-warn/25',
  pending: 'bg-warn/12 text-warn border-warn/25',
  overdue: 'bg-danger/12 text-danger border-danger/25',
  unpaid: 'bg-danger/12 text-danger border-danger/25',
  blocked: 'bg-danger/12 text-danger border-danger/25',
  cancelled: 'bg-muted/12 text-muted border-line',
  archived: 'bg-muted/12 text-muted border-line',
  disabled: 'bg-muted/12 text-muted border-line',
}

const classes = computed(() => map[props.status] ?? 'bg-surface2 text-muted border-line')
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold capitalize"
    :class="classes"
  >
    <span class="size-1.5 rounded-full bg-current" />
    {{ $t(`status.${status}`) }}
  </span>
</template>
