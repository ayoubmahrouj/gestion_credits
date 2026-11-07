<script setup lang="ts">
import { Ban, Check, DatabaseBackup, Download, Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '../components/ui/AppButton.vue'
import AppCard from '../components/ui/AppCard.vue'
import api from '../lib/api'
import { dateTime } from '../lib/format'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

type Kind = 'brands' | 'categories'

interface RefItem {
  id: number
  name: string
  slug: string
  is_active: boolean
  products_count: number
}

const { t } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()

const isAdmin = computed(() => auth.user?.role === 'admin')

const brands = ref<RefItem[]>([])
const categories = ref<RefItem[]>([])
const loading = ref(true)
const savingBrand = ref(false)
const savingCategory = ref(false)
const brandName = ref('')
const categoryName = ref('')
const error = ref('')
const showDisabled = ref(false)

const editingKey = ref<string | null>(null)
const editingValue = ref('')
const busyId = ref<number | null>(null)

const savingShop = ref(false)
const savingPassword = ref(false)
const passwordError = ref('')

const backups = ref<{ name: string; size: number; created_at: string }[]>([])
const loadingBackups = ref(false)
const creatingBackup = ref(false)
const busyBackup = ref<string | null>(null)

const password = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const shop = reactive({
  shop_name: '',
  shop_tagline: '',
  shop_phone: '',
  shop_address: '',
  shop_ice: '',
  ticket_footer: '',
  currency: 'DH',
})

function productsLabel(count: number): string {
  return t(count > 1 ? 'settings.products_many' : 'settings.products_one', { n: count })
}

function message(e: unknown): string {  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }

  return Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? err.response?.data?.message ?? t('common.error')
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const params = { with_disabled: showDisabled.value || undefined }
    const [b, c] = await Promise.all([api.get('/brands', { params }), api.get('/categories', { params })])
    brands.value = b.data.data
    categories.value = c.data.data
  } finally {
    loading.value = false
  }
}

async function add(kind: Kind): Promise<void> {
  const name = kind === 'brands' ? brandName.value.trim() : categoryName.value.trim()

  if (!name) return

  if (kind === 'brands') savingBrand.value = true
  else savingCategory.value = true

  error.value = ''

  try {
    await api.post(`/${kind}`, { name })

    if (kind === 'brands') brandName.value = ''
    else categoryName.value = ''

    ui.notify(t('settings.added'))
    await load()
  } catch (e) {
    error.value = message(e)
  } finally {
    savingBrand.value = false
    savingCategory.value = false
  }
}

function startEdit(kind: Kind, item: RefItem): void {
  editingKey.value = `${kind}-${item.id}`
  editingValue.value = item.name
}

function cancelEdit(): void {
  editingKey.value = null
  editingValue.value = ''
}

async function saveEdit(kind: Kind, item: RefItem): Promise<void> {
  const name = editingValue.value.trim()

  if (!name || name === item.name) {
    cancelEdit()

    return
  }

  busyId.value = item.id
  error.value = ''

  try {
    await api.put(`/${kind}/${item.id}`, { name })
    ui.notify(t('settings.updated'))
    cancelEdit()
    await load()
  } catch (e) {
    error.value = message(e)
  } finally {
    busyId.value = null
  }
}

async function toggleActive(kind: Kind, item: RefItem): Promise<void> {
  busyId.value = item.id
  error.value = ''

  try {
    if (item.is_active) {
      if (!window.confirm(t('common.confirm_disable', { name: item.name }))) return

      await api.delete(`/${kind}/${item.id}`)
      ui.notify(t('common.disabled_success'))
    } else {
      await api.patch(`/${kind}/${item.id}/restore`)
      ui.notify(t('common.restored'))
    }

    await load()
  } catch (e) {
    error.value = message(e)
  } finally {
    busyId.value = null
  }
}

async function loadShop(): Promise<void> {
  const { data } = await api.get('/settings')
  Object.assign(shop, data.data)
}

