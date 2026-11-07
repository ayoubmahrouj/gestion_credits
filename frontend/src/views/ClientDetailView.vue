<script setup lang="ts">
import { ArrowLeft, Phone, MapPin, MessageCircle } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import AppBadge from '../components/ui/AppBadge.vue'
import AppCard from '../components/ui/AppCard.vue'
import api from '../lib/api'
import { dateTime, money, shortDate } from '../lib/format'
import type { Client, Credit, Payment } from '../types'

const { t } = useI18n()
const route = useRoute()

const client = ref<Client | null>(null)
const credits = ref<Credit[]>([])
const payments = ref<Payment[]>([])
const summary = ref({ credit_total: 0, paid_total: 0, overdue_total: 0 })
const loading = ref(true)

const initials = computed(() =>
  (client.value?.name ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join(''),
)

const ring = computed(() => {
  const score = client.value?.score ?? 0
  const circumference = 2 * Math.PI * 34

  return {
    circumference,
    dash: (score / 100) * circumference,
    color: score >= 60 ? 'var(--success)' : score >= 40 ? 'var(--warn)' : 'var(--danger)',
  }
})

function progress(credit: Credit): number {
  const total = Number(credit.total_due) || 0
  if (total <= 0) return 0

  return Math.min(100, Math.round((Number(credit.amount_paid) / total) * 100))
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get(`/clients/${route.params.id}`)
    client.value = data.data
    credits.value = data.credits ?? []
    payments.value = data.payments ?? []
    summary.value = data.summary
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <RouterLink :to="{ name: 'clients' }" class="inline-flex items-center gap-2 text-xs font-semibold text-muted transition hover:text-gold">
      <ArrowLeft class="size-4 rtl:rotate-180" />
      {{ t('common.back') }}
    </RouterLink>

    <div v-if="loading" class="card p-10 text-center text-sm text-muted">{{ t('common.loading') }}</div>

    <template v-if="client">
      <section class="card overflow-hidden">
        <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center">
          <div class="grid size-16 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-goldstrong to-gold text-xl font-extrabold text-[#0a0e13]">
            {{ initials }}
          </div>

          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-xl font-bold tracking-tight">{{ client.name }}</h2>
              <AppBadge :status="client.status" />
              <span class="rounded-full border border-line px-2 py-0.5 text-[10px] uppercase tracking-widest text-muted">
                {{ client.code }}
              </span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted">
              <span class="inline-flex items-center gap-1.5"><Phone class="size-3.5" /> {{ client.phone ?? '—' }}</span>
              <span class="inline-flex items-center gap-1.5"><MessageCircle class="size-3.5" /> {{ client.whatsapp ?? '—' }}</span>
              <span class="inline-flex items-center gap-1.5"><MapPin class="size-3.5" /> {{ client.city ?? '—' }}</span>
              <span>{{ client.type === 'company' ? t('clients.company') : t('clients.individual') }}</span>
            </div>
          </div>

          <div class="flex items-center gap-5">
            <div class="relative size-[92px]">
              <svg viewBox="0 0 80 80" class="size-full -rotate-90">
                <circle cx="40" cy="40" r="34" fill="none" stroke="var(--line)" stroke-width="6" />
                <circle
                  cx="40"
                  cy="40"
                  r="34"
                  fill="none"
                  :stroke="ring.color"
                  stroke-width="6"
                  stroke-linecap="round"
                  :stroke-dasharray="`${ring.dash} ${ring.circumference}`"
                />
              </svg>
              <div class="absolute inset-0 grid place-items-center">
                <div class="text-center">
                  <p class="text-lg font-bold">{{ client.score }}</p>
                  <p class="text-[9px] uppercase tracking-widest text-muted">{{ t('clients.score') }}</p>
                </div>
              </div>
            </div>

            <div class="text-end">
              <p class="text-[10px] uppercase tracking-widest text-muted">{{ t('clients.balance') }}</p>
              <p class="text-2xl font-bold text-gold">{{ money(client.balance) }}</p>
              <p class="text-[11px] text-muted">{{ t('clients.limit') }} · {{ money(client.credit_limit) }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
          <p class="text-[11px] uppercase tracking-[0.16em] text-muted">{{ t('client_detail.total_credit') }}</p>
          <p class="mt-2 text-xl font-bold">{{ money(summary.credit_total) }}</p>
        </div>
        <div class="card p-5">
          <p class="text-[11px] uppercase tracking-[0.16em] text-muted">{{ t('client_detail.total_paid') }}</p>
          <p class="mt-2 text-xl font-bold text-success">{{ money(summary.paid_total) }}</p>
        </div>
        <div class="card p-5">
          <p class="text-[11px] uppercase tracking-[0.16em] text-muted">{{ t('client_detail.overdue_total') }}</p>
          <p class="mt-2 text-xl font-bold text-danger">{{ money(summary.overdue_total) }}</p>
        </div>
      </section>

      <div class="grid gap-4 xl:grid-cols-2">
        <AppCard :title="t('client_detail.credits')" :padded="false">
          <div class="divide-y divide-line/70">
            <article v-for="credit in credits" :key="credit.id" class="p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold">{{ credit.sale?.number ?? `#${credit.id}` }}</p>
                  <p class="text-[11px] text-muted">{{ shortDate(credit.start_date) }} → {{ shortDate(credit.due_date) }}</p>
                </div>
                <AppBadge :status="credit.status" />
              </div>

              <div class="mt-3 flex items-center justify-between text-xs">
                <span class="text-muted">{{ money(credit.amount_paid) }} / {{ money(credit.total_due) }}</span>
                <span class="font-semibold">{{ progress(credit) }}%</span>
              </div>
              <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface2">
                <div class="h-full rounded-full bg-gradient-to-r from-goldstrong to-gold" :style="{ width: `${progress(credit)}%` }" />
              </div>

              <div v-if="credit.installments?.length" class="mt-3 flex flex-wrap gap-1.5">
                <span
                  v-for="installment in credit.installments"
                  :key="installment.id"
                  class="rounded-md border px-2 py-1 text-[10px] font-semibold"
                  :class="installment.status === 'paid'
                    ? 'border-success/30 bg-success/10 text-success'
                    : installment.is_overdue
                      ? 'border-danger/30 bg-danger/10 text-danger'
                      : 'border-line text-muted'"
                >
                  #{{ installment.number }} · {{ shortDate(installment.due_date) }}
                </span>
              </div>
            </article>

            <p v-if="credits.length === 0" class="p-8 text-center text-sm text-muted">{{ t('client_detail.no_credits') }}</p>
          </div>
        </AppCard>

        <AppCard :title="t('client_detail.payments')" :padded="false">
          <div class="overflow-x-auto">
            <table class="table-base">
              <thead>
                <tr>
                  <th>{{ t('common.date') }}</th>
                  <th>{{ t('payments.method') }}</th>
                  <th class="text-end">{{ t('common.amount') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="payment in payments" :key="payment.id">
                  <td class="text-muted">{{ dateTime(payment.paid_at) }}</td>
                  <td><AppBadge :status="payment.method" /></td>
                  <td class="text-end font-semibold text-success">+{{ money(payment.amount) }}</td>
                </tr>
                <tr v-if="payments.length === 0">
                  <td colspan="3" class="py-10 text-center text-sm text-muted">{{ t('client_detail.no_payments') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>
      </div>
    </template>
  </div>
</template>
