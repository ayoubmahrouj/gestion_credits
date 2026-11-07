import axios from 'axios'

const api = axios.create({
  // En dev : proxy Vite vers /api/v1. En production ou sous WAMP, on peut
  // pointer ailleurs via VITE_API_BASE (ex. /gestion-credits-api/api/v1).
  baseURL: import.meta.env.VITE_API_BASE || '/api/v1',
  headers: { Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  config.headers['Accept-Language'] = localStorage.getItem('locale') ?? 'fr'

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const base = import.meta.env.BASE_URL
    const loginPath = `${base}login`

    if (error.response?.status === 401 && !window.location.pathname.startsWith(loginPath)) {
      localStorage.removeItem('token')
      window.location.href = loginPath
    }

    return Promise.reject(error)
  },
)

export default api
