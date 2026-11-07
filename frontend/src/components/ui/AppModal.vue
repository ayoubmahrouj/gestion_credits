<script setup lang="ts">
defineProps<{ open: boolean; title?: string; subtitle?: string; width?: string }>()
const emit = defineEmits<{ close: [] }>()
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 p-0 backdrop-blur-sm sm:items-center sm:p-6"
        @click.self="emit('close')"
      >
        <div
          class="card w-full max-h-[92vh] overflow-y-auto rounded-b-none sm:rounded-2xl"
          :class="width ?? 'sm:max-w-lg'"
        >
          <header class="flex items-start justify-between gap-4 border-b border-line/70 px-5 py-4">
            <div>
              <h3 class="text-base font-semibold">{{ title }}</h3>
              <p v-if="subtitle" class="mt-0.5 text-xs text-muted">{{ subtitle }}</p>
            </div>
            <button
              class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-ink"
              @click="emit('close')"
            >
              <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
              </svg>
            </button>
          </header>

          <div class="p-5">
            <slot />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
