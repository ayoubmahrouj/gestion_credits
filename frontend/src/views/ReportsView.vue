<script setup lang="ts">
import { Download, RefreshCw } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BarChart from '../components/charts/BarChart.vue'
import DonutChart from '../components/charts/DonutChart.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import StatCard from '../components/ui/StatCard.vue'
import api from '../lib/api'
import { downloadExcel } from '../lib/export'
import { money, num } from '../lib/format'
import { useUiStore } from '../stores/ui'
import type { Client, Paginated, ReportSummary } from '../types'

const { t } = useI18n()
const ui = useUiStore()

function pad(value: number): string {
  return String(value).padStart(2, '0')
}

function iso(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

const now = new Date()

const report = ref<ReportSummary | null>(null)
const loading = ref(true)
const error = ref('')
const exporting = ref(false)
const preset = ref('month')
const ready = ref(false)

const filters = reactive({
  from: iso(new Date(now.getFullYear(), now.getMonth(), 1)),
  to: iso(now),
  client_id: '' as number | '',
  brand_id: '' as number | '',
  category_id: '' as number | '',
  type: '',
  method: '',
  status: '',
})

const clients = ref<Client[]>([])
const brands = ref<{ id: number; name: string }[]>([])
const categories = ref<{ id: number; name: string }[]>([])

const presets = [
  { key: 'today', label: 'reports.presets.today' },
  { key: 'yesterday', label: 'reports.presets.yesterday' },
  { key: '7d', label: 'reports.presets.d7' },
  { key: '30d', label: 'reports.presets.d30' },
  { key: 'month', label: 'reports.presets.month' },
  { key: 'last_month', label: 'reports.presets.last_month' },
  { key: 'quarter', label: 'reports.presets.quarter' },
  { key: 'year', label: 'reports.presets.year' },
]

function applyPreset(key: string): void {
  const today = new Date()
  let from = new Date(today.getFullYear(), today.getMonth(), today.getDate())
  let to = new Date(from)

  switch (key) {
    case 'yesterday':
      from = new Date(from.getFullYear(), from.getMonth(), from.getDate() - 1)
      to = new Date(from)
      break
    case '7d':
      from = new Date(from.getFullYear(), from.getMonth(), from.getDate() - 6)
      break
    case '30d':
      from = new Date(from.getFullYear(), from.getMonth(), from.getDate() - 29)
      break
    case 'month':
      from = new Date(today.getFullYear(), today.getMonth(), 1)
      break
    case 'last_month':
      from = new Date(today.getFullYear(), today.getMonth() - 1, 1)
      to = new Date(today.getFullYear(), today.getMonth(), 0)
      break
    case 'quarter':
      from = new Date(today.getFullYear(), Math.floor(today.getMonth() / 3) * 3, 1)
      break
    case 'year':
      from = new Date(today.getFullYear(), 0, 1)
      break
    default:
      break
  }

  filters.from = iso(from)
  filters.to = iso(to)
  preset.value = key
}

function query(): Record<string, string | number> {
  const params: Record<string, string | number> = { from: filters.from, to: filters.to }

  if (filters.client_id) params.client_id = filters.client_id
  if (filters.brand_id) params.brand_id = filters.brand_id
  if (filters.category_id) params.category_id = filters.category_id
  if (filters.type) params.type = filters.type
  if (filters.method) params.method = filters.method
  if (filters.status) params.status = filters.status

  return params
}

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const { data } = await api.get<ReportSummary>('/reports/summary', { params: query() })
    report.value = data
  } catch {
    error.value = t('common.error')
  } finally {
    loading.value = false
  }
}

async function loadRefs(): Promise<void> {
  const [clientsRes, brandsRes, categoriesRes] = await Promise.all([
    api.get<Paginated<Client>>('/clients', { params: { per_page: 100 } }),
    api.get('/brands'),
    api.get('/categories'),
  ])

  clients.value = clientsRes.data.data
  brands.value = brandsRes.data.data
  categories.value = categoriesRes.data.data
}

async function exportAll(): Promise<void> {
  exporting.value = true

  try {
    await downloadExcel({ ...query(), scope: 'all' })
    ui.notify(t('reports.exported'))
  } catch {
    ui.notify(t('common.error'), 'error')
  } finally {
    exporting.value = false
  }
}

