<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '../../lib/api'
import type { Product } from '../../types'
import AppButton from '../ui/AppButton.vue'
import AppModal from '../ui/AppModal.vue'
import QuickNameModal from '../ui/QuickNameModal.vue'

interface RefItem {
  id: number
  name: string
}

const props = defineProps<{ open: boolean; product?: Product | null }>()

const emit = defineEmits<{
  close: []
  saved: [product: Product]
}>()

const { t } = useI18n()

const brands = ref<RefItem[]>([])
const categories = ref<RefItem[]>([])
const saving = ref(false)
const error = ref('')

const quickBrand = ref(false)
const quickCategory = ref(false)

const form = reactive({
  name: '',
  description: '',
  brand_id: '' as number | '',
  category_id: '' as number | '',
  purchase_price: 0,
  sale_price: 0,
  stock_qty: 0,
  stock_min: 0,
})

async function loadMeta(): Promise<void> {
  const [b, c] = await Promise.all([api.get('/brands'), api.get('/categories')])
  brands.value = b.data.data
  categories.value = c.data.data
}

function reset(): void {
  const p = props.product

  Object.assign(form, {
    name: p?.name_fr ?? p?.name_ar ?? '',
    description: p?.description ?? '',
    brand_id: p?.brand_id ?? '',
    category_id: p?.category_id ?? '',
    purchase_price: p?.purchase_price ?? 0,
    sale_price: p?.sale_price ?? 0,
    stock_qty: p?.stock_qty ?? 0,
    stock_min: p?.stock_min ?? 0,
  })

  error.value = ''
  quickBrand.value = false
  quickCategory.value = false
}

watch(
  () => props.open,
  (open) => {
    if (!open) return

    reset()
    loadMeta()
  },
)

function onBrandCreated(item: RefItem): void {
  brands.value = [...brands.value, item]
  form.brand_id = item.id
}

function onCategoryCreated(item: RefItem): void {
  categories.value = [...categories.value, item]
  form.category_id = item.id
}

async function submit(): Promise<void> {
  saving.value = true
  error.value = ''

  const name = form.name.trim()

  const payload = {
    name_fr: name,
    name_ar: name,
    description: form.description || null,
    brand_id: form.brand_id || null,
    category_id: form.category_id || null,
    purchase_price: form.purchase_price,
    sale_price: form.sale_price,
    stock_qty: form.stock_qty,
    stock_min: form.stock_min,
  }

  try {
    const { data } = props.product
      ? await api.put(`/products/${props.product.id}`, payload)
      : await api.post('/products', payload)

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

onMounted(loadMeta)
</script>

<template>
  <AppModal
    :open="open"
    :title="product ? t('common.edit') : t('products.new')"
    :subtitle="product ? product.sku : t('products.subtitle')"
    @close="emit('close')"
  >
    <form class="space-y-4" @submit.prevent="submit">
      <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
        <div class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.brand') }}</span>
          <select id="product-brand" name="brand_id" v-model="form.brand_id" class="field">
            <option value="">—</option>
            <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
          </select>
        </div>

        <button
          type="button"
          class="mt-auto grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl border border-line bg-surface2 text-gold transition hover:border-gold hover:bg-goldsoft"
          :title="t('settings.new_brand')"
          @click="quickBrand = true"
        >
          <Plus class="size-4" />
        </button>
      </div>

      <label class="block">
        <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.name') }}</span>
        <textarea
          id="product-name"
          name="name"
          v-model="form.name"
          class="field resize-y"
          rows="2"
          required
        />
      </label>

      <label class="block">
        <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.description') }}</span>
        <textarea
          id="product-description"
          name="description"
          v-model="form.description"
          class="field resize-y"
          rows="3"
        />
      </label>

      <details class="rounded-xl border border-line/70 bg-surface2/40 px-3.5 py-3">
        <summary class="cursor-pointer select-none text-xs font-semibold text-muted">
          {{ t('products.price_stock') }}
        </summary>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div class="block sm:col-span-2">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.category') }}</span>
            <div class="flex gap-1.5">
              <select id="product-category" name="category_id" v-model="form.category_id" class="field flex-1">
                <option value="">—</option>
                <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
              </select>
              <button
                type="button"
                class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl border border-line bg-surface2 text-gold transition hover:border-gold hover:bg-goldsoft"
                :title="t('settings.new_category')"
                @click="quickCategory = true"
              >
                <Plus class="size-4" />
              </button>
            </div>
          </div>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.purchase') }}</span>
            <input id="product-purchase" name="purchase_price" v-model.number="form.purchase_price" type="number" min="0" step="0.01" class="field" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.price') }}</span>
            <input id="product-price" name="sale_price" v-model.number="form.sale_price" type="number" min="0" step="0.01" class="field" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('products.stock') }}</span>
            <input id="product-stock" name="stock_qty" v-model.number="form.stock_qty" type="number" step="0.01" class="field" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">Stock min.</span>
            <input id="product-stock-min" name="stock_min" v-model.number="form.stock_min" type="number" min="0" step="0.01" class="field" />
          </label>
        </div>
      </details>

      <p v-if="error" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
        {{ error }}
      </p>

      <div class="flex justify-end gap-2 pt-1">
        <AppButton variant="ghost" type="button" @click="emit('close')">{{ t('common.cancel') }}</AppButton>
        <AppButton type="submit" :loading="saving">{{ t('common.save') }}</AppButton>
      </div>
    </form>

    <QuickNameModal
      :open="quickBrand"
      :title="t('settings.new_brand')"
      :label="t('products.brand')"
      endpoint="/brands"
      @close="quickBrand = false"
      @created="onBrandCreated"
    />

    <QuickNameModal
      :open="quickCategory"
      :title="t('settings.new_category')"
      :label="t('products.category')"
      endpoint="/categories"
      @close="quickCategory = false"
      @created="onCategoryCreated"
    />
  </AppModal>
</template>
