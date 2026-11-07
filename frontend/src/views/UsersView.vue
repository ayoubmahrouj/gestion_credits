<script setup lang="ts">
import { Ban, KeyRound, Pencil, Plus, RotateCcw, Search, ShieldCheck } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppButton from '../components/ui/AppButton.vue'
import AppModal from '../components/ui/AppModal.vue'
import api from '../lib/api'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

type Role = 'admin' | 'manager' | 'cashier' | 'accountant'

interface ManagedUser {
  id: number
  name: string
  username: string | null
  email: string | null
  phone: string | null
  role: Role
  is_active: boolean
  is_self: boolean
  created_at: string
}

const { t } = useI18n()
const ui = useUiStore()
const auth = useAuthStore()

const users = ref<ManagedUser[]>([])
const loading = ref(true)
const search = ref('')
const showDisabled = ref(false)

const modal = ref(false)
const editing = ref<ManagedUser | null>(null)
const saving = ref(false)
const formError = ref('')

const passwordModal = ref(false)
const passwordTarget = ref<ManagedUser | null>(null)
const savingPassword = ref(false)
const passwordError = ref('')

const form = reactive({
  name: '',
  username: '',
  email: '',
  phone: '',
  role: 'cashier' as Role,
  is_active: true,
  password: '',
  password_confirmation: '',
})

const passwordForm = reactive({
  password: '',
  password_confirmation: '',
})

const roles: Role[] = ['admin', 'manager', 'cashier', 'accountant']

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()

  if (!q) return users.value

  return users.value.filter((u) =>
    [u.name, u.username, u.email, u.phone, t(`users.roles.${u.role}`)]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(q)),
  )
})

function message(e: unknown): string {
  const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }

  return Object.values(err.response?.data?.errors ?? {})[0]?.[0] ?? err.response?.data?.message ?? t('common.error')
}

async function load(): Promise<void> {
  loading.value = true

  try {
    const { data } = await api.get('/users', { params: { with_disabled: showDisabled.value || undefined } })
    users.value = data.data
  } catch (e) {
    ui.notify(message(e), 'error')
  } finally {
    loading.value = false
  }
}

watch(showDisabled, load)

function openCreate(): void {
  editing.value = null
  Object.assign(form, {
    name: '',
    username: '',
    email: '',
    phone: '',
    role: 'cashier',
    is_active: true,
    password: '',
    password_confirmation: '',
  })
  formError.value = ''
  modal.value = true
}

function openEdit(user: ManagedUser): void {
  editing.value = user
  Object.assign(form, {
    name: user.name,
    username: user.username ?? '',
    email: user.email ?? '',
    phone: user.phone ?? '',
    role: user.role,
    is_active: user.is_active,
    password: '',
    password_confirmation: '',
  })
  formError.value = ''
  modal.value = true
}

async function submit(): Promise<void> {
  saving.value = true
  formError.value = ''

  try {
    if (editing.value) {
      const { data } = await api.put(`/users/${editing.value.id}`, {
        name: form.name,
        username: form.username,
        email: form.email || null,
        phone: form.phone || null,
        role: form.role,
        is_active: form.is_active,
      })
      ui.notify(t('users.updated'))

      if (data.data.id === auth.user?.id) {
        await auth.fetchMe()
      }
    } else {
      await api.post('/users', {
        name: form.name,
        username: form.username,
        email: form.email || null,
        phone: form.phone || null,
        role: form.role,
        is_active: form.is_active,
        password: form.password,
        password_confirmation: form.password_confirmation,
      })
      ui.notify(t('users.created'))
    }

    modal.value = false
    await load()
  } catch (e) {
    formError.value = message(e)
  } finally {
    saving.value = false
  }
}

function openPassword(user: ManagedUser): void {
  passwordTarget.value = user
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
  passwordError.value = ''
  passwordModal.value = true
}

