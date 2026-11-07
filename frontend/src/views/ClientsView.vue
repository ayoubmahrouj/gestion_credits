<script setup lang="ts">
import { Ban, Download, Eye, Pencil, Plus, RotateCcw, Search } from 'lucide-vue-next'
import { nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import ClientFormModal from '../components/forms/ClientFormModal.vue'
import AppBadge from '../components/ui/AppBadge.vue'
import AppButton from '../components/ui/AppButton.vue'
import api from '../lib/api'
import { money } from '../lib/format'
import { downloadExcel } from '../lib/export'
import { useUiStore } from '../stores/ui'
import type { Client, Paginated } from '../types'

const { t } = useI18n()
const ui = useUiStore()

const clients = ref<Client[]>([])
const meta = ref<Paginated<Client>['meta'] | null>(null)
const loading = ref(true)
const search = ref('')
const status = ref('')
const showDisabled = ref(false)
const page = ref(1)

const modal = ref(false)
const editing = ref<Client | null>(null)

type CellField = 'name' | 'phone' | 'credit_limit'

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

watch(status, () => {
  page.value = 1
  load()
})

watch(showDisabled, () => {
  page.value = 1
  load()
})

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get<Paginated<Client>>('/clients', {
      params: {
        search: search.value || undefined,
        status: status.value || undefined,
        with_disabled: showDisabled.value || undefined,
        page: page.value,
        per_page: 12,
      },
    })
    clients.value = data.data
    meta.value = data.meta
  } finally {
    loading.value = false
  }
}

function go(next: number): void {
  page.value = Math.min(Math.max(1, next), meta.value?.last_page ?? 1)
  load()
}

function openModal(client?: Client): void {
  editing.value = client ?? null
  modal.value = true
}

function message(e: unknown): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }

  return Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? err.response?.data?.message ?? t('common.error')
}

function onSaved(): void {
  ui.notify(t('common.created'))
  editing.value = null
  load()
}

function isEditing(client: Client, field: CellField): boolean {
  return editingCell.value?.id === client.id && editingCell.value?.field === field
}

function startEdit(client: Client, field: CellField): void {
  editingCell.value = { id: client.id, field }
  cellValue.value = (client[field] ?? '') as string | number

  nextTick(() => {
    const el = document.querySelector<HTMLInputElement>('[data-inline-edit]')
    el?.focus()
    el?.select()
  })
}

function cancelEdit(): void {
  editingCell.value = null
}

async function commitEdit(client: Client, field: CellField): Promise<void> {
  if (!isEditing(client, field)) return

  const raw = cellValue.value
  editingCell.value = null

  const value = field === 'credit_limit' ? Number(raw || 0) : String(raw ?? '')

  if (value === client[field]) return

  try {
    const { data } = await api.put(`/clients/${client.id}`, {
      name: client.name,
      type: client.type,
      phone: client.phone,
      whatsapp: client.whatsapp,
      email: client.email,
      city: client.city,
      credit_limit: client.credit_limit,
      status: client.status,
      [field]: value,
    })

    const index = clients.value.findIndex((c) => c.id === client.id)
    if (index >= 0) clients.value[index] = data.data

    ui.notify(t('common.updated'))
  } catch (e: unknown) {
    ui.notify(message(e), 'error')
    load()
  }
}

async function toggleActive(client: Client): Promise<void> {
  try {
    if (client.is_active) {
      if (!window.confirm(t('common.confirm_disable', { name: client.name }))) return

      await api.delete(`/clients/${client.id}`)
      ui.notify(t('common.disabled_success'))
    } else {
      await api.patch(`/clients/${client.id}/restore`)
      ui.notify(t('common.restored'))
    }

    await load()
  } catch (e: unknown) {
    ui.notify(message(e), 'error')
  }
}

const exporting = ref(false)

