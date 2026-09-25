<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { api } from '../api/client'
import { useSitesStore } from '../stores/sites'
import SiteStatusBadge from '../components/SiteStatusBadge.vue'
import ManualScanPanel from '../components/ManualScanPanel.vue'
import CollapsibleSection from '../components/CollapsibleSection.vue'

const route = useRoute()
const router = useRouter()
const sitesStore = useSitesStore()
const site = ref(null)
const error = ref('')
const loading = ref(true)

const deleting = ref(false)
const deleteError = ref('')

const editingName = ref(false)
const nameDraft = ref('')
const renaming = ref(false)
const renameError = ref('')
const nameInput = ref(null)

const confirming = ref(false)
const confirmError = ref('')

const savingSettings = ref(false)
const settingsError = ref('')

const hosterUrlDraft = ref('')
const savingHosterUrl = ref(false)
const hosterUrlError = ref('')

const notesDraft = ref('')
const savingNotes = ref(false)
const notesError = ref('')
const notesSaved = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    site.value = await api.getSite(route.params.id)
    notesDraft.value = site.value.notes ?? ''
    hosterUrlDraft.value = site.value.hoster_url ?? ''
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function startEditingName() {
  nameDraft.value = site.value.name
  renameError.value = ''
  editingName.value = true
  await nextTick()
  nameInput.value?.focus()
}

function cancelEditingName() {
  editingName.value = false
  renameError.value = ''
}

async function saveName() {
  const name = nameDraft.value.trim()
  if (name === '') {
    renameError.value = 'Name cannot be empty'
    return
  }

  renaming.value = true
  renameError.value = ''
  try {
    const updated = await api.renameSite(site.value.id, name)
    site.value.name = updated.name
    editingName.value = false
  } catch (e) {
    renameError.value = e.message
  } finally {
    renaming.value = false
  }
}

async function confirmChanges() {
  confirmError.value = ''
  confirming.value = true
  try {
    await api.confirmSiteChanges(site.value.id)
    await load()
  } catch (e) {
    confirmError.value = e.message
  } finally {
    confirming.value = false
  }
}

async function saveHosterUrl() {
  hosterUrlError.value = ''
  savingHosterUrl.value = true
  try {
    const updated = await api.updateSiteHosterUrl(site.value.id, hosterUrlDraft.value.trim())
    site.value.hoster_url = updated.hoster_url
    hosterUrlDraft.value = updated.hoster_url
  } catch (e) {
    hosterUrlError.value = e.message
  } finally {
    savingHosterUrl.value = false
  }
}

async function saveNotes() {
  notesError.value = ''
  notesSaved.value = false
  savingNotes.value = true
  try {
    const updated = await api.updateSiteNotes(site.value.id, notesDraft.value)
    site.value.notes = updated.notes
    notesDraft.value = updated.notes
    notesSaved.value = true
  } catch (e) {
    notesError.value = e.message
  } finally {
    savingNotes.value = false
  }
}

async function toggleSetting(key) {
  settingsError.value = ''
  savingSettings.value = true
  try {
    const updated = await api.updateSiteSettings(site.value.id, { [key]: !site.value[key] })
    site.value[key] = updated[key]
  } catch (e) {
    settingsError.value = e.message
  } finally {
    savingSettings.value = false
  }
}

