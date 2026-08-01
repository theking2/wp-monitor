<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { api } from '../api/client'
import SiteStatusBadge from '../components/SiteStatusBadge.vue'
import ManualScanPanel from '../components/ManualScanPanel.vue'

const route = useRoute()
const site = ref(null)
const error = ref('')
const loading = ref(true)

const editingName = ref(false)
const nameDraft = ref('')
const renaming = ref(false)
const renameError = ref('')
const nameInput = ref(null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    site.value = await api.getSite(route.params.id)
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
        </div>
        <p v-if="renameError" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ renameError }}</p>
      </div>

      <a
        :href="site.url"
        target="_blank"
        rel="noreferrer"
        class="text-sm text-slate-500 hover:underline dark:text-slate-400"
      >
        {{ site.url }}
      </a>

      <ManualScanPanel :site-id="site.id" @scanned="load" />

      <div>
        <h3 class="font-heading mb-2 text-lg">Alert history</h3>
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
      </div>

      <div>
        <h3 class="font-heading mb-2 text-lg">Outage history</h3>
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
      </div>

      <div>
        <h3 class="font-heading mb-2 text-lg">Snapshot history</h3>
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
      </div>
    </template>
  </div>
</template>
