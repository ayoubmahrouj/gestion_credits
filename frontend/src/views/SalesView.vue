<script setup lang="ts">
import { Download, Plus, Printer, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ClientFormModal from '../components/forms/ClientFormModal.vue'
import ProductFormModal from '../components/forms/ProductFormModal.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import AppModal from '../components/ui/AppModal.vue'
import api from '../lib/api'
import { downloadExcel } from '../lib/export'
import { money, shortDate } from '../lib/format'
import { useUiStore } from '../stores/ui'
import type { Client, Paginated, Product, Sale } from '../types'

interface Line {
  product_id: number | ''
  quantity: number
  unit_price: number
}

function todayIso(): string {
  const now = new Date()

  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

const { t } = useI18n()
const ui = useUiStore()

const sales = ref<Sale[]>([])
const clients = ref<Client[]>([])
const products = ref<Product[]>([])
const meta = ref<Paginated<Sale>['meta'] | null>(null)
const loading = ref(true)
const typeFilter = ref('')
const page = ref(1)

const modal = ref(false)
const saving = ref(false)
const formError = ref('')
const exporting = ref(false)
const doneSale = ref<Sale | null>(null)

const clientModal = ref(false)
const productModal = ref(false)
const targetLine = ref<number | null>(null)

const form = reactive({
  client_id: '' as number | '',
  type: 'cash' as 'cash' | 'credit',
  date: todayIso(),
  discount: 0,
  payment_amount: 0,
  installment_count: 1,
  items: [{ product_id: '', quantity: 1, unit_price: 0 }] as Line[],
})

const total = computed(() =>
  Math.max(0, form.items.reduce((sum, line) => sum + Number(line.quantity || 0) * Number(line.unit_price || 0), 0) - Number(form.discount || 0)),
)

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get<Paginated<Sale>>('/sales', {
      params: { type: typeFilter.value || undefined, page: page.value, per_page: 15 },
    })
    sales.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

async function loadRefs(): Promise<void> {
  const [clientsRes, productsRes] = await Promise.all([
    api.get<Paginated<Client>>('/clients', { params: { per_page: 100 } }),
    api.get<Paginated<Product>>('/products', { params: { per_page: 100 } }),
  ])
  clients.value = clientsRes.data.data
  products.value = productsRes.data.data
}

function go(next: number): void {
  page.value = Math.min(Math.max(1, next), meta.value?.last_page ?? 1)
  load()
}

function openModal(): void {
  Object.assign(form, {
    client_id: '',
    type: 'cash',
    date: todayIso(),
    discount: 0,
    payment_amount: 0,
    installment_count: 1,
    items: [{ product_id: '', quantity: 1, unit_price: 0 }],
  })
  formError.value = ''
  modal.value = true
}

function addLine(): void {
  form.items.push({ product_id: '', quantity: 1, unit_price: 0 })
}

function removeLine(index: number): void {
  if (form.items.length > 1) form.items.splice(index, 1)
}

function onProductChange(line: Line): void {
  const product = products.value.find((p) => p.id === line.product_id)
  if (product) line.unit_price = Number(product.sale_price)
}

function openNewClient(): void {
  clientModal.value = true
}

function onClientSaved(client: Client): void {
  clients.value = [...clients.value, client]
  form.client_id = client.id
  clientModal.value = false
}

function openNewProduct(index: number): void {
  targetLine.value = index
  productModal.value = true
}

function onProductSaved(product: Product): void {
  products.value = [...products.value, product]

  const index = targetLine.value
  if (index !== null && form.items[index]) {
    form.items[index].product_id = product.id
    form.items[index].unit_price = Number(product.sale_price)
  }

  productModal.value = false
  targetLine.value = null
}

async function submit(): Promise<void> {
  formError.value = ''

  if (form.type === 'credit' && !form.client_id) {
    formError.value = t('common.required')

    return
  }

  const items = form.items.filter((line) => line.product_id !== '' && line.quantity > 0)
  if (items.length === 0) {
    formError.value = t('common.required')

    return
  }

  saving.value = true

  try {
    const { data } = await api.post<{ data: Sale }>('/sales', {
      client_id: form.client_id || null,
      type: form.type,
      date: form.date,
      discount: Number(form.discount) || 0,
      items: items.map((line) => ({
        product_id: line.product_id,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
      })),
      payment: form.type === 'cash' ? { amount: Number(form.payment_amount) || total.value, method: 'cash' } : undefined,
      installments: form.type === 'credit' ? { count: Number(form.installment_count) } : undefined,
    })

    modal.value = false
    doneSale.value = data.data
    ui.notify(t('sales.new'))
    await Promise.all([load(), loadRefs()])
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    formError.value = Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? err.response?.data?.message ?? t('common.error')
  } finally {
    saving.value = false
  }
}

function printSale(sale: Sale): void {
  window.open(`${import.meta.env.BASE_URL}print/sale/${sale.id}`, '_blank', 'width=420,height=800')
}

function printDoneSale(): void {
  if (doneSale.value) {
    printSale(doneSale.value)
  }
}

async function exportSales(): Promise<void> {
  exporting.value = true

  try {
    await downloadExcel({ scope: 'sales', type: typeFilter.value || undefined })
    ui.notify(t('reports.exported'))
  } catch {
    ui.notify(t('common.error'), 'error')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), loadRefs()])
})
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('sales.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('sales.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton variant="outline" :loading="exporting" @click="exportSales">
          <Download class="size-4" />
          {{ t('common.export_excel') }}
        </AppButton>

        <AppButton @click="openModal">
          <Plus class="size-4" />
          {{ t('sales.new') }}
        </AppButton>
      </div>
    </header>

    <div class="card p-3">
      <select v-model="typeFilter" class="field max-w-[200px]" @change="page = 1; load()">
        <option value="">{{ t('common.all') }}</option>
        <option value="cash">{{ t('sales.cash') }}</option>
        <option value="credit">{{ t('sales.credit') }}</option>
      </select>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>{{ t('sales.number') }}</th>
              <th>{{ t('common.client') }}</th>
              <th>{{ t('common.date') }}</th>
              <th>{{ t('sales.type') }}</th>
              <th>{{ t('sales.items') }}</th>
              <th class="text-end">{{ t('common.total') }}</th>
              <th>{{ t('common.status') }}</th>
              <th class="w-12 text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</td>
            </tr>
            <tr v-for="sale in sales" v-else :key="sale.id">
              <td class="font-mono text-xs">{{ sale.number }}</td>
              <td>
                <p class="font-semibold">{{ sale.client?.name ?? '—' }}</p>
                <p class="text-xs text-muted">{{ sale.client?.code }}</p>
              </td>
              <td class="text-muted">{{ shortDate(sale.date) }}</td>
              <td>
                <span class="rounded-full border border-line px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider"
                  :class="sale.type === 'credit' ? 'border-gold/40 bg-goldsoft text-gold' : 'text-muted'">
                  {{ sale.type === 'credit' ? t('sales.credit') : t('sales.cash') }}
                </span>
              </td>
              <td class="text-muted">{{ sale.items?.length ?? '—' }}</td>
              <td class="text-end font-semibold">{{ money(sale.total) }}</td>
              <td><AppBadge :status="sale.status" /></td>
              <td class="text-end">
                <button
                  type="button"
                  class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                  :title="t('common.print')"
                  @click="printSale(sale)"
                >
                  <Printer class="size-3.5" />
                </button>
              </td>
            </tr>
            <tr v-if="!loading && sales.length === 0">
              <td colspan="8" class="py-12 text-center text-sm text-muted">{{ t('sales.empty') }}</td>
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

    <AppModal :open="modal" :title="t('sales.new')" :subtitle="t('sales.new_subtitle')" width="sm:max-w-2xl" @close="modal = false">
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="block sm:col-span-2">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.client') }}</span>
            <div class="flex gap-1.5">
              <select id="sale-client" name="client_id" v-model="form.client_id" class="field flex-1">
                <option value="">{{ t('common.select') }}</option>
                <option v-for="client in clients" :key="client.id" :value="client.id">
                  {{ client.name }} — {{ money(client.balance) }}
                </option>
              </select>
              <button
                type="button"
                class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl border border-line bg-surface2 text-gold transition hover:border-gold hover:bg-goldsoft"
                :title="t('clients.new')"
                @click="openNewClient"
              >
                <Plus class="size-4" />
              </button>
            </div>
          </div>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('sales.type') }}</span>
            <select v-model="form.type" class="field">
              <option value="cash">{{ t('sales.cash') }}</option>
              <option value="credit">{{ t('sales.credit') }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.date') }}</span>
            <input id="sale-date" name="date" v-model="form.date" type="date" class="field" />
          </label>
        </div>

        <div class="space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-xs font-semibold text-muted">{{ t('sales.items') }}</span>
            <AppButton size="sm" variant="ghost" type="button" @click="addLine">
              <Plus class="size-3.5" />
              {{ t('common.add') }}
            </AppButton>
          </div>

          <div v-for="(line, index) in form.items" :key="index" class="grid grid-cols-12 items-center gap-2">
            <div class="col-span-6 flex gap-1.5">
              <select
                :id="`sale-product-${index}`"
                :name="`product_${index}`"
                v-model="line.product_id"
                class="field flex-1"
                @change="onProductChange(line)"
              >
                <option value="">{{ t('common.select') }}</option>
                <option v-for="product in products" :key="product.id" :value="product.id">
                  {{ product.name_fr }} ({{ product.sku }})
                </option>
              </select>
              <button
                type="button"
                class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl border border-line bg-surface2 text-gold transition hover:border-gold hover:bg-goldsoft"
                :title="t('products.new')"
                @click="openNewProduct(index)"
              >
                <Plus class="size-4" />
              </button>
            </div>
            <input v-model.number="line.quantity" type="number" min="0.01" step="0.01" class="field col-span-2" />
            <input v-model.number="line.unit_price" type="number" min="0" step="0.01" class="field col-span-2" />
            <div class="col-span-2 flex items-center justify-between gap-2">
              <span class="truncate text-xs font-semibold">{{ money(line.quantity * line.unit_price) }}</span>
              <button type="button" class="text-muted transition hover:text-danger" @click="removeLine(index)">
                <Trash2 class="size-4" />
              </button>
            </div>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">Remise</span>
            <input v-model.number="form.discount" type="number" min="0" step="0.01" class="field" />
          </label>

          <label v-if="form.type === 'cash'" class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('common.amount') }}</span>
            <input v-model.number="form.payment_amount" type="number" min="0" step="0.01" class="field" :placeholder="String(total)" />
          </label>

          <label v-if="form.type === 'credit'" class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('credits.installments') }}</span>
            <input v-model.number="form.installment_count" type="number" min="1" max="36" class="field" />
          </label>

          <div class="flex items-end">
            <div class="w-full rounded-xl border border-gold/30 bg-goldsoft px-3 py-2.5">
              <p class="text-[10px] uppercase tracking-widest text-gold">{{ t('common.total') }}</p>
              <p class="text-lg font-bold text-gold">{{ money(total) }}</p>
            </div>
          </div>
        </div>

        <p v-if="formError" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {{ formError }}
        </p>

        <div class="flex justify-end gap-2 pt-1">
          <AppButton variant="ghost" type="button" @click="modal = false">{{ t('common.cancel') }}</AppButton>
          <AppButton type="submit" :loading="saving">{{ t('common.save') }}</AppButton>
        </div>
      </form>
    </AppModal>

    <AppModal :open="!!doneSale" :title="t('sales.saved')" :subtitle="doneSale?.number" @close="doneSale = null">
      <div class="space-y-5">
        <p class="text-sm text-muted">{{ t('sales.saved_hint') }}</p>

        <div class="flex justify-end gap-2">
          <AppButton variant="ghost" @click="doneSale = null">{{ t('common.close') }}</AppButton>
          <AppButton v-if="doneSale" @click="printDoneSale">
            <Printer class="size-4" />
            {{ t('common.print') }}
          </AppButton>
        </div>
      </div>
    </AppModal>

    <ClientFormModal
      :open="clientModal"
      @close="clientModal = false"
      @saved="onClientSaved"
    />

    <ProductFormModal
      :open="productModal"
      @close="productModal = false; targetLine = null"
      @saved="onProductSaved"
    />
  </div>
</template>