async function exportClients(): Promise<void> {
  exporting.value = true

  try {
    await downloadExcel({ scope: 'clients' })
    ui.notify(t('reports.exported'))
  } catch {
    ui.notify(t('common.error'), 'error')
  } finally {
    exporting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('clients.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('clients.subtitle') }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <AppButton variant="outline" :loading="exporting" @click="exportClients">
          <Download class="size-4" />
          {{ t('common.export_excel') }}
        </AppButton>

        <AppButton @click="openModal()">
          <Plus class="size-4" />
          {{ t('clients.new') }}
        </AppButton>
      </div>
    </header>

    <div class="card flex flex-wrap items-center gap-3 p-3">
      <div class="relative min-w-[220px] flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input id="client-search" v-model="search" name="search" class="field ps-10" :placeholder="t('common.search')" />
      </div>

      <select id="client-status" v-model="status" name="status" class="field max-w-[180px]">
        <option value="">{{ t('common.all') }}</option>
        <option value="active">{{ t('status.active') }}</option>
        <option value="blocked">{{ t('status.blocked') }}</option>
        <option value="archived">{{ t('status.archived') }}</option>
      </select>

      <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-surface2 px-3 py-2.5 text-xs text-muted">
        <input v-model="showDisabled" type="checkbox" class="size-3.5 accent-[var(--gold)]" />
        {{ t('common.show_disabled') }}
      </label>

      <p class="ms-auto hidden text-[11px] text-muted sm:block">{{ t('clients.inline_hint') }}</p>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>{{ t('common.client') }}</th>
              <th>{{ t('clients.balance') }}</th>
              <th>{{ t('clients.limit') }}</th>
              <th>{{ t('clients.available') }}</th>
              <th>{{ t('clients.score') }}</th>
              <th>{{ t('common.status') }}</th>
              <th class="text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</td>
            </tr>
            <tr v-for="client in clients" v-else :key="client.id" :class="client.is_active ? '' : 'opacity-50'">
              <td>
                <input
                  v-if="isEditing(client, 'name')"
                  v-model="cellValue"
                  data-inline-edit
                  class="field mb-1 py-1.5 text-xs"
                  @keyup.enter="commitEdit(client, 'name')"
                  @keyup.esc="cancelEdit"
                  @blur="commitEdit(client, 'name')"
                />
                <p
                  v-else
                  class="cursor-text font-semibold transition hover:text-gold"
                  @click="startEdit(client, 'name')"
                >
                  {{ client.name }}
                </p>

                <div class="mt-0.5 flex items-center gap-1 whitespace-nowrap text-xs text-muted">
                  <span>{{ client.code }}</span>
                  <span>·</span>
                  <input
                    v-if="isEditing(client, 'phone')"
                    v-model="cellValue"
                    data-inline-edit
                    class="field w-32 px-2 py-0.5 text-xs"
                    @keyup.enter="commitEdit(client, 'phone')"
                    @keyup.esc="cancelEdit"
                    @blur="commitEdit(client, 'phone')"
                  />
                  <span
                    v-else
                    class="cursor-text transition hover:text-gold"
                    @click="startEdit(client, 'phone')"
                  >
                    {{ client.phone || '—' }}
                  </span>
                </div>
              </td>
              <td class="whitespace-nowrap">
                <span class="font-semibold" :class="client.balance > 0 ? 'text-gold' : 'text-muted'">
                  {{ money(client.balance) }}
                </span>
              </td>
              <td class="whitespace-nowrap">
                <input
                  v-if="isEditing(client, 'credit_limit')"
                  v-model="cellValue"
                  data-inline-edit
                  type="number"
                  min="0"
                  step="0.01"
                  class="field py-1.5 text-xs"
                  @keyup.enter="commitEdit(client, 'credit_limit')"
                  @keyup.esc="cancelEdit"
                  @blur="commitEdit(client, 'credit_limit')"
                />
                <span
                  v-else
                  class="cursor-text text-muted transition hover:text-gold"
                  @click="startEdit(client, 'credit_limit')"
                >
                  {{ money(client.credit_limit) }}
                </span>
              </td>
              <td class="whitespace-nowrap text-muted">{{ money(client.available_credit) }}</td>
              <td>
                <div class="flex items-center gap-2">
                  <div class="h-1.5 w-14 overflow-hidden rounded-full bg-surface2">
                    <div
                      class="h-full rounded-full"
                      :class="client.score >= 60 ? 'bg-success' : client.score >= 40 ? 'bg-warn' : 'bg-danger'"
                      :style="{ width: `${client.score}%` }"
                    />
                  </div>
                  <span class="text-xs text-muted">{{ client.score }}</span>
                </div>
              </td>
              <td><AppBadge :status="client.is_active ? client.status : 'disabled'" /></td>
              <td class="text-end">
                <div class="flex items-center justify-end gap-1">
                  <RouterLink
                    :to="{ name: 'client-detail', params: { id: client.id } }"
                    class="grid size-7 place-items-center rounded-lg text-gold transition hover:bg-surface2"
                    :title="t('common.view_all')"
                  >
                    <Eye class="size-3.5" />
                  </RouterLink>

                  <button
                    class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                    :title="t('common.edit')"
                    @click="openModal(client)"
                  >
                    <Pencil class="size-3.5" />
                  </button>

                  <button
                    class="grid size-7 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-danger"
                    :title="client.is_active ? t('common.disable') : t('common.enable')"
                    @click="toggleActive(client)"
                  >
                    <Ban v-if="client.is_active" class="size-3.5" />
                    <RotateCcw v-else class="size-3.5" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && clients.length === 0">
              <td colspan="7" class="py-12 text-center text-sm text-muted">{{ t('clients.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="meta" class="flex items-center justify-between gap-3 border-t border-line/70 px-4 py-3">
        <p class="text-[11px] text-muted">
          {{ t('common.page') }} {{ meta.current_page }} {{ t('common.of') }} {{ meta.last_page }}
          · {{ meta.total }}
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

    <ClientFormModal :open="modal" :client="editing" @close="modal = false; editing = null" @saved="onSaved" />
  </div>
</template>
