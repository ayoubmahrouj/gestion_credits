<script setup lang="ts">
defineProps<{
  title?: string
  subtitle?: string
  padded?: boolean
}>()
</script>

<template>
  <section class="card fade-up overflow-hidden">
    <header
      v-if="title || $slots.header || $slots.actions"
      class="flex items-start justify-between gap-4 border-b border-line/70 px-5 py-4"
    >
      <div>
        <slot name="header">
          <h2 class="text-sm font-semibold tracking-wide">{{ title }}</h2>
          <p v-if="subtitle" class="mt-0.5 text-xs text-muted">{{ subtitle }}</p>
        </slot>
      </div>
      <div v-if="$slots.actions" class="flex items-center gap-2">
        <slot name="actions" />
      </div>
    </header>

    <div :class="padded === false ? '' : 'p-5'">
      <slot />
    </div>
  </section>
</template>
