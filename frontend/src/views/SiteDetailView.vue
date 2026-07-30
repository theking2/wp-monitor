<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { api } from '../api/client'
import SiteStatusBadge from '../components/SiteStatusBadge.vue'
import ManualScanPanel from '../components/ManualScanPanel.vue'

const route = useRoute()
const site = ref(null)
const error = ref('')
const loading = ref(true)

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

onMounted(load)
</script>

<template>
  <div class="space-y-6">
    <RouterLink :to="{ name: 'sites' }" class="text-sm text-slate-500 hover:underline">
      &larr; All sites
    </RouterLink>

    <p v-if="loading && !site" class="text-slate-500">Loading…</p>
    <p v-else-if="error" class="text-sm text-red-600">{{ error }}</p>

    <template v-else-if="site">
      <div class="flex items-center gap-3">
        <h2 class="font-heading text-2xl">{{ site.name }}</h2>
        <SiteStatusBadge :status="site.status" />
      </div>
      <a :href="site.url" target="_blank" rel="noreferrer" class="text-sm text-slate-500 hover:underline">
        {{ site.url }}
      </a>

      <ManualScanPanel :site-id="site.id" @scanned="load" />

      <div>
        <h3 class="font-heading mb-2 text-lg">Alert history</h3>
        <p v-if="site.alerts.length === 0" class="text-sm text-slate-400">No alerts recorded.</p>
        <ul v-else class="space-y-2">
          <li
            v-for="alert in site.alerts"
            :key="alert.id"
            class="rounded-md border border-red-200 bg-red-50 p-3 text-sm"
          >
            <div class="flex justify-between text-red-800">
              <span>{{ alert.detected_at }}</span>
              <span>similarity {{ (alert.similarity * 100).toFixed(1) }}%</span>
            </div>
            <pre class="mt-2 whitespace-pre-wrap text-red-700">{{ alert.diff_summary }}</pre>
          </li>
        </ul>
      </div>

      <div>
        <h3 class="font-heading mb-2 text-lg">Snapshot history</h3>
        <table class="w-full divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white text-sm">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-4 py-2 text-left font-medium text-slate-500">When</th>
              <th class="px-4 py-2 text-left font-medium text-slate-500">Trigger</th>
              <th class="px-4 py-2 text-left font-medium text-slate-500">Similarity</th>
              <th class="px-4 py-2 text-left font-medium text-slate-500">Signature</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
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
