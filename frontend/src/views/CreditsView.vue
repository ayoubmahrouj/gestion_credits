<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppModal from '../components/ui/AppModal.vue'
import api from '../lib/api'
import { money, shortDate } from '../lib/format'
import type { Credit, Paginated } from '../types'

const { t } = useI18n()

const credits = ref<Credit[]>([])
const meta = ref<Paginated<Credit>['meta'] | null>(null)
const loading = ref(true)
const search = ref('')
const status = ref('')
const overdue = ref(false)
const page = ref(1)

const selected = ref<Credit | null>(null)
const detailOpen = ref(false)
const detailLoading = ref(false)

let timer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

watch([status, overdue], () => {
  page.value = 1
  load()
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get<Paginated<Credit>>('/credits', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        overdue: overdue.value ? 1 : undefined,
        page: page.value,
        per_page: 12,
      },
    })
    credits.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

function go(next: number): void {
  page.value = Math.min(Math.max(1, next), meta.value?.last_page ?? 1)
  load()
}

function progress(credit: Credit): number {
  const total = Number(credit.total_due) || 0
  if (total <= 0) return 0

  return Math.min(100, Math.round((Number(credit.amount_paid) / total) * 100))
}

async function openDetail(credit: Credit): Promise<void> {
  detailOpen.value = true
  detailLoading.value = true
  selected.value = credit

  try {
    const { data } = await api.get(`/credits/${credit.id}`)
    selected.value = data.data
  } finally {
    detailLoading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <header>
      <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('credits.title') }}</h2>
      <p class="mt-1 text-xs text-muted">{{ t('credits.subtitle') }}</p>
    </header>

    <div class="card flex flex-wrap items-center gap-3 p-3">
      <div class="relative min-w-[220px] flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input v-model="search" class="field ps-10" :placeholder="t('common.search')" />
      </div>

      <select v-model="status" class="field max-w-[170px]">
        <option value="">{{ t('common.all') }}</option>
        <option value="active">{{ t('status.active') }}</option>
        <option value="overdue">{{ t('status.overdue') }}</option>
        <option value="settled">{{ t('status.settled') }}</option>
      </select>

      <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line px-3 py-2.5 text-xs font-semibold text-muted">
        <input v-model="overdue" type="checkbox" class="accent-[var(--gold)]" />
        {{ t('status.overdue') }}
      </label>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>{{ t('common.client') }}</th>
              <th>{{ t('credits.principal') }}</th>
              <th>{{ t('credits.remaining') }}</th>
              <th>{{ t('credits.progress') }}</th>
              <th>{{ t('credits.due_date') }}</th>
              <th>{{ t('common.status') }}</th>
              <th class="text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</td>
            </tr>
            <tr v-for="credit in credits" v-else :key="credit.id">
              <td>
                <p class="font-semibold">{{ credit.client?.name ?? '—' }}</p>
                <p class="text-xs text-muted">{{ credit.sale?.number ?? `#${credit.id}` }}</p>
              </td>
              <td class="text-muted">{{ money(credit.total_due) }}</td>
              <td class="font-semibold text-gold">{{ money(credit.balance) }}</td>
              <td>
                <div class="flex items-center gap-2">
                  <div class="h-1.5 w-20 overflow-hidden rounded-full bg-surface2">
                    <div class="h-full rounded-full bg-gradient-to-r from-goldstrong to-gold" :style="{ width: `${progress(credit)}%` }" />
                  </div>
                  <span class="text-[11px] text-muted">{{ progress(credit) }}%</span>
                </div>
              </td>
              <td>
                <p class="text-xs">{{ shortDate(credit.due_date) }}</p>
                <p v-if="credit.days_overdue && credit.days_overdue > 0" class="text-[11px] font-semibold text-danger">
                  {{ credit.days_overdue }}{{ t('credits.days_late') }}
                </p>
              </td>
              <td><AppBadge :status="credit.status" /></td>
              <td class="text-end">
                <AppButton size="sm" variant="outline" @click="openDetail(credit)">{{ t('common.view_all') }}</AppButton>
              </td>
            </tr>
            <tr v-if="!loading && credits.length === 0">
              <td colspan="7" class="py-12 text-center text-sm text-muted">{{ t('credits.empty') }}</td>
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

    <AppModal :open="detailOpen" :title="selected?.client?.name" :subtitle="selected?.sale?.number" @close="detailOpen = false">
      <div v-if="detailLoading" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</div>

      <div v-else-if="selected" class="space-y-4">
        <div class="grid grid-cols-3 gap-3">
          <div class="rounded-xl border border-line bg-surface2/60 p-3">
            <p class="text-[10px] uppercase tracking-widest text-muted">{{ t('credits.principal') }}</p>
            <p class="mt-1 text-sm font-bold">{{ money(selected.total_due) }}</p>
          </div>
          <div class="rounded-xl border border-line bg-surface2/60 p-3">
            <p class="text-[10px] uppercase tracking-widest text-muted">{{ t('credits.remaining') }}</p>
            <p class="mt-1 text-sm font-bold text-gold">{{ money(selected.balance) }}</p>
          </div>
          <div class="rounded-xl border border-line bg-surface2/60 p-3">
            <p class="text-[10px] uppercase tracking-widest text-muted">{{ t('common.status') }}</p>
            <p class="mt-1"><AppBadge :status="selected.status" /></p>
          </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-line">
          <table class="table-base">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ t('credits.due_date') }}</th>
                <th>{{ t('common.amount') }}</th>
                <th>{{ t('credits.remaining') }}</th>
                <th>{{ t('common.status') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="installment in selected.installments" :key="installment.id">
                <td class="text-muted">{{ installment.number }}</td>
                <td>{{ shortDate(installment.due_date) }}</td>
                <td>{{ money(installment.amount) }}</td>
                <td>{{ money(installment.remaining) }}</td>
                <td><AppBadge :status="installment.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </AppModal>
  </div>
</template>