let timer: number | undefined

watch(
  filters,
  () => {
    if (!ready.value) return

    window.clearTimeout(timer)
    timer = window.setTimeout(load, 350)
  },
  { deep: true },
)

onMounted(async () => {
  await loadRefs()
  ready.value = true
  await load()
})

const series = computed(() => {
  const all = report.value?.series ?? []

  return all.length > 31 ? all.slice(-31) : all
})

const salesChart = computed(() => series.value.map((row) => ({ label: row.label, total: row.sales })))
const paymentsChart = computed(() => series.value.map((row) => ({ label: row.label, total: row.payments })))

const typeSplit = computed(() => ({
  cash: report.value?.sales.cash ?? 0,
  credit: report.value?.sales.credit ?? 0,
}))

const typeLabels = computed(() => ({
  cash: t('sales.cash'),
  credit: t('sales.credit'),
}))

const methodSplit = computed(() => {
  const source = report.value?.payments.by_method ?? {}

  return {
    cash: Number(source.cash ?? 0),
    bank: Number(source.bank ?? 0),
    cheque: Number(source.cheque ?? 0),
    card: Number(source.card ?? 0),
    other: Number(source.other ?? 0),
  }
})

const methodLabels = computed(() => ({
  cash: t('status.cash'),
  bank: t('status.bank'),
  cheque: t('status.cheque'),
  card: t('status.card'),
  other: t('status.other'),
}))

const agingRows = computed(() =>
  Object.entries(report.value?.receivables.aging ?? {}).map(([key, value]) => ({
    key,
    label: t(`aging.${key}`),
    total: Number(value),
  })),
)

