<script setup lang="ts">
import { Globe, Lock, Moon, ShieldCheck, Sparkles, Sun, TrendingUp, User, Wallet } from 'lucide-vue-next'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppButton from '../components/ui/AppButton.vue'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const login = ref('admin')
const password = ref('admin123')
const error = ref('')

async function submit(): Promise<void> {
  error.value = ''

  try {
    await auth.login(login.value, password.value)
    router.push({ name: 'dashboard' })
  } catch (e: unknown) {
    const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
    error.value =
      err.response?.data?.errors?.login?.[0] ?? err.response?.data?.message ?? t('auth.error')
  }
}
</script>

<template>
  <div class="relative z-10 grid min-h-screen lg:grid-cols-2">
    <!-- Panneau marque -->
    <section class="relative hidden overflow-hidden border-e border-line bg-surface lg:flex lg:flex-col lg:justify-between lg:p-12">
      <div class="pointer-events-none absolute -top-40 -end-40 size-[520px] rounded-full bg-gold/10 blur-3xl" />
      <div class="pointer-events-none absolute -bottom-52 -start-32 size-[420px] rounded-full bg-gold/5 blur-3xl" />

      <div class="relative flex items-center gap-3">
        <div class="grid size-12 place-items-center rounded-2xl bg-gradient-to-br from-goldstrong to-gold text-xl font-extrabold text-[#0a0e13] shadow-[0_16px_40px_-14px_rgba(200,164,92,0.9)]">
          C
        </div>
        <div>
          <p class="text-sm font-bold tracking-wide">{{ t('app.name') }}</p>
          <p class="text-[11px] text-muted">{{ t('app.tagline') }}</p>
        </div>
      </div>

      <div class="relative max-w-md">
        <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-gold/30 bg-goldsoft px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-gold">
          <Sparkles class="size-3.5" />
          Premium Credit Suite
        </p>
        <h1 class="text-4xl font-extrabold leading-tight tracking-tight">
          {{ t('auth.hero_line1') }}
          <span class="gold-text">{{ t('auth.hero_gold') }}</span>
        </h1>
        <p class="mt-4 text-sm leading-relaxed text-muted">{{ t('auth.hero_sub') }}</p>

        <ul class="mt-8 space-y-4">
          <li class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-goldsoft text-gold"><Wallet class="size-4" /></span>
            <div>
              <p class="text-sm font-semibold">Crédits & échéanciers</p>
              <p class="text-xs text-muted">Suivi automatique des versements et relances.</p>
            </div>
          </li>
          <li class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-goldsoft text-gold"><TrendingUp class="size-4" /></span>
            <div>
              <p class="text-sm font-semibold">Scoring intelligent</p>
              <p class="text-xs text-muted">Note de paiement par client et alertes de risque.</p>
            </div>
          </li>
          <li class="flex items-start gap-3">
            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-goldsoft text-gold"><ShieldCheck class="size-4" /></span>
            <div>
              <p class="text-sm font-semibold">Sécurisé & traçable</p>
              <p class="text-xs text-muted">Chaque opération est journalisée.</p>
            </div>
          </li>
        </ul>
      </div>

      <p class="relative text-[11px] text-muted/60">© {{ new Date().getFullYear() }} · {{ t('app.name') }}</p>
    </section>

    <!-- Formulaire -->
    <section class="flex flex-col">
      <div class="flex justify-end gap-2 p-5">
        <button
          class="flex items-center gap-1.5 rounded-xl border border-line px-3 py-2 text-xs font-semibold text-muted transition hover:border-gold/40 hover:text-gold"
          @click="ui.toggleLocale()"
        >
          <Globe class="size-4" />
          {{ ui.locale === 'fr' ? 'FR' : 'ع' }}
        </button>
        <button
          class="grid size-9 place-items-center rounded-xl border border-line text-muted transition hover:border-gold/40 hover:text-gold"
          @click="ui.toggleTheme()"
        >
          <Sun v-if="ui.theme === 'dark'" class="size-4" />
          <Moon v-else class="size-4" />
        </button>
      </div>

      <div class="flex flex-1 items-center justify-center px-5 pb-12">
        <form class="w-full max-w-sm fade-up" @submit.prevent="submit">
          <div class="mb-8 flex items-center gap-3 lg:hidden">
            <div class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-goldstrong to-gold text-lg font-extrabold text-[#0a0e13]">C</div>
            <div>
              <p class="text-sm font-bold">{{ t('app.name') }}</p>
              <p class="text-[11px] text-muted">{{ t('app.tagline') }}</p>
            </div>
          </div>

          <h2 class="text-2xl font-bold tracking-tight">{{ t('auth.title') }}</h2>
          <p class="mt-1 text-sm text-muted">{{ t('auth.subtitle') }}</p>

          <div class="mt-8 space-y-4">
            <label class="block">
              <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('auth.identifier') }}</span>
              <div class="relative">
                <User class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                <input
                  id="login"
                  v-model="login"
                  type="text"
                  name="login"
                  required
                  autocomplete="username"
                  class="field ps-10"
                  placeholder="admin"
                />
              </div>
            </label>

            <label class="block">
              <span class="mb-1.5 block text-xs font-semibold text-muted">{{ t('auth.password') }}</span>
              <div class="relative">
                <Lock class="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                <input
                  id="password"
                  v-model="password"
                  type="password"
                  name="password"
                  required
                  autocomplete="current-password"
                  class="field ps-10"
                  placeholder="••••••••"
                />
              </div>
            </label>

            <p v-if="error" class="rounded-xl border border-danger/30 bg-danger/10 px-3 py-2 text-xs font-medium text-danger">
              {{ error }}
            </p>

            <AppButton type="submit" block size="lg" :loading="auth.loading">
              {{ auth.loading ? t('auth.submitting') : t('auth.submit') }}
            </AppButton>
          </div>

          <p class="mt-6 text-center text-[11px] text-muted/70">{{ t('auth.demo') }}</p>
        </form>
      </div>
    </section>
  </div>
</template>
