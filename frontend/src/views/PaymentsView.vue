<script setup lang="ts">
import { Download, Plus, Printer } from 'lucide-vue-next'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppModal from '../components/ui/AppModal.vue'
import api from '../lib/api'
import { downloadExcel } from '../lib/export'
import { dateTime, money } from '../lib/format'
import { useUiStore } from '../stores/ui'
import type { Client, Paginated, Payment } from '../types'

const { t } = useI18n()
const ui = useUiStore()

const payments = ref<Payment[]>([])
const clients = ref<Client[]>([])
const meta = ref<Paginated<Payment>['meta'] | null>(null)
const loading = ref(true)
const page = ref(1)

const modal = ref(false)
const saving = ref(false)
const formError = ref('')
const exporting = ref(false)
const donePayment = ref<Payment | null>(null)

const form = reactive({
  client_id: '' as number | '',
  amount: 0,
  method: 'cash',
  reference: '',
  date: todayIso(),
  notes: '',
})

function todayIso(): string {
  const now = new Date()

  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function nowTime(): string {
  const now = new Date()

  return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:00`
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get<Paginated<Payment>>('/payments', { params: { page: page.value, per_page: 15 } })
    payments.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

async function loadClients(): Promise<void> {
  const { data } = await api.get<Paginated<Client>>('/clients', { params: { per_page: 100 } })
  clients.value = data.data
}

function go(next: number): void {
  page.value = Math.min(Math.max(1, next), meta.value?.last_page ?? 1)
  load()
}

function openModal(): void {
  Object.assign(form, { client_id: '', amount: 0, method: 'cash', reference: '', date: todayIso(), notes: '' })
  formError.value = ''
  modal.value = true
}

async function submit(): Promise<void> {
  if (!form.client_id) {
    formError.value = t('common.required')

    return
  }

  saving.value = true
  formError.value = ''

  try {
    const { data } = await api.post<{ data: Payment }>(
      '/payments',
      {
        client_id: form.client_id,
        amount: form.amount,
        method: form.method,
        reference: form.reference || null,
        paid_at: `${form.date} ${nowTime()}`,
        notes: form.notes || null,
      },
      { headers: { 'Idempotency-Key': crypto.randomUUID() } },
    )

    modal.value = false
    donePayment.value = data.data
    ui.notify(t('payments.success'))
    await load()
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    formError.value = Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? err.response?.data?.message ?? t('common.error')
  } finally {
    saving.value = false
  }
}

function printPayment(payment: Payment): void {
  window.open(`${import.meta.env.BASE_URL}print/payment/${payment.id}`, '_blank', 'width=420,height=800')
}

function printDonePayment(): void {
  if (donePayment.value) {
    printPayment(donePayment.value)
  }
}

async function exportPayments(): Promise<void> {
  exporting.value = true

  try {
    await downloadExcel({ scope: 'payments' })
    ui.notify(t('reports.exported'))
  } catch {
    ui.notify(t('common.error'), 'error')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), loadClients()])
})
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('payments.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('payments.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton variant="outline" :loading="exporting" @click="exportPayments">
          <Download class="size-4" />
          {{ t('common.export_excel') }}
        </AppButton>

        <AppButton @click="openModal">
          <Plus class="size-4" />
          {{ t('payments.new') }}
        </AppButton>
      </div>
    </header>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>{{ t('common.client') }}</th>
              <th>{{ t('common.date') }}</th>
              <th>{{ t('payments.method') }}</th>
              <th>{{ t('payments.reference') }}</th>
              <th class="text-end">{{ t('common.amount') }}</th>
              <th class="w-12 text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</td>
            </tr>
            <tr v-for="payment in payments" v-else :key="payment.id">
              <td>
                <p class="font-semibold">{{ payment.client?.name ?? '—' }}</p>
                <p class="text-xs text-muted">{{ payment.client?.code }}</p>
              </td>
              <td class="text-muted">{{ dateTime(payment.paid_at) }}</td>
              <td><AppBadge :status="payment.method" /></td>
              <td class="text-muted">{{ payment.reference ?? '—' }}</td>
              <td class="text-end font-semibold text-success">+{{ money(payment.amount) }}</td>
              <td class="text-end">
                <button
                  type="button"
                  class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                  :title="t('common.print')"
                  @click="printPayment(payment)"
                >
                  <Printer class="size-3.5" />
                </button>
              </td>
            </tr>
            <tr v-if="!loading && payments.length === 0">
              <td colspan="6" class="py-12 text-center text-sm text-muted">{{ t('payments.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="meta" class="flex items-center justify-between gap-3 border-t border-line/70 px-4 py-3">
        <p class="text-[11px] text-muted">
          {{ t('common.page') }} {{ meta.current_page }} {{ t('common.of') }} {{ meta.last_page }} · {{ meta.total }}
        </p>
        <div class="flex gap-2">
          <AppButton size="sm" variant="outline" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">
            {{ t('common.prev') }}
          </AppButton>
          <AppButton size="sm" variant="outline" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">
            {{ t('common.next') }}
          </AppButton>
        </div>
      </footer>
    </div>

    <AppModal :open="modal" :title="t('payments.new')" :subtitle="t('payments.subtitle')" @close="modal = false">
      <form class="space-y-4" @submit.prevent="submit">
        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.client') }}</span>
          <select v-model="form.client_id" required class="field">
            <option value="">{{ t('common.select') }}</option>
            <option v-for="client in clients" :key="client.id" :value="client.id">
              {{ client.name }} — {{ money(client.balance) }}
            </option>
          </select>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.amount') }}</span>
            <input v-model.number="form.amount" type="number" min="0.01" step="0.01" required class="field" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('payments.method') }}</span>
            <select v-model="form.method" class="field">
              <option value="cash">{{ t('payments.methods.cash') }}</option>
              <option value="bank">{{ t('payments.methods.bank') }}</option>
              <option value="cheque">{{ t('payments.methods.cheque') }}</option>
              <option value="card">{{ t('payments.methods.card') }}</option>
              <option value="other">{{ t('payments.methods.other') }}</option>
            </select>
          </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('payments.reference') }}</span>
            <input v-model="form.reference" class="field" placeholder="CHQ-0001, VIR-…" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.date') }}</span>
            <input id="payment-date" name="date" v-model="form.date" type="date" class="field" />
          </label>
        </div>

        <p v-if="formError" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {{ formError }}
        </p>

        <div class="flex justify-end gap-2 pt-1">
          <AppButton variant="ghost" type="button" @click="modal = false">{{ t('common.cancel') }}</AppButton>
          <AppButton type="submit" :loading="saving">{{ t('payments.record') }}</AppButton>
        </div>
      </form>
    </AppModal>
    <AppModal :open="!!donePayment" :title="t('payments.saved')" :subtitle="donePayment?.client?.name" @close="donePayment = null">
      <div class="space-y-5">
        <p class="text-sm text-muted">{{ t('payments.saved_hint') }}</p>

        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="donePayment = null">{{ t('common.close') }}</AppButton>
          <AppButton v-if="donePayment" @click="printDonePayment">
            <Printer class="size-4" />
            {{ t('common.print') }}
          </AppButton>
        </div>
      </div>
    </AppModal>
  </div>
</template>
