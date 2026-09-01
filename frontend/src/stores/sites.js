import { defineStore } from 'pinia'
import { api } from '../api/client'

export const useSitesStore = defineStore('sites', {
  state: () => ({
    sites: [],
    loading: false,
    error: '',
  }),
  actions: {
    async fetchAll() {
      this.loading = true
      this.error = ''
      try {
        this.sites = await api.listSites()
      } catch (e) {
        this.error = e.message
      } finally {
        this.loading = false
      }
    },
    async addSite(payload) {
      const site = await api.createSite(payload)
      this.sites.push(site)
      return site
    },
    async removeSite(id) {
      await api.deleteSite(id)
      this.sites = this.sites.filter((site) => site.id !== id)
    },
  },
})