const discountRate = computed(() => {
  const sales = report.value?.sales
  if (!sales || sales.subtotal <= 0) return 0

  return Math.round((sales.discount / sales.subtotal) * 1000) / 10
})
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('reports.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('reports.subtitle') }}</p>
      </div>

      <div class="flex flex-wrap gap-2">
        <AppButton variant="outline" size="sm" :loading="loading" @click="load">
          <RefreshCw class="size-3.5" />
          {{ t('common.refresh') }}
        </AppButton>

        <AppButton size="sm" :loading="exporting" @click="exportAll">
          <Download class="size-3.5" />
          {{ t('common.export_excel') }}
        </AppButton>
      </div>
    </header>

    <AppCard :padded="false">
      <div class="space-y-4 p-4">
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="item in presets"
            :key="item.key"
            type="button"
            class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
            :class="preset === item.key
              ? 'border-gold/60 bg-goldsoft text-gold'
              : 'border-line bg-surface2 text-muted hover:border-gold/40 hover:text-ink'"
            @click="applyPreset(item.key)"
          >
            {{ t(item.label) }}
          </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('reports.from') }}</span>
            <input v-model="filters.from" type="date" class="field py-2 text-xs" />
          </label>

          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('reports.to') }}</span>
            <input v-model="filters.to" type="date" class="field py-2 text-xs" />
          </label>

          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('common.client') }}</span>
            <select v-model="filters.client_id" class="field py-2 text-xs">
              <option value="">{{ t('common.all') }}</option>
              <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('products.brand') }}</span>
            <select v-model="filters.brand_id" class="field py-2 text-xs">
              <option value="">{{ t('common.all') }}</option>
              <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('products.category') }}</span>
            <select v-model="filters.category_id" class="field py-2 text-xs">
              <option value="">{{ t('common.all') }}</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('sales.type') }}</span>
            <select v-model="filters.type" class="field py-2 text-xs">
              <option value="">{{ t('common.all') }}</option>
              <option value="cash">{{ t('sales.cash') }}</option>
              <option value="credit">{{ t('sales.credit') }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-muted">{{ t('payments.method') }}</span>
            <select v-model="filters.method" class="field py-2 text-xs">
              <option value="">{{ t('common.all') }}</option>
              <option value="cash">{{ t('status.cash') }}</option>
              <option value="bank">{{ t('status.bank') }}</option>
              <option value="cheque">{{ t('status.cheque') }}</option>
              <option value="card">{{ t('status.card') }}</option>
              <option value="other">{{ t('status.other') }}</option>
            </select>
          </label>
        </div>
      </div>
    </AppCard>

    <p v-if="error" class="card border-danger/30 bg-danger/10 p-3 text-xs text-danger">{{ error }}</p>

    <div v-if="loading && !report" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 8" :key="i" class="card p-5">
        <div class="skeleton h-3 w-24" />
        <div class="skeleton mt-3 h-7 w-32" />
      </div>
    </div>

    <template v-if="report">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          :label="t('reports.sales_total')"
          :value="money(report.sales.total)"
          :hint="`${t('reports.sales_count_' + (report.sales.count > 1 ? 'many' : 'one'), { n: report.sales.count })} · ${t('reports.avg_basket')} ${money(report.sales.avg_basket)}`"
          tone="gold"
          icon="sales"
        />

        <StatCard
          :label="t('reports.payments_total')"
          :value="money(report.payments.total)"
          :hint="t('reports.receipts_count_' + (report.payments.count > 1 ? 'many' : 'one'), { n: report.payments.count })"
          tone="success"
          icon="cash"
        />

        <StatCard
          :label="t('reports.receivables')"
          :value="money(report.receivables.balance)"
          :hint="t('reports.clients_debt_' + (report.receivables.clients > 1 ? 'many' : 'one'), { n: report.receivables.clients })"
          icon="wallet"
        />

        <StatCard
          :label="t('reports.overdue')"
          :value="money(report.receivables.overdue)"
          :hint="`${report.collection_rate}% ${t('reports.collection_rate')}`"
          tone="danger"
          icon="alert"
        />

        <StatCard
          :label="t('reports.avg_basket')"
          :value="money(report.sales.avg_basket)"
          :hint="`${money(report.sales.daily_average)} / ${t('reports.day')}`"
          icon="star"
        />

        <StatCard
          :label="t('reports.margin')"
          :value="money(report.margin.margin)"
          :hint="`${report.margin.rate}% ${t('reports.margin_rate')}`"
          tone="success"
          icon="sales"
        />

        <StatCard
          :label="t('reports.discounts')"
          :value="money(report.sales.discount)"
          :hint="`${discountRate}% ${t('reports.of_sales')}`"
          icon="alert"
        />

        <StatCard
          :label="t('reports.stock_value')"
          :value="money(report.stock.value_purchase)"
          :hint="`${t('settings.products_' + (report.stock.products > 1 ? 'many' : 'one'), { n: report.stock.products })} · ${report.stock.low} ${t('reports.low_word')}`"
          icon="box"
        />
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <AppCard :title="t('reports.sales_chart')">
          <template #header>
            <h2 class="text-sm font-semibold tracking-wide">{{ t('reports.sales_chart') }}</h2>
            <p class="mt-0.5 text-xs text-muted" dir="ltr">{{ report.period.from }} → {{ report.period.to }}</p>
          </template>

          <BarChart :data="salesChart" :height="190" />
        </AppCard>

        <AppCard :title="t('reports.payments_chart')" :subtitle="t('reports.payments_chart_sub')">
          <BarChart :data="paymentsChart" :height="190" />
        </AppCard>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <AppCard :title="t('reports.split_type')">
          <DonutChart :data="typeSplit" :labels="typeLabels" />
        </AppCard>

        <AppCard :title="t('reports.split_method')">
          <DonutChart :data="methodSplit" :labels="methodLabels" />
        </AppCard>

        <AppCard :title="t('reports.aging')">
          <ul class="space-y-2">
            <li v-for="row in agingRows" :key="row.key" class="flex items-center justify-between gap-3 text-xs">
              <span class="text-muted">{{ row.label }}</span>
              <span class="font-semibold tabular-nums">{{ money(row.total) }}</span>
            </li>
          </ul>
        </AppCard>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <AppCard :title="t('reports.top_products')" :padded="false">
          <div class="overflow-x-auto">
            <table class="table-base">
              <thead>
                <tr>
                  <th>{{ t('reports.product') }}</th>
                  <th class="text-end">{{ t('reports.qty') }}</th>
                  <th class="text-end">{{ t('common.total') }}</th>
                  <th class="text-end">{{ t('reports.margin_short') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product in report.top_products" :key="product.id">
                  <td>
                    <p class="font-semibold">{{ product.name }}</p>
                    <p class="text-[11px] text-muted">{{ product.sku }}</p>
                  </td>
                  <td class="text-end tabular-nums">{{ num(product.qty) }}</td>
                  <td class="text-end font-semibold tabular-nums">{{ money(product.total) }}</td>
                  <td class="text-end tabular-nums text-success">{{ money(product.margin) }}</td>
                </tr>
                <tr v-if="report.top_products.length === 0">
                  <td colspan="4" class="py-8 text-center text-xs text-muted">{{ t('reports.empty') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <AppCard :title="t('reports.top_clients')" :padded="false">
          <div class="overflow-x-auto">
            <table class="table-base">
              <thead>
                <tr>
                  <th>{{ t('common.client') }}</th>
                  <th class="text-end">{{ t('reports.sales_word') }}</th>
                  <th class="text-end">{{ t('common.total') }}</th>
                  <th class="text-end">{{ t('reports.balance_short') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="client in report.top_clients" :key="client.id">
                  <td>
                    <p class="font-semibold">{{ client.name }}</p>
                    <p class="text-[11px] text-muted">{{ client.code }}</p>
                  </td>
                  <td class="text-end tabular-nums">{{ client.count }}</td>
                  <td class="text-end font-semibold tabular-nums">{{ money(client.total) }}</td>
                  <td class="text-end tabular-nums" :class="client.balance > 0 ? 'text-gold' : 'text-muted'">
                    {{ money(client.balance) }}
                  </td>
                </tr>
                <tr v-if="report.top_clients.length === 0">
                  <td colspan="4" class="py-8 text-center text-xs text-muted">{{ t('reports.empty') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <AppCard :title="t('reports.by_brand')" :padded="false">
          <ul class="divide-y divide-line/70">
            <li v-for="row in report.by_brand" :key="row.name" class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
              <span class="truncate">{{ row.name }}</span>
              <span class="shrink-0 font-semibold tabular-nums">{{ money(row.total) }}</span>
            </li>
            <li v-if="report.by_brand.length === 0" class="p-6 text-center text-xs text-muted">{{ t('reports.empty') }}</li>
          </ul>
        </AppCard>

        <AppCard :title="t('reports.by_category')" :padded="false">
          <ul class="divide-y divide-line/70">
            <li v-for="row in report.by_category" :key="row.name" class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
              <span class="truncate">{{ row.name }}</span>
              <span class="shrink-0 font-semibold tabular-nums">{{ money(row.total) }}</span>
            </li>
            <li v-if="report.by_category.length === 0" class="p-6 text-center text-xs text-muted">{{ t('reports.empty') }}</li>
          </ul>
        </AppCard>

        <AppCard :title="t('reports.by_user')" :padded="false">
          <ul class="divide-y divide-line/70">
            <li v-for="row in report.by_user" :key="row.name" class="flex items-center justify-between gap-3 px-4 py-2.5 text-xs">
              <span class="truncate">{{ row.name }}</span>
              <span class="shrink-0 font-semibold tabular-nums">{{ money(row.total) }}</span>
            </li>
            <li v-if="report.by_user.length === 0" class="p-6 text-center text-xs text-muted">{{ t('reports.empty') }}</li>
          </ul>
        </AppCard>
      </div>

      <AppCard :title="t('reports.low_stock')" :padded="false">
        <div class="overflow-x-auto">
          <table class="table-base">
            <thead>
              <tr>
                <th>{{ t('products.sku') }}</th>
                <th>{{ t('products.name') }}</th>
                <th class="text-end">{{ t('products.stock') }}</th>
                <th class="text-end">{{ t('reports.stock_min') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in report.stock.low_list" :key="product.id">
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td>{{ product.name }}</td>
                <td class="text-end font-semibold tabular-nums text-danger">{{ num(product.stock_qty) }}</td>
                <td class="text-end tabular-nums text-muted">{{ num(product.stock_min) }}</td>
              </tr>
              <tr v-if="report.stock.low_list.length === 0">
                <td colspan="4" class="py-8 text-center text-xs text-muted">{{ t('reports.no_low_stock') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </AppCard>
    </template>
  </div>
</template>
