import { defineStore } from 'pinia'

const STORAGE_KEY = 'wp-monitor-theme'

export const useThemeStore = defineStore('theme', {
  state: () => ({
    // index.html's inline script already applied the initial class before Vue mounted, to
    // avoid a flash of the wrong theme — read it back here so this store matches the screen.
    isDark: document.documentElement.classList.contains('dark'),
  }),
  actions: {
    toggle() {
      this.isDark = !this.isDark
      document.documentElement.classList.toggle('dark', this.isDark)
      localStorage.setItem(STORAGE_KEY, this.isDark ? 'dark' : 'light')
    },
  },
})
