import { defineStore } from 'pinia'
import api, { STORAGE_TOKEN_KEY } from '@/lib/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(STORAGE_TOKEN_KEY) || null,
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    roles: (state) => state.user?.roles ?? [],
    isManagement: (state) => (state.user?.roles ?? []).includes('management'),
    isTrainer: (state) => (state.user?.roles ?? []).includes('trainer'),
    permissions: (state) => state.user?.permissions ?? [],
    // Cek permission tunggal (backend tetap otoritas final).
    can: (state) => (perm) => (state.user?.permissions ?? []).includes(perm),
    firstName: (state) => (state.user?.name ? state.user.name.split(' ')[0] : ''),
    // Dashboard tujuan sesuai peran. Management diprioritaskan bila punya dua peran.
    homeRoute() {
      return this.isManagement ? '/management' : '/trainer'
    },
  },

  actions: {
    setToken(token) {
      this.token = token
      if (token) {
        localStorage.setItem(STORAGE_TOKEN_KEY, token)
      } else {
        localStorage.removeItem(STORAGE_TOKEN_KEY)
      }
    },

    /**
     * Login via POST /login (Sanctum). Melempar error validasi (422) ke pemanggil.
     */
    async login(email, password) {
      this.loading = true
      try {
        const { data } = await api.post('/login', {
          email,
          password,
          device_name: 'web-spa',
        })
        this.setToken(data.token)
        this.user = data.user
        return data.user
      } finally {
        this.loading = false
      }
    },

    /**
     * Ambil profil user aktif (GET /me) — dipakai saat refresh halaman.
     */
    async fetchMe() {
      const { data } = await api.get('/me')
      // /me mengembalikan UserResource langsung -> terbungkus { data: {...} }.
      this.user = data.data ?? data
      return this.user
    },

    async logout() {
      try {
        await api.post('/logout')
      } catch (e) {
        // Abaikan; token mungkin sudah tidak valid.
      } finally {
        this.setToken(null)
        this.user = null
      }
    },
  },
})
