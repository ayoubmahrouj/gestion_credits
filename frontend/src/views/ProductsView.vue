<script setup lang="ts">
import { Ban, Download, Pencil, PackagePlus, RotateCcw, Search } from 'lucide-vue-next'
import { nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ProductFormModal from '../components/forms/ProductFormModal.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import api from '../lib/api'
import { money, num } from '../lib/format'
import { downloadExcel } from '../lib/export'
import { useUiStore } from '../stores/ui'
import type { Paginated, Product } from '../types'

const { t } = useI18n()
const ui = useUiStore()

const products = ref<Product[]>([])
const meta = ref<Paginated<Product>['meta'] | null>(null)
const loading = ref(true)
const search = ref('')
const showDisabled = ref(false)
const page = ref(1)

const modal = ref(false)
const editing = ref<Product | null>(null)

type CellField = 'name_fr' | 'name_ar' | 'brand_id' | 'sale_price' | 'stock_qty'

const brands = ref<{ id: number; name: string }[]>([])
const editingCell = ref<{ id: number; field: CellField } | null>(null)
const cellValue = ref<string | number>('')

let timer: ReturnType<typeof setTimeout> | undefined

watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => {
    page.value = 1
    load()
  }, 300)
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get<Paginated<Product>>('/products', {
      params: {
        search: search.value || undefined,
        with_disabled: showDisabled.value || undefined,
        page: page.value,
        per_page: 12,
      },
    })
    products.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

function go(next: number): void {
  page.value = Math.min(Math.max(1, next), meta.value?.last_page ?? 1)
  load()
}

function openModal(product?: Product): void {
  editing.value = product ?? null
  modal.value = true
}

function onSaved(): void {
  ui.notify(t('common.created'))
  editing.value = null
  load()
}

function message(e: unknown): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }

  return Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? err.response?.data?.message ?? t('common.error')
}

async function loadBrands(): Promise<void> {
  const { data } = await api.get('/brands')
  brands.value = data.data
}

function isEditing(product: Product, field: CellField): boolean {
  return editingCell.value?.id === product.id && editingCell.value?.field === field
}

function startEdit(product: Product, field: CellField): void {
  editingCell.value = { id: product.id, field }
  cellValue.value = field === 'brand_id' ? (product.brand_id ?? '') : (product[field] as string | number)

  nextTick(() => {
    const el = document.querySelector<HTMLInputElement | HTMLSelectElement>('[data-inline-edit]')
    el?.focus()
    if (el instanceof HTMLInputElement) el.select()
  })
}

function cancelEdit(): void {
  editingCell.value = null
}

/**
 * Modification directe dans le tableau (clic sur une cellule).
 */
async function commitEdit(product: Product, field: CellField): Promise<void> {
  if (!isEditing(product, field)) return

  const raw = cellValue.value
  editingCell.value = null

  const value = field === 'brand_id'
    ? (raw === '' || raw === null ? null : Number(raw))
    : field === 'sale_price' || field === 'stock_qty'
      ? Number(raw || 0)
      : String(raw ?? '')

  const current = field === 'brand_id' ? product.brand_id : product[field]

  if (value === current) return

  try {
    const { data } = await api.put(`/products/${product.id}`, {
      sku: product.sku,
      name_fr: product.name_fr,
      name_ar: product.name_ar,
      description: product.description,
      brand_id: product.brand_id,
      category_id: product.category_id,
      purchase_price: product.purchase_price,
      sale_price: product.sale_price,
      stock_qty: product.stock_qty,
      stock_min: product.stock_min,
      [field]: value,
    })

    const index = products.value.findIndex((p) => p.id === product.id)
    if (index >= 0) products.value[index] = data.data

    ui.notify(t('common.updated'))
  } catch (e: unknown) {
    ui.notify(message(e), 'error')
    load()
  }
}

async function toggleActive(product: Product): Promise<void> {
  try {
    if (product.is_active) {
      if (!window.confirm(t('common.confirm_disable', { name: product.name_fr }))) return

      await api.delete(`/products/${product.id}`)
      ui.notify(t('common.disabled_success'))
    } else {
      await api.patch(`/products/${product.id}/restore`)
      ui.notify(t('common.restored'))
    }

    await load()
  } catch (e: unknown) {
    ui.notify(message(e), 'error')
  }
}

const exporting = ref(false)

