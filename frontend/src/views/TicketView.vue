<script setup lang="ts">
import { Printer, X } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import api from '../lib/api'
import { dateTime, money, shortDate } from '../lib/format'
import type { Payment, Sale } from '../types'

interface Shop {
  shop_name: string
  shop_tagline: string
  shop_phone: string
  shop_address: string
  shop_ice: string
  ticket_footer: string
  currency: string
}

const { t } = useI18n()
const route = useRoute()
const router = useRouter()

const kind = computed<'sale' | 'payment'>(() => (route.meta.ticket === 'payment' ? 'payment' : 'sale'))

const loading = ref(true)
const error = ref('')
const sale = ref<Sale | null>(null)
const payment = ref<Payment | null>(null)

const shop = ref<Shop>({
  shop_name: 'Gestion Crédits',
  shop_tagline: '',
  shop_phone: '',
  shop_address: '',
  shop_ice: '',
  ticket_footer: '',
  currency: 'DH',
})

onMounted(async () => {
  try {
    const shopRes = await api.get('/settings')
    shop.value = { ...shop.value, ...shopRes.data.data }

    const { data } = await api.get(`${kind.value === 'sale' ? 'sales' : 'payments'}/${route.params.id}`)

    if (kind.value === 'sale') {
      sale.value = data.data
    } else {
      payment.value = data.data
    }
  } catch {
    error.value = t('ticket.not_found')
  } finally {
    loading.value = false
  }
})

const previousBalance = computed(() => {
  if (!payment.value) return 0

  return Number(payment.value.client?.balance ?? 0) + Number(payment.value.amount ?? 0)
})

function print(): void {
  window.print()
}

function close(): void {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/')
  }
}
</script>

