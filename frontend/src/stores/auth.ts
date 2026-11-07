import { defineStore } from 'pinia'
import api from '../lib/api'
import type { User } from '../types'

interface AuthState {
  token: string | null
  user: User | null
  loading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    token: localStorage.getItem('token'),
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => Boolean(state.token),
    initials: (state) =>
      (state.user?.name ?? '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
  },

  actions: {
    async login(login: string, password: string): Promise<void> {
      this.loading = true
      try {
        const { data } = await api.post('/auth/login', { login, password, device_name: 'web' })
        this.token = data.token
        this.user = data.user
        localStorage.setItem('token', data.token)
      } finally {
        this.loading = false
      }
    },

    async fetchMe(): Promise<void> {
      const { data } = await api.get('/auth/me')
      this.user = data.data ?? data
    },

    async logout(): Promise<void> {
      try {
        await api.post('/auth/logout')
      } catch {
        // ignore network errors on logout
      }
      this.token = null
      this.user = null
      localStorage.removeItem('token')
    },
  },
})