async function deleteSite() {
  if (!window.confirm(`Delete ${site.value.name}? This removes its history and cannot be undone.`)) {
    return
  }

  deleteError.value = ''
  deleting.value = true
  try {
    await sitesStore.removeSite(site.value.id)
    router.push({ name: 'sites' })
  } catch (e) {
    deleteError.value = e.message
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="{ name: 'sites' }" class="text-sm text-slate-500 hover:underline dark:text-slate-400">
      &larr; All sites
    </RouterLink>

    <p v-if="loading && !site" class="text-slate-500 dark:text-slate-400">Loading…</p>
    <p v-else-if="error" class="text-sm text-red-600 dark:text-red-400">{{ error }}</p>

    <template v-else-if="site">
      <div>
        <div v-if="editingName" class="flex items-center gap-2">
          <input
            ref="nameInput"
            v-model="nameDraft"
            type="text"
            class="font-heading rounded-md border border-slate-300 px-2 py-1 text-2xl dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            @keyup.enter="saveName"
            @keyup.escape="cancelEditingName"
          >
          <button
            type="button"
            :disabled="renaming"
            class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
            @click="saveName"
          >
            {{ renaming ? 'Saving…' : 'Save' }}
          </button>
          <button
            type="button"
            class="text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
            @click="cancelEditingName"
          >
            Cancel
          </button>
        </div>
        <div v-else class="flex items-center gap-3">
          <h2 class="font-heading text-2xl">{{ site.name }}</h2>
          <SiteStatusBadge :status="site.status" />
          <button
            type="button"
            title="Rename site"
            aria-label="Rename site"
            class="rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            @click="startEditingName"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
            </svg>
          </button>
          <button
            v-if="site.status === 'tampered'"
            type="button"
            :disabled="confirming"
            class="ml-auto rounded-md bg-green-700 px-3 py-1.5 text-sm text-white hover:bg-green-600 disabled:opacity-50 dark:bg-green-600 dark:hover:bg-green-500"
            @click="confirmChanges"
          >
            {{ confirming ? 'Confirming…' : 'Confirm changes' }}
          </button>
          <button
            type="button"
            :disabled="deleting"
            :class="site.status === 'tampered' ? '' : 'ml-auto'"
            class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-400 dark:hover:bg-red-950"
            @click="deleteSite"
          >
            {{ deleting ? 'Deleting…' : 'Delete site' }}
          </button>
        </div>
        <p v-if="renameError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ renameError }}</p>
        <p v-if="confirmError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ confirmError }}</p>
        <p v-if="deleteError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ deleteError }}</p>
      </div>

      <a
        :href="site.url"
        target="_blank"
        rel="noreferrer"
        class="text-sm text-slate-500 hover:underline dark:text-slate-400"
      >
        {{ site.url }}
      </a>

      <div>
        <h3 class="font-heading mb-2 text-lg">Hoster</h3>
        <div class="flex flex-wrap items-center gap-2">
          <input
            v-model="hosterUrlDraft"
            type="url"
            inputmode="url"
            placeholder="https://control-panel.example.com"
            class="min-w-0 flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            @keyup.enter="saveHosterUrl"
          >
          <button
            type="button"
            :disabled="savingHosterUrl || hosterUrlDraft.trim() === (site.hoster_url ?? '')"
            class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
            @click="saveHosterUrl"
          >
            {{ savingHosterUrl ? 'Saving…' : 'Save' }}
          </button>
          <a
            v-if="site.hoster_url"
            :href="site.hoster_url"
            target="_blank"
            rel="noreferrer"
            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-800"
          >
            Open
          </a>
        </div>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
          Control panel URL only — sign in with the credentials from the password manager.
        </p>
        <p v-if="hosterUrlError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ hosterUrlError }}</p>
      </div>

      <div class="flex flex-wrap gap-6 text-sm">
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            :checked="!!site.sanity_check_enabled"
            :disabled="savingSettings"
            class="h-4 w-4 rounded border-slate-300 dark:border-slate-600"
            @change="toggleSetting('sanity_check_enabled')"
          >
          Sanity check during scheduled scan
        </label>
        <label class="flex items-center gap-2">
          <input
            type="checkbox"
            :checked="!!site.wp_cron_enabled"
            :disabled="savingSettings"
            class="h-4 w-4 rounded border-slate-300 dark:border-slate-600"
            @change="toggleSetting('wp_cron_enabled')"
          >
          Fire wp-cron during scheduled scan
        </label>
      </div>
      <p v-if="settingsError" class="text-sm text-red-600 dark:text-red-400">{{ settingsError }}</p>

      <div>
        <h3 class="font-heading mb-2 text-lg">Notes</h3>
        <textarea
          v-model="notesDraft"
          rows="4"
          placeholder="Anything worth remembering about this site…"
          class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
          @input="notesSaved = false"
        ></textarea>
        <div class="mt-2 flex items-center gap-3">
          <button
            type="button"
            :disabled="savingNotes || notesDraft === (site.notes ?? '')"
            class="rounded-md bg-slate-900 px-3 py-1.5 text-sm text-white hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
            @click="saveNotes"
          >
            {{ savingNotes ? 'Saving…' : 'Save notes' }}
          </button>
          <span v-if="notesSaved" class="text-sm text-slate-500 dark:text-slate-400">Saved</span>
          <span v-if="notesError" class="text-sm text-red-600 dark:text-red-400">{{ notesError }}</span>
        </div>
      </div>

      <ManualScanPanel :site-id="site.id" @scanned="load" />

      <CollapsibleSection title="Alert history" :count="site.alerts.length" :open="site.alerts.length > 0">
        <p v-if="site.alerts.length === 0" class="text-sm text-slate-400 dark:text-slate-500">
          No alerts recorded.
        </p>
        <ul v-else class="space-y-2">
          <li
            v-for="alert in site.alerts"
            :key="alert.id"
            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950"
          >
            <div class="flex justify-between text-red-800 dark:text-red-300">
              <span>{{ alert.detected_at }}</span>
              <span>similarity {{ (alert.similarity * 100).toFixed(1) }}%</span>
            </div>
            <pre class="mt-2 whitespace-pre-wrap text-red-700 dark:text-red-400">{{ alert.diff_summary }}</pre>
          </li>
        </ul>
      </CollapsibleSection>

      <CollapsibleSection title="Outage history" :count="site.outages.length" :open="site.outages.length > 0">
        <p v-if="site.outages.length === 0" class="text-sm text-slate-400 dark:text-slate-500">
          No outages recorded.
        </p>
        <ul v-else class="space-y-2">
          <li
            v-for="outage in site.outages"
            :key="outage.id"
            class="rounded-md border border-violet-200 bg-violet-50 p-3 text-sm dark:border-violet-900 dark:bg-violet-950"
          >
            <div class="flex justify-between text-violet-800 dark:text-violet-300">
              <span>{{ outage.detected_at }}</span>
              <span>{{ outage.resolved_at ? `resolved ${outage.resolved_at}` : 'ongoing' }}</span>
            </div>
            <p class="mt-2 text-violet-700 dark:text-violet-400">{{ outage.error_message }}</p>
          </li>
        </ul>
      </CollapsibleSection>

      <CollapsibleSection title="Snapshot history" :count="site.snapshots.length">
        <table class="w-full divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white text-sm dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
          <thead class="bg-slate-50 dark:bg-slate-900">
            <tr>
              <th class="px-4 py-2 text-left font-medium text-slate-500 dark:text-slate-400">When</th>
              <th class="px-4 py-2 text-left font-medium text-slate-500 dark:text-slate-400">Trigger</th>
              <th class="px-4 py-2 text-left font-medium text-slate-500 dark:text-slate-400">Similarity</th>
              <th class="px-4 py-2 text-left font-medium text-slate-500 dark:text-slate-400">Signature</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr v-for="snap in site.snapshots" :key="snap.id">
              <td class="px-4 py-2">{{ snap.created_at }}</td>
              <td class="px-4 py-2">{{ snap.triggered_by }}</td>
              <td class="px-4 py-2">
                {{ snap.similarity != null ? (snap.similarity * 100).toFixed(1) + '%' : '—' }}
              </td>
              <td class="px-4 py-2">{{ snap.is_signature ? 'yes' : '' }}</td>
            </tr>
          </tbody>
        </table>
      </CollapsibleSection>
    </template>
  </div>
</template>
