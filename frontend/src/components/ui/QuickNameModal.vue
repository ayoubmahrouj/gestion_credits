<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../lib/api'
import AppButton from './AppButton.vue'
import AppModal from './AppModal.vue'

const props = defineProps<{
  open: boolean
  title: string
  label: string
  endpoint: string
  placeholder?: string
}>()

const emit = defineEmits<{
  close: []
  created: [item: { id: number; name: string }]
}>()

const { t } = useI18n()
const name = ref('')
const saving = ref(false)
const error = ref('')
const input = ref<HTMLInputElement | null>(null)

watch(
  () => props.open,
  async (open) => {
    if (!open) return

    name.value = ''
    error.value = ''
    await nextTick()
    input.value?.focus()
  },
)

async function submit(): Promise<void> {
  const value = name.value.trim()

  if (!value) return

  saving.value = true
  error.value = ''

  try {
    const { data } = await api.post(props.endpoint, { name: value })
    emit('created', data.data)
    emit('close')
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value =
      Object.values(err.response?.data?.errors ?? {})[0]?.[0] ??
      err.response?.data?.message ??
      t('common.error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :open="open" :title="title" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="submit">
      <label class="block">
        <span class="mb-1.5 block text-xs font-semibold text-muted">{{ label }}</span>
        <input
          ref="input"
          v-model="name"
          class="field"
          :placeholder="placeholder ?? label"
          maxlength="100"
        />
      </label>

      <p v-if="error" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
        {{ error }}
      </p>

      <div class="flex justify-end gap-2 pt-1">
        <AppButton variant="ghost" type="button" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        <AppButton type="submit" :loading="saving" :disabled="!name.trim()">{{ t('common.save') }}</AppButton>
      </div>
    </form>
  </AppModal>
</template>