async function submitPassword(): Promise<void> {
  if (!passwordTarget.value) return

  savingPassword.value = true
  passwordError.value = ''

  try {
    await api.patch(`/users/${passwordTarget.value.id}/password`, {
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    ui.notify(t('users.reset_done'))
    passwordModal.value = false
  } catch (e) {
    passwordError.value = message(e)
  } finally {
    savingPassword.value = false
  }
}

async function toggleActive(user: ManagedUser): Promise<void> {
  try {
    if (user.is_active) {
      if (!window.confirm(t('common.confirm_disable', { name: user.name }))) return

      await api.delete(`/users/${user.id}`)
      ui.notify(t('common.disabled_success'))
    } else {
      await api.patch(`/users/${user.id}/restore`)
      ui.notify(t('common.restored'))
    }

    await load()
  } catch (e) {
    ui.notify(message(e), 'error')
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-xl font-bold tracking-tight sm:text-2xl">{{ t('users.title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('users.subtitle') }}</p>
      </div>

      <AppButton @click="openCreate">
        <Plus class="size-4" />
        {{ t('users.new') }}
      </AppButton>
    </header>

    <div class="card flex flex-wrap items-center gap-3 p-3">
      <div class="relative min-w-[220px] flex-1">
        <Search class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input v-model="search" class="field ps-10" :placeholder="t('common.search')" />
      </div>

      <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-surface2 px-3 py-2.5 text-xs text-muted">
        <input v-model="showDisabled" type="checkbox" class="size-3.5 accent-[var(--gold)]" />
        {{ t('common.show_disabled') }}
      </label>
    </div>

    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table-base">
          <thead>
            <tr>
              <th>{{ t('users.name') }}</th>
              <th>{{ t('users.username') }}</th>
              <th>{{ t('users.role') }}</th>
              <th>{{ t('users.phone') }}</th>
              <th>{{ t('common.status') }}</th>
              <th class="text-end">{{ t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="6" class="py-10 text-center text-sm text-muted">{{ t('common.loading') }}</td>
            </tr>

            <tr v-for="user in filtered" v-else :key="user.id" :class="user.is_active ? '' : 'opacity-50'">
              <td>
                <div class="flex items-center gap-2.5">
                  <div class="grid size-8 shrink-0 place-items-center rounded-full border border-gold/40 bg-goldsoft text-[11px] font-bold text-gold">
                    {{ user.name.slice(0, 2).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <p class="truncate font-semibold">
                      {{ user.name }}
                      <span v-if="user.is_self" class="text-[10px] font-normal text-muted">· {{ t('users.you') }}</span>
                    </p>
                    <p class="truncate text-[11px] text-muted">{{ user.email || '—' }}</p>
                  </div>
                </div>
              </td>
              <td class="font-mono text-xs text-muted">{{ user.username || '—' }}</td>
              <td>
                <span
                  class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide"
                  :class="user.role === 'admin' ? 'bg-goldsoft text-gold' : 'bg-surface2 text-muted'"
                >
                  <ShieldCheck v-if="user.role === 'admin'" class="size-3" />
                  {{ t(`users.roles.${user.role}`) }}
                </span>
              </td>
              <td class="text-xs text-muted">{{ user.phone || '—' }}</td>
              <td>
                <span
                  class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide"
                  :class="user.is_active ? 'bg-success/15 text-success' : 'bg-danger/15 text-danger'"
                >
                  {{ user.is_active ? t('common.active') : t('common.disabled') }}
                </span>
              </td>
              <td>
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                    :title="t('common.edit')"
                    @click="openEdit(user)"
                  >
                    <Pencil class="size-4" />
                  </button>

                  <button
                    class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2 hover:text-gold"
                    :title="t('users.reset')"
                    @click="openPassword(user)"
                  >
                    <KeyRound class="size-4" />
                  </button>

                  <button
                    v-if="!user.is_self"
                    class="grid size-8 place-items-center rounded-lg text-muted transition hover:bg-surface2"
                    :class="user.is_active ? 'hover:text-danger' : 'hover:text-success'"
                    :title="user.is_active ? t('common.disable') : t('common.enable')"
                    @click="toggleActive(user)"
                  >
                    <Ban v-if="user.is_active" class="size-4" />
                    <RotateCcw v-else class="size-4" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="!loading && filtered.length === 0">
              <td colspan="6" class="py-12 text-center text-sm text-muted">{{ t('users.empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <AppModal
      :open="modal"
      :title="editing ? t('users.edit') : t('users.new')"
      :subtitle="t('users.subtitle')"
      @close="modal = false"
    >
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.name') }}</span>
            <input id="user-name" v-model="form.name" class="field" required maxlength="100" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.username') }}</span>
            <input id="user-username" v-model="form.username" class="field font-mono" required maxlength="50" autocomplete="off" />
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.role') }}</span>
            <select id="user-role" v-model="form.role" class="field">
              <option v-for="role in roles" :key="role" :value="role">{{ t(`users.roles.${role}`) }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.phone') }}</span>
            <input v-model="form.phone" class="field" maxlength="30" />
          </label>

          <label class="block sm:col-span-2">
            <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.email') }}</span>
            <input v-model="form.email" type="email" class="field" maxlength="150" />
          </label>

          <template v-if="!editing">
            <label class="block">
              <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.password') }}</span>
              <input v-model="form.password" type="password" class="field" required minlength="6" autocomplete="new-password" />
            </label>

            <label class="block">
              <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.password_confirm') }}</span>
              <input v-model="form.password_confirmation" type="password" class="field" required minlength="6" autocomplete="new-password" />
            </label>
          </template>
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

    <AppModal
      :open="passwordModal"
      :title="t('users.reset')"
      :subtitle="passwordTarget?.name"
      @close="passwordModal = false"
    >
      <form class="space-y-4" @submit.prevent="submitPassword">
        <p class="text-xs text-muted">{{ t('users.reset_hint', { name: passwordTarget?.name }) }}</p>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.password') }}</span>
          <input v-model="passwordForm.password" type="password" class="field" required minlength="6" autocomplete="new-password" />
        </label>

        <label class="block">
          <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('users.password_confirm') }}</span>
          <input v-model="passwordForm.password_confirmation" type="password" class="field" required minlength="6" autocomplete="new-password" />
        </label>

        <p v-if="passwordError" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
          {{ passwordError }}
        </p>

        <div class="flex justify-end gap-2 pt-1">
          <AppButton variant="ghost" type="button" @click="passwordModal = false">{{ t('common.cancel') }}</AppButton>
          <AppButton type="submit" :loading="savingPassword">{{ t('common.save') }}</AppButton>
        </div>
      </form>
    </AppModal>
  </div>
</template>
