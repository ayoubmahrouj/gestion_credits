<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useUiStore } from '../stores/ui'
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'

const ui = useUiStore()

onMounted(() => ui.init())
</script>

<template>
  <div class="relative z-10 flex min-h-screen">
    <AppSidebar />

    <div class="flex min-w-0 flex-1 flex-col">
      <AppTopbar />

      <main class="flex-1 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <RouterView v-slot="{ Component }">
          <component :is="Component" />
        </RouterView>
      </main>
    </div>

    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-3"
      leave-active-class="transition duration-200 ease-in"
      leave-to-class="opacity-0 translate-y-3"
    >
      <div
        v-if="ui.toast"
        class="fixed bottom-5 end-5 z-[60] flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium shadow-2xl backdrop-blur-xl"
        :class="ui.toast.type === 'success'
          ? 'border-success/30 bg-success/10 text-success'
          : 'border-danger/30 bg-danger/10 text-danger'"
      >
        {{ ui.toast.message }}
      </div>
    </Transition>
  </div>
</template>
