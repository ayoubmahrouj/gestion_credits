/* Gestion Crédits — service worker (PWA) */
const CACHE = 'gestion-credits-v1'

// Base de déploiement ('/' en production, '/gestion-credits/' sous WAMP…)
const BASE = new URL(self.registration.scope).pathname

const SHELL = [
  BASE,
  `${BASE}index.html`,
  `${BASE}manifest.webmanifest`,
  `${BASE}favicon.svg`,
  `${BASE}icon-192.png`,
  `${BASE}icon-512.png`,
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE)
      .then((cache) => cache.addAll(SHELL))
      .then(() => self.skipWaiting()),
  )
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  )
})

self.addEventListener('fetch', (event) => {
  const request = event.request

  if (request.method !== 'GET') return

  const url = new URL(request.url)

  if (url.origin !== self.location.origin) return

  // Les données (API) ne sont jamais mises en cache.
  if (url.pathname.includes('/api/')) return

  // Navigation : réseau d'abord, coquille hors ligne en secours.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone()
          caches.open(CACHE).then((cache) => cache.put(`${BASE}index.html`, copy))
          return response
        })
        .catch(() => caches.match(`${BASE}index.html`).then((hit) => hit || caches.match(BASE))),
    )
    return
  }

  // Assets statiques : cache d'abord (fichiers versionnés par Vite).
  if (url.pathname.startsWith(`${BASE}assets/`) || /\.(css|js|png|svg|webp|woff2?)$/.test(url.pathname)) {
    event.respondWith(
      caches.match(request).then(
        (hit) =>
          hit ||
          fetch(request).then((response) => {
            if (response.ok) {
              const copy = response.clone()
              caches.open(CACHE).then((cache) => cache.put(request, copy))
            }
            return response
          }),
      ),
    )
  }
})