<template>
  <div class="ticket-page min-h-screen px-4 py-6">
    <div class="no-print mx-auto mb-4 flex w-full max-w-[340px] items-center justify-between gap-2">
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-b from-goldstrong to-gold px-4 py-2.5 text-sm font-semibold text-[#0a0e13] shadow-lg transition hover:brightness-110"
        @click="print"
      >
        <Printer class="size-4" />
        {{ t('ticket.print') }}
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-800 transition hover:bg-slate-100"
        @click="close"
      >
        <X class="size-4" />
        {{ t('ticket.close') }}
      </button>
    </div>

    <div v-if="loading" class="paper mx-auto w-[302px] bg-white p-4 text-[11px] text-black">
      {{ t('common.loading') }}
    </div>

    <div v-else-if="error" class="paper mx-auto w-[302px] bg-white p-6 text-center text-[11px] text-black">
      {{ error }}
    </div>

    <!-- ───────────── Ticket de vente ───────────── -->
    <div v-else-if="sale" class="paper mx-auto w-[302px] bg-white px-4 py-4 text-[11px] leading-[1.45] text-black shadow-xl">
      <header class="text-center">
        <p class="text-[15px] font-extrabold uppercase leading-tight tracking-wide">{{ shop.shop_name }}</p>
        <p v-if="shop.shop_tagline" class="text-[10px]">{{ shop.shop_tagline }}</p>
        <p v-if="shop.shop_address" class="text-[10px]">{{ shop.shop_address }}</p>
        <p v-if="shop.shop_phone" class="text-[10px]">Tél : {{ shop.shop_phone }}</p>
        <p v-if="shop.shop_ice" class="text-[10px]">ICE : {{ shop.shop_ice }}</p>
      </header>

      <div class="my-2 border-t border-dashed border-black" />

      <div class="space-y-0.5">
        <p class="flex items-start justify-between gap-2">
          <span class="font-bold">N° {{ sale.number }}</span>
          <span class="shrink-0 text-[10px]">{{ shortDate(sale.date) }}</span>
        </p>
        <p>{{ t('ticket.client') }} : {{ sale.client?.name ?? t('ticket.walk_in') }}</p>
        <p v-if="sale.client?.code" class="text-[10px]">{{ t('ticket.code') }} : {{ sale.client.code }}</p>
        <p v-if="sale.user?.name" class="text-[10px]">{{ t('ticket.seller') }} : {{ sale.user.name }}</p>
      </div>

      <div class="my-2 border-t border-dashed border-black" />

      <div class="flex gap-2 pb-1 text-[9.5px] font-bold uppercase tracking-wide">
        <span class="min-w-0 flex-1">{{ t('ticket.item') }}</span>
        <span class="shrink-0">{{ t('ticket.amount') }}</span>
      </div>

      <div
        v-for="item in sale.items ?? []"
        :key="item.id"
        class="flex gap-2 border-b border-dotted border-black/40 py-1.5"
      >
        <div class="min-w-0 flex-1">
          <p class="font-semibold leading-tight">{{ item.product?.name_fr }}</p>
          <p class="text-[9.5px] text-black/70">
            {{ item.quantity }} × {{ money(item.unit_price) }}
            <span v-if="item.discount > 0"> · remise {{ money(item.discount) }}</span>
          </p>
        </div>
        <p class="shrink-0 tabular-nums">{{ money(item.total) }}</p>
      </div>

      <div class="mt-2 space-y-0.5">
        <p class="flex justify-between">
          <span>{{ t('ticket.subtotal') }}</span>
          <span class="tabular-nums">{{ money(sale.subtotal) }}</span>
        </p>
        <p v-if="sale.discount > 0" class="flex justify-between">
          <span>{{ t('ticket.discount') }}</span>
          <span class="tabular-nums">− {{ money(sale.discount) }}</span>
        </p>
        <p class="mt-1 flex justify-between border-t border-black pt-1 text-[14px] font-extrabold">
          <span>{{ t('ticket.total') }}</span>
          <span class="tabular-nums">{{ money(sale.total) }}</span>
        </p>
        <p class="flex justify-between">
          <span>{{ t('ticket.paid') }}</span>
          <span class="tabular-nums">{{ money(sale.paid_amount) }}</span>
        </p>
        <p v-if="sale.total - sale.paid_amount > 0.009" class="flex justify-between font-bold">
          <span>{{ t('ticket.remaining') }}</span>
          <span class="tabular-nums">{{ money(sale.total - sale.paid_amount) }}</span>
        </p>
      </div>

      <div v-if="sale.credit" class="mt-2 border-t border-dashed border-black pt-2">
        <p class="mb-1 font-bold uppercase">{{ t('ticket.credit') }}</p>
        <p v-if="sale.credit.due_date" class="flex justify-between text-[10px]">
          <span>{{ t('ticket.due') }}</span>
          <span>{{ shortDate(sale.credit.due_date) }}</span>
        </p>
        <div
          v-for="installment in sale.credit.installments ?? []"
          :key="installment.id"
          class="flex justify-between text-[10px]"
        >
          <span>{{ installment.number }}. {{ shortDate(installment.due_date) }}</span>
          <span class="tabular-nums">{{ money(installment.amount) }}</span>
        </div>
      </div>

      <div class="my-2 border-t border-dashed border-black" />

      <footer class="text-center text-[10px]">
        <p v-if="shop.ticket_footer">{{ shop.ticket_footer }}</p>
        <p v-else class="font-semibold">{{ t('ticket.thanks') }}</p>
        <p class="mt-1 text-[9px] text-black/60">{{ shop.shop_name }} · {{ sale.number }}</p>
      </footer>
    </div>

    <!-- ───────────── Reçu de paiement ───────────── -->
    <div v-else-if="payment" class="paper mx-auto w-[302px] bg-white px-4 py-4 text-[11px] leading-[1.45] text-black shadow-xl">
      <header class="text-center">
        <p class="text-[15px] font-extrabold uppercase leading-tight tracking-wide">{{ shop.shop_name }}</p>
        <p v-if="shop.shop_tagline" class="text-[10px]">{{ shop.shop_tagline }}</p>
        <p v-if="shop.shop_phone" class="text-[10px]">Tél : {{ shop.shop_phone }}</p>
        <p v-if="shop.shop_ice" class="text-[10px]">ICE : {{ shop.shop_ice }}</p>
      </header>

      <div class="my-2 border-t border-dashed border-black" />

      <p class="text-center font-bold uppercase">{{ t('ticket.receipt') }}</p>
      <p class="text-center text-[10px]">N° {{ String(payment.id).padStart(6, '0') }}</p>

      <div class="my-2 border-t border-dashed border-black" />

      <div class="space-y-0.5">
        <p class="flex justify-between gap-2">
          <span>{{ t('common.date') }}</span>
          <span class="text-[10px]">{{ dateTime(payment.paid_at) }}</span>
        </p>
        <p>{{ t('ticket.client') }} : {{ payment.client?.name ?? '—' }}</p>
        <p v-if="payment.client?.code" class="text-[10px]">{{ t('ticket.code') }} : {{ payment.client.code }}</p>
        <p v-if="payment.credit" class="text-[10px]">{{ t('ticket.sale') }} : {{ payment.credit.sale_number }}</p>
      </div>

      <div class="my-3 rounded border border-black px-3 py-2 text-center">
        <p class="text-[9.5px] uppercase tracking-widest">{{ t('ticket.received') }}</p>
        <p class="text-[20px] font-extrabold leading-tight tabular-nums">{{ money(payment.amount) }}</p>
      </div>

      <div class="space-y-0.5">
        <p class="flex justify-between">
          <span>{{ t('ticket.method') }}</span>
          <span class="font-semibold">{{ t('status.' + payment.method) }}</span>
        </p>
        <p v-if="payment.reference" class="flex justify-between">
          <span>{{ t('ticket.reference') }}</span>
          <span>{{ payment.reference }}</span>
        </p>
        <p class="flex justify-between">
          <span>{{ t('ticket.previous_balance') }}</span>
          <span class="tabular-nums">{{ money(previousBalance) }}</span>
        </p>
        <p class="flex justify-between font-bold">
          <span>{{ t('ticket.new_balance') }}</span>
          <span class="tabular-nums">{{ money(payment.client?.balance ?? 0) }}</span>
        </p>
      </div>

      <div class="my-2 border-t border-dashed border-black" />

      <p v-if="payment.received_by?.name" class="text-[10px]">
        {{ t('ticket.received_by') }} : {{ payment.received_by.name }}
      </p>

      <footer class="mt-2 text-center text-[10px]">
        <p v-if="shop.ticket_footer">{{ shop.ticket_footer }}</p>
        <p v-else class="font-semibold">{{ t('ticket.thanks') }}</p>
      </footer>
    </div>
  </div>
</template>

<style>
.ticket-page {
  background: #e7e9ec;
}

@media print {
  @page {
    size: 80mm auto;
    margin: 3mm;
  }

  html,
  body {
    background: #fff !important;
  }

  body::before {
    display: none !important;
  }

  .no-print {
    display: none !important;
  }

  .ticket-page {
    background: #fff !important;
    min-height: 0 !important;
    padding: 0 !important;
  }

  .ticket-page .paper {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
    box-shadow: none !important;
    border: 0 !important;
  }

  .ticket-page,
  .ticket-page * {
    color: #000 !important;
  }
}
</style>
