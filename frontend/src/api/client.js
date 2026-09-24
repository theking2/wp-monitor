import { useAuthStore } from '../stores/auth'

const BASE_URL = '/api'

async function request(path, { method = 'GET', body } = {}) {
  const auth = useAuthStore()

  const headers = {}
  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (auth.token) {
    headers.Authorization = `Bearer ${auth.token}`
  }

  const response = await fetch(BASE_URL + path, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (response.status === 401) {
    auth.logout()
  }

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    throw new Error(data?.error ?? `Request failed (${response.status})`)
  }

  return data
}

export const api = {
  login: (username, password) => request('/login', { method: 'POST', body: { username, password } }),
  listSites: () => request('/sites'),
  getSite: (id) => request(`/sites/${id}`),
  createSite: (payload) => request('/sites', { method: 'POST', body: payload }),
  deleteSite: (id) => request(`/sites/${id}`, { method: 'DELETE' }),
  scanSite: (id) => request(`/sites/${id}/scan`, { method: 'POST' }),
  renameSite: (id, name) => request(`/sites/${id}/rename`, { method: 'POST', body: { name } }),
  confirmSiteChanges: (id) => request(`/sites/${id}/confirm`, { method: 'POST' }),
  updateSiteNotes: (id, notes) => request(`/sites/${id}/notes`, { method: 'POST', body: { notes } }),
  updateSiteHosterUrl: (id, hosterUrl) => request(`/sites/${id}/hoster-url`, { method: 'POST', body: { hoster_url: hosterUrl } }),
  updateSiteSettings: (id, settings) => request(`/sites/${id}/settings`, { method: 'POST', body: settings }),
}