async function saveShop(): Promise<void> {
  savingShop.value = true
  error.value = ''

  try {
    const { data } = await api.put('/settings', { ...shop })
    Object.assign(shop, data.data)
    ui.notify(t('settings.shop.saved'))
  } catch (e) {
    error.value = message(e)
  } finally {
    savingShop.value = false
  }
}

async function changePassword(): Promise<void> {
  savingPassword.value = true
  passwordError.value = ''

  try {
    await api.patch('/auth/password', { ...password })
    password.current_password = ''
    password.password = ''
    password.password_confirmation = ''
    ui.notify(t('settings.account.saved'))
  } catch (e) {
    passwordError.value = message(e)
  } finally {
    savingPassword.value = false
  }
}

function fileSize(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(2)} Mo`

  return `${Math.max(1, Math.round(bytes / 1024))} Ko`
}

async function loadBackups(): Promise<void> {
  loadingBackups.value = true

  try {
    const { data } = await api.get('/backups')
    backups.value = data.data
  } catch {
    // réservé aux administrateurs
  } finally {
    loadingBackups.value = false
  }
}

async function createBackup(): Promise<void> {
  creatingBackup.value = true
  error.value = ''

  try {
    const { data } = await api.post('/backups')
    backups.value = [data.data, ...backups.value]
    ui.notify(t('settings.backup.created'))
  } catch (e) {
    error.value = message(e)
  } finally {
    creatingBackup.value = false
  }
}

async function downloadBackup(item: { name: string }): Promise<void> {
  busyBackup.value = item.name

  try {
    const { data } = await api.get(`/backups/${item.name}`, { responseType: 'blob' })
    const url = URL.createObjectURL(data as Blob)
    const link = document.createElement('a')
    link.href = url
    link.download = item.name
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = message(e)
  } finally {
    busyBackup.value = null
  }
}

async function deleteBackup(item: { name: string }): Promise<void> {
  if (!window.confirm(t('settings.backup.confirm'))) return

  busyBackup.value = item.name

  try {
    await api.delete(`/backups/${item.name}`)
    backups.value = backups.value.filter((backup) => backup.name !== item.name)
    ui.notify(t('settings.backup.deleted'))
  } catch (e) {
    error.value = message(e)
  } finally {
    busyBackup.value = null
  }
}

onMounted(async () => {
  await Promise.all([load(), loadShop()])

  if (isAdmin.value) {
    await loadBackups()
  }
})
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('settings.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('settings.subtitle') }}</p>
      </div>

      <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-surface2 px-3 py-2.5 text-xs text-muted">
        <input v-model="showDisabled" type="checkbox" class="size-3.5 accent-[var(--gold)]" @change="load" />
        {{ t('common.show_disabled') }}
      </label>
    </header>

    <p v-if="error" class="card border-danger/30 bg-danger/10 p-3 text-xs text-danger">{{ error }}</p>

    <AppCard :title="t('settings.shop.title')" :subtitle="t('settings.shop.subtitle')">
      <form class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" @submit.prevent="saveShop">
        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.name') }}</span>
          <input v-model="shop.shop_name" class="field" maxlength="120" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.tagline') }}</span>
          <input v-model="shop.shop_tagline" class="field" maxlength="160" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.phone') }}</span>
          <input v-model="shop.shop_phone" class="field" maxlength="40" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.address') }}</span>
          <input v-model="shop.shop_address" class="field" maxlength="200" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.ice') }}</span>
          <input v-model="shop.shop_ice" class="field" maxlength="40" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.currency') }}</span>
          <input v-model="shop.currency" class="field" maxlength="10" />
        </label>

        <label class="block sm:col-span-2">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.shop.footer') }}</span>
          <input v-model="shop.ticket_footer" class="field" maxlength="200" />
        </label>

        <div class="flex items-end justify-end sm:col-span-2 lg:col-span-3">
          <AppButton type="submit" :loading="savingShop">{{ t('common.save') }}</AppButton>
        </div>
      </form>
    </AppCard>

    <AppCard :title="t('settings.account.title')" :subtitle="t('settings.account.subtitle')">
      <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="changePassword">
        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.account.current') }}</span>
          <input
            v-model="password.current_password"
            type="password"
            class="field"
            required
            autocomplete="current-password"
          />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.account.new') }}</span>
          <input
            v-model="password.password"
            type="password"
            class="field"
            required
            minlength="6"
            autocomplete="new-password"
          />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('settings.account.confirm') }}</span>
          <input
            v-model="password.password_confirmation"
            type="password"
            class="field"
            required
            minlength="6"
            autocomplete="new-password"
          />
        </label>

        <p v-if="passwordError" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger sm:col-span-3">
          {{ passwordError }}
        </p>

        <div class="flex justify-end sm:col-span-3">
          <AppButton type="submit" :loading="savingPassword">{{ t('settings.account.save') }}</AppButton>
        </div>
      </form>
    </AppCard>

    <AppCard v-if="isAdmin" :title="t('settings.backup.title')" :subtitle="t('settings.backup.subtitle')">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-muted">{{ t('settings.backup.daily') }}</p>
        <AppButton :loading="creatingBackup" @click="createBackup">
          <DatabaseBackup class="size-4" />
          {{ t('settings.backup.create') }}
        </AppButton>
      </div>

      <ul class="divide-y divide-line/70">
        <li v-if="loadingBackups" class="py-6 text-center text-sm text-muted">{{ t('common.loading') }}</li>

        <li v-for="backup in backups" v-else :key="backup.name" class="flex items-center justify-between gap-3 py-2.5">
          <div class="min-w-0">
            <p class="truncate font-mono text-xs">{{ backup.name }}</p>
            <p class="text-[11px] text-muted">{{ dateTime(backup.created_at) }} · {{ fileSize(backup.size) }}</p>
          </div>

          <div class="flex shrink-0 items-center gap-1">
            <button
              class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold disabled:opacity-40"
              :disabled="busyBackup === backup.name"
              :title="t('settings.backup.download')"
              @click="downloadBackup(backup)"
            >
              <Download class="size-4" />
            </button>

            <button
              class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-danger/15 hover:text-danger disabled:opacity-40"
              :disabled="busyBackup === backup.name"
              :title="t('common.delete')"
              @click="deleteBackup(backup)"
            >
              <Trash2 class="size-4" />
            </button>
          </div>
        </li>

        <li v-if="!loadingBackups && backups.length === 0" class="py-6 text-center text-sm text-muted">
          {{ t('settings.backup.empty') }}
        </li>
      </ul>
    </AppCard>

    <div class="grid gap-4 lg:grid-cols-2">
      <AppCard :title="t('settings.brands')" :padded="false">
        <div class="border-b border-line/70 p-4">
          <form class="flex gap-2" @submit.prevent="add('brands')">
            <input
              id="brand-name"
              v-model="brandName"
              name="brand"
              class="field"
              :placeholder="t('settings.name_ph')"
            />
            <AppButton type="submit" :loading="savingBrand">
              <Plus class="size-4" />
              {{ t('common.add') }}
            </AppButton>
          </form>
        </div>

        <ul class="divide-y divide-line/70">
          <li v-if="loading" class="p-6 text-center text-sm text-muted">{{ t('common.loading') }}</li>

          <li
            v-for="brand in brands"
            v-else
            :key="brand.id"
            class="flex items-center justify-between gap-2 px-4 py-2.5"
            :class="brand.is_active ? '' : 'opacity-50'"
          >
            <div class="min-w-0 flex-1">
              <form v-if="editingKey === `brands-${brand.id}`" class="flex items-center gap-1" @submit.prevent="saveEdit('brands', brand)">
                <input v-model="editingValue" class="field py-1.5 text-xs" autofocus />
                <button type="submit" class="grid size-7 place-items-center rounded-lg text-success hover:bg-surface2">
                  <Check class="size-4" />
                </button>
                <button type="button" class="grid size-7 place-items-center rounded-lg text-muted hover:bg-surface2" @click="cancelEdit">
                  <X class="size-4" />
                </button>
              </form>

              <template v-else>
                <p class="truncate text-sm font-medium">{{ brand.name }}</p>
                <p class="text-[11px] text-muted">
                  {{ productsLabel(brand.products_count) }}
                  <span v-if="!brand.is_active" class="text-danger">· {{ t('common.disabled') }}</span>
                </p>
              </template>
            </div>

            <div v-if="editingKey !== `brands-${brand.id}`" class="flex items-center gap-1">
              <button
                class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                :title="t('common.edit')"
                @click="startEdit('brands', brand)"
              >
                <Pencil class="size-3.5" />
              </button>

              <button
                class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-danger disabled:opacity-40"
                :disabled="busyId === brand.id"
                :title="brand.is_active ? t('common.disable') : t('common.enable')"
                @click="toggleActive('brands', brand)"
              >
                <Ban v-if="brand.is_active" class="size-3.5" />
                <RotateCcw v-else class="size-3.5" />
              </button>
            </div>
          </li>

          <li v-if="!loading && brands.length === 0" class="p-8 text-center text-sm text-muted">
            {{ t('settings.no_brands') }}
          </li>
        </ul>
      </AppCard>

      <AppCard :title="t('settings.categories')" :padded="false">
        <div class="border-b border-line/70 p-4">
          <form class="flex gap-2" @submit.prevent="add('categories')">
            <input
              id="category-name"
              v-model="categoryName"
              name="category"
              class="field"
              :placeholder="t('settings.name_ph')"
            />
            <AppButton type="submit" :loading="savingCategory">
              <Plus class="size-4" />
              {{ t('common.add') }}
            </AppButton>
          </form>
        </div>

        <ul class="divide-y divide-line/70">
          <li v-if="loading" class="p-6 text-center text-sm text-muted">{{ t('common.loading') }}</li>

          <li
            v-for="category in categories"
            v-else
            :key="category.id"
            class="flex items-center justify-between gap-2 px-4 py-2.5"
            :class="category.is_active ? '' : 'opacity-50'"
          >
            <div class="min-w-0 flex-1">
              <form
                v-if="editingKey === `categories-${category.id}`"
                class="flex items-center gap-1"
                @submit.prevent="saveEdit('categories', category)"
              >
                <input v-model="editingValue" class="field py-1.5 text-xs" autofocus />
                <button type="submit" class="grid size-7 place-items-center rounded-lg text-success hover:bg-surface2">
                  <Check class="size-4" />
                </button>
                <button type="button" class="grid size-7 place-items-center rounded-lg text-muted hover:bg-surface2" @click="cancelEdit">
                  <X class="size-4" />
                </button>
              </form>

              <template v-else>
                <p class="truncate text-sm font-medium">{{ category.name }}</p>
                <p class="text-[11px] text-muted">
                  {{ productsLabel(category.products_count) }}
                  <span v-if="!category.is_active" class="text-danger">· {{ t('common.disabled') }}</span>
                </p>
              </template>
            </div>

            <div v-if="editingKey !== `categories-${category.id}`" class="flex items-center gap-1">
              <button
                class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                :title="t('common.edit')"
                @click="startEdit('categories', category)"
              >
                <Pencil class="size-3.5" />
              </button>

              <button
                class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-danger disabled:opacity-40"
                :disabled="busyId === category.id"
                :title="category.is_active ? t('common.disable') : t('common.enable')"
                @click="toggleActive('categories', category)"
              >
                <Ban v-if="category.is_active" class="size-3.5" />
                <RotateCcw v-else class="size-3.5" />
              </button>
            </div>
          </li>

          <li v-if="!loading && categories.length === 0" class="p-8 text-center text-sm text-muted">
            {{ t('settings.no_categories') }}
          </li>
        </ul>
      </AppCard>
    </div>

    <p class="flex items-center gap-2 text-[11px] text-muted">
      <Trash2 class="size-3.5" />
      {{ t('common.disabled_notice') }}
    </p>
  </div>
</template>
