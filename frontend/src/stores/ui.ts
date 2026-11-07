import { defineStore } from 'pinia'
import i18n, { applyDirection, setLocale, type Locale } from '../i18n'

type Theme = 'dark' | 'light'

interface UiState {
  theme: Theme
  locale: Locale
  sidebarOpen: boolean
  toast: { message: string; type: 'success' | 'error' } | null
}

function initialTheme(): Theme {
  const saved = localStorage.getItem('theme') as Theme | null
  return saved ?? 'dark'
}

function initialLocale(): Locale {
  const saved = localStorage.getItem('locale') as Locale | null
  return saved ?? (i18n.global.locale.value as Locale)
}

export const useUiStore = defineStore('ui', {
  state: (): UiState => ({
    theme: initialTheme(),
    locale: initialLocale(),
    sidebarOpen: false,
    toast: null,
  }),

  actions: {
    init(): void {
      this.applyTheme()
      setLocale(this.locale)
    },

    toggleTheme(): void {
      this.theme = this.theme === 'dark' ? 'light' : 'dark'
      localStorage.setItem('theme', this.theme)
      this.applyTheme()
    },

    applyTheme(): void {
      document.documentElement.setAttribute('data-theme', this.theme)
    },

    setLocale(locale: Locale): void {
      this.locale = locale
      setLocale(locale)
    },

    toggleLocale(): void {
      this.setLocale(this.locale === 'fr' ? 'ar' : 'fr')
    },

    toggleSidebar(): void {
      this.sidebarOpen = !this.sidebarOpen
    },

    closeSidebar(): void {
      this.sidebarOpen = false
    },

    notify(message: string, type: 'success' | 'error' = 'success'): void {
      this.toast = { message, type }
      setTimeout(() => {
        this.toast = null
      }, 3200)
    },
  },
})

export { applyDirection }
