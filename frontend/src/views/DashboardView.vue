<script setup lang="ts">
import { ArrowUpRight, RefreshCw } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BarChart from '../components/charts/BarChart.vue'
import DonutChart from '../components/charts/DonutChart.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import StatCard from '../components/ui/StatCard.vue'
import api from '../lib/api'
import { dateTime, money, num } from '../lib/format'
import type { DashboardData } from '../types'

const { t } = useI18n()

function countHint(kind: 'clients' | 'overdue', count: number): string {
  const key = `kpi.${kind}_${count > 1 ? 'many' : 'one'}`

  return t(key, { n: count })
}

const data = ref<DashboardData | null>(null)
const loading = ref(true)
const error = ref('')

async function load(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    const response = await api.get('/dashboard')
    data.value = response.data
  } catch {
    error.value = t('common.error')
  } finally {
    loading.value = false
  }
}

onMounted(load)

const agingLabels = computed(() => ({
  current: t('aging.current'),
  '1_30': t('aging.1_30'),
  '31_60': t('aging.31_60'),
  '61_90': t('aging.61_90'),
  over_90: t('aging.over_90'),
}))

const chartData = computed(() =>
  (data.value?.monthly_series ?? []).map((row) => ({ label: row.label, total: row.total })),
)
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('dashboard.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('dashboard.subtitle') }}</p>
      </div>
      <AppButton variant="outline" size="sm" :loading="loading" @click="load">
        <RefreshCw class="size-3.5" />
        {{ t('common.refresh') }}
      </AppButton>
    </header>

    <div v-if="error" class="card border-danger/30 bg-danger/10 p-4 text-sm text-danger">{{ error }}</div>

    <!-- Squelette -->
    <div v-if="loading && !data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="i in 4" :key="i" class="card p-5">
        <div class="skeleton h-3 w-24" />
        <div class="skeleton mt-3 h-7 w-32" />
      </div>
    </div>

    <template v-if="data">
      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          :label="t('kpi.sales_today')"
          :value="money(data.kpis.sales_today)"
          :hint="t('kpi.sales_month') + ' · ' + money(data.kpis.sales_month)"
          icon="sales"
          tone="gold"
        />
        <StatCard
          :label="t('kpi.receivables')"
          :value="money(data.kpis.receivables)"
          :hint="countHint('clients', data.kpis.clients_total)"
          icon="wallet"
        />
        <StatCard
          :label="t('kpi.overdue')"
          :value="money(data.kpis.overdue_amount)"
          :hint="countHint('overdue', data.kpis.clients_overdue)"
          icon="alert"
          tone="danger"
        />
        <StatCard
          :label="t('kpi.payments_today')"
          :value="money(data.kpis.payments_today)"
          :hint="`DSO · ${num(data.kpis.dso, 1)} ${t('kpi.days')}`"
          icon="cash"
          tone="success"
        />
      </section>

      <section class="grid gap-4 xl:grid-cols-3">
        <AppCard class="xl:col-span-2" :title="t('dashboard.monthly_sales')" padded>
          <BarChart :data="chartData" :height="180" />
        </AppCard>

        <AppCard :title="t('dashboard.aging')" padded>
          <DonutChart :data="data.aging" :labels="agingLabels" />
        </AppCard>
      </section>

      <section class="grid gap-4 xl:grid-cols-3">
        <AppCard class="xl:col-span-2" :title="t('dashboard.top_debtors')" :padded="false">
          <div class="overflow-x-auto">
            <table class="table-base">
              <thead>
                <tr>
                  <th>{{ t('common.client') }}</th>
                  <th>{{ t('clients.balance') }}</th>
                  <th>{{ t('clients.score') }}</th>
                  <th class="text-end">{{ t('common.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="debtor in data.top_debtors" :key="debtor.id">
                  <td>
                    <p class="font-semibold">{{ debtor.name }}</p>
                    <p class="text-xs text-muted">{{ debtor.code }} · {{ debtor.phone ?? '—' }}</p>
                  </td>
                  <td class="font-semibold">{{ money(debtor.balance) }}</td>
                  <td>
                    <div class="flex items-center gap-2">
                      <div class="h-1.5 w-16 overflow-hidden rounded-full bg-surface2">
                        <div
                          class="h-full rounded-full"
                          :class="debtor.score >= 60 ? 'bg-success' : debtor.score >= 40 ? 'bg-warn' : 'bg-danger'"
                          :style="{ width: `${debtor.score}%` }"
                        />
                      </div>
                      <span class="text-xs text-muted">{{ debtor.score }}</span>
                    </div>
                  </td>
                  <td class="text-end">
                    <RouterLink
                      :to="{ name: 'client-detail', params: { id: debtor.id } }"
                      class="inline-flex items-center gap-1 text-xs font-semibold text-gold hover:underline"
                    >
                      {{ t('common.view_all') }}
                      <ArrowUpRight class="size-3.5" />
                    </RouterLink>
                  </td>
                </tr>
                <tr v-if="data.top_debtors.length === 0">
                  <td colspan="4" class="py-10 text-center text-sm text-muted">{{ t('dashboard.no_debtors') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </AppCard>

        <AppCard :title="t('dashboard.recent_activity')" :padded="false">
          <ul class="divide-y divide-line/70">
            <li v-for="item in data.recent_activity" :key="item.id" class="flex items-center justify-between gap-3 px-5 py-3">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium">{{ item.client ?? '—' }}</p>
                <p class="text-[11px] text-muted">{{ dateTime(item.paid_at) }}</p>
              </div>
              <div class="text-end">
                <p class="text-sm font-semibold text-success">+{{ money(item.amount) }}</p>
                <AppBadge :status="item.method" />
              </div>
            </li>
            <li v-if="data.recent_activity.length === 0" class="px-5 py-10 text-center text-sm text-muted">
              {{ t('common.empty') }}
            </li>
          </ul>
        </AppCard>
      </section>

      <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard :label="t('kpi.dso')" :value="`${num(data.kpis.dso, 1)} ${t('kpi.days')}`" icon="clock" />
        <StatCard :label="t('kpi.clients_total')" :value="num(data.kpis.clients_total)" icon="users" />
        <StatCard :label="t('kpi.clients_overdue')" :value="num(data.kpis.clients_overdue)" icon="alert" tone="danger" />
        <StatCard :label="t('kpi.avg_score')" :value="num(data.kpis.avg_score)" icon="star" tone="gold" />
      </section>
    </template>
  </div>
</template>
