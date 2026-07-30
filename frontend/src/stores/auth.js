import { defineStore } from 'pinia'

const STORAGE_KEY = 'wp-monitor-token'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(STORAGE_KEY) || null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },
  actions: {
    setToken(token) {
      this.token = token
      localStorage.setItem(STORAGE_KEY, token)
    },
    logout() {
      this.token = null
      localStorage.removeItem(STORAGE_KEY)
    },
  },
})
