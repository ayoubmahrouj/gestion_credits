import { createPinia } from 'pinia'
import { createApp } from 'vue'
import App from './App.vue'
import i18n, { applyDirection } from './i18n'
import router from './router'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)
app.use(i18n)
app.mount('#app')

applyDirection(i18n.global.locale.value)

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {
      // PWA optionnelle : aucune erreur bloquante
    })
  })
}
