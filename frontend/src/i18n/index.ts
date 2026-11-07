import { createI18n } from 'vue-i18n'
import ar from './ar.json'
import fr from './fr.json'

export type Locale = 'fr' | 'ar'

const saved = (localStorage.getItem('locale') as Locale | null) ?? 'fr'

const i18n = createI18n({
  legacy: false,
  locale: saved,
  fallbackLocale: 'fr',
  messages: { fr, ar },
})

export function applyDirection(locale: string): void {
  const dir = locale === 'ar' ? 'rtl' : 'ltr'
  document.documentElement.setAttribute('dir', dir)
  document.documentElement.setAttribute('lang', locale)
}

export function setLocale(locale: Locale): void {
  i18n.global.locale.value = locale
  localStorage.setItem('locale', locale)
  applyDirection(locale)
}

export default i18n