async function exportProducts(): Promise<void> {
  exporting.value = true

  try {
    await downloadExcel({ scope: 'products' })
    ui.notify(t('reports.exported'))
  } catch {
    ui.notify(t('common.error'), 'error')
  } finally {
    exporting.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), loadBrands()])
})
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('products.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('products.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton variant="outline" :loading="exporting" @click="exportProducts">
          <Download class="size-4" />
          {{ t('common.export_excel') }}
        </AppButton>

        <AppButton @click="openModal()">
          <PackagePlus class="size-4" />
          {{ t('products.new') }}
        </AppButton>
      </div>
    </header>

    <div class="card flex flex-wrap items-center gap-3 p-3">
      <div class="relative min-w-[220px] flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input id="product-search" v-model="search" name="search" class="field ps-10" :placeholder="t('common.search')" />
      </div>

      <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-surface2 px-3 py-2.5 text-xs text-muted">
        <input v-model="showDisabled" type="checkbox" class="size-3.5 accent-[var(--gold)]" />
        {{ t('common.show_disabled') }}
      </label>

      <p class="ms-auto hidden text-[11px] text-muted sm:block">{{ t('products.inline_hint') }}</p>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>{{ t('products.sku') }}</th>
              <th>{{ t('products.name') }}</th>
              <th>{{ t('products.brand') }}</th>
              <th>{{ t('products.stock') }}</th>
              <th>{{ t('products.price') }}</th>
              <th>{{ t('common.status') }}</th>
              <th class="text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</td>
            </tr>
            <tr v-for="product in products" v-else :key="product.id" :class="product.is_active ? '' : 'opacity-50'">
              <td class="font-mono text-xs text-muted">{{ product.sku }}</td>
              <td>
                <input
                  v-if="isEditing(product, 'name_fr')"
                  v-model="cellValue"
                  data-inline-edit
                  class="field mb-1 py-1.5 text-xs"
                  @keyup.enter="commitEdit(product, 'name_fr')"
                  @keyup.esc="cancelEdit"
                  @blur="commitEdit(product, 'name_fr')"
                />
                <p
                  v-else
                  class="cursor-text font-semibold transition hover:text-gold"
                  @click="startEdit(product, 'name_fr')"
                >
                  {{ product.name_fr }}
                </p>

                <input
                  v-if="isEditing(product, 'name_ar')"
                  v-model="cellValue"
                  data-inline-edit
                  dir="rtl"
                  class="field mt-1 py-1.5 text-xs"
                  @keyup.enter="commitEdit(product, 'name_ar')"
                  @keyup.esc="cancelEdit"
                  @blur="commitEdit(product, 'name_ar')"
                />
                <p
                  v-else-if="product.name_ar && product.name_ar !== product.name_fr"
                  class="cursor-text text-xs text-muted transition hover:text-gold"
                  @click="startEdit(product, 'name_ar')"
                >
                  {{ product.name_ar }}
                </p>
              </td>
              <td>
                <select
                  v-if="isEditing(product, 'brand_id')"
                  v-model="cellValue"
                  data-inline-edit
                  class="field py-1.5 text-xs"
                  @change="commitEdit(product, 'brand_id')"
                  @blur="commitEdit(product, 'brand_id')"
                >
                  <option value="">—</option>
                  <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                </select>
                <span
                  v-else
                  class="cursor-pointer text-muted transition hover:text-gold"
                  @click="startEdit(product, 'brand_id')"
                >
                  {{ product.brand?.name ?? '—' }}
                </span>
              </td>
              <td>
                <input
                  v-if="isEditing(product, 'stock_qty')"
                  v-model="cellValue"
                  data-inline-edit
                  type="number"
                  step="0.01"
                  class="field py-1.5 text-xs"
                  @keyup.enter="commitEdit(product, 'stock_qty')"
                  @keyup.esc="cancelEdit"
                  @blur="commitEdit(product, 'stock_qty')"
                />
                <template v-else>
                  <span
                    class="cursor-text font-semibold transition hover:text-gold"
                    :class="product.is_low_stock ? 'text-danger' : ''"
                    @click="startEdit(product, 'stock_qty')"
                  >
                    {{ num(product.stock_qty) }}
                  </span>
                  <p v-if="product.is_low_stock" class="text-[10px] font-semibold text-danger">{{ t('products.low') }}</p>
                </template>
              </td>
              <td>
                <input
                  v-if="isEditing(product, 'sale_price')"
                  v-model="cellValue"
                  data-inline-edit
                  type="number"
                  min="0"
                  step="0.01"
                  class="field py-1.5 text-xs"
                  @keyup.enter="commitEdit(product, 'sale_price')"
                  @keyup.esc="cancelEdit"
                  @blur="commitEdit(product, 'sale_price')"
                />
                <span
                  v-else
                  class="cursor-text font-semibold transition hover:text-gold"
                  @click="startEdit(product, 'sale_price')"
                >
                  {{ money(product.sale_price) }}
                </span>
              </td>
              <td><AppBadge :status="product.is_active ? 'active' : 'disabled'" /></td>
              <td class="text-end">
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                    :title="t('common.edit')"
                    @click="openModal(product)"
                  >
                    <Pencil class="size-3.5" />
                  </button>

                  <button
                    class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-danger"
                    :title="product.is_active ? t('common.disable') : t('common.enable')"
                    @click="toggleActive(product)"
                  >
                    <Ban v-if="product.is_active" class="size-3.5" />
                    <RotateCcw v-else class="size-3.5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && products.length === 0">
              <td colspan="7" class="py-12 text-center text-sm text-muted">{{ t('products.empty') }}</td>
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

    <ProductFormModal :open="modal" :product="editing" @close="modal = false; editing = null" @saved="onSaved" />
  </div>
</template>
