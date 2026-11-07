<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../lib/api'
import type { Client } from '../../types'
import AppButton from '../ui/AppButton.vue'
import AppModal from '../ui/AppModal.vue'

const props = defineProps<{ open: boolean; client?: Client | null }>()

const emit = defineEmits<{
  close: []
  saved: [client: Client]
}>()

const { t } = useI18n()

const saving = ref(false)
const error = ref('')

const form = reactive({
  name: '',
  type: 'individual' as string,
  phone: '',
  whatsapp: '',
  email: '',
  city: '',
  credit_limit: 0,
  status: 'active' as string,
})

function reset(): void {
  const c = props.client

  Object.assign(form, {
    name: c?.name ?? '',
    type: c?.type ?? 'individual',
    phone: c?.phone ?? '',
    whatsapp: c?.whatsapp ?? '',
    email: c?.email ?? '',
    city: c?.city ?? '',
    credit_limit: c?.credit_limit ?? 0,
    status: c?.status ?? 'active',
  })

  error.value = ''
}

watch(
  () => props.open,
  (open) => {
    if (open) reset()
  },
)

async function submit(): Promise<void> {
  saving.value = true
  error.value = ''

  try {
    const { data } = props.client
      ? await api.put(`/clients/${props.client.id}`, form)
      : await api.post('/clients', form)

    emit('saved', data.data)
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
  <AppModal
    :open="open"
    :title="client ? t('common.edit') : t('clients.new')"
    :subtitle="client ? client.code : t('clients.subtitle')"
    @close="emit('close')"
  >
    <form class="space-y-4" @submit.prevent="submit">
      <div class="grid gap-4 sm:grid-cols-2">
        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('clients.name') }}</span>
          <input id="client-name" name="name" v-model="form.name" required class="field" :placeholder="t('clients.name_ph')" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('clients.type') }}</span>
          <select id="client-type" name="type" v-model="form.type" class="field">
            <option value="individual">{{ t('clients.individual') }}</option>
            <option value="company">{{ t('clients.company') }}</option>
          </select>
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.phone') }}</span>
          <input id="client-phone" name="phone" v-model="form.phone" class="field" :placeholder="t('clients.phone_ph')" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">WhatsApp</span>
          <input id="client-whatsapp" name="whatsapp" v-model="form.whatsapp" class="field" :placeholder="t('clients.phone_ph')" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('clients.city') }}</span>
          <input id="client-city" name="city" v-model="form.city" class="field" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('clients.credit_limit') }}</span>
          <input id="client-limit" name="credit_limit" v-model.number="form.credit_limit" type="number" min="0" step="0.01" class="field" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.status') }}</span>
          <select id="client-status-form" name="client_status" v-model="form.status" class="field">
            <option value="active">{{ t('status.active') }}</option>
            <option value="blocked">{{ t('status.blocked') }}</option>
            <option value="archived">{{ t('status.archived') }}</option>
          </select>
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-semibold text-muted">Email</span>
          <input id="client-email" name="email" v-model="form.email" type="email" class="field" />
        </label>
      </div>

      <p v-if="error" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
        {{ error }}
      </p>

      <div class="flex justify-end gap-2 pt-1">
        <AppButton variant="ghost" type="button" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        <AppButton type="submit" :loading="saving">{{ t('common.save') }}</AppButton>
      </div>
    </form>
  </AppModal>
</template>
