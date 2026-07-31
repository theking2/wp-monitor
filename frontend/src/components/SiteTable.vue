<script setup>
import { RouterLink } from 'vue-router'
import SiteStatusBadge from './SiteStatusBadge.vue'
import { formatRelativeUpperBound } from '../utils/relativeTime'

defineProps({
  sites: { type: Array, required: true },
})

// Above this similarity, an 'ok' site is shown as unremarkable (white); below it (but still
// above the tamper threshold, otherwise the site wouldn't be 'ok' at all), it's shown as having
// small tolerated differences (orange) rather than being visually identical to a perfect match.
const MINOR_DIFFERENCE_THRESHOLD = 0.999

function rowClass(site) {
  if (site.status === 'unknown') {
    return 'bg-blue-50'
  }
  if (site.status === 'tampered') {
    return 'bg-red-50'
  }
  if (site.status === 'ok' && site.latest_similarity !== null && site.latest_similarity < MINOR_DIFFERENCE_THRESHOLD) {
    return 'bg-orange-50'
  }
  return ''
}
</script>

<template>
  <table class="w-full divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-4 py-2 text-left text-sm font-medium text-slate-500">Name</th>
        <th class="px-4 py-2 text-left text-sm font-medium text-slate-500">URL</th>
        <th class="px-4 py-2 text-left text-sm font-medium text-slate-500">Status</th>
        <th class="px-4 py-2 text-left text-sm font-medium text-slate-500">Last checked</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
      <tr v-for="site in sites" :key="site.id" class="hover:brightness-95" :class="rowClass(site)">
        <td class="px-4 py-3">
          <RouterLink
            :to="{ name: 'site-detail', params: { id: site.id } }"
            class="font-medium text-slate-900 hover:underline"
          >
            {{ site.name }}
          </RouterLink>
        </td>
        <td class="px-4 py-3 text-slate-500">{{ site.url }}</td>
        <td class="px-4 py-3"><SiteStatusBadge :status="site.status" /></td>
        <td class="px-4 py-3 text-slate-500">{{ formatRelativeUpperBound(site.last_checked_at) }}</td>
      </tr>
      <tr v-if="sites.length === 0">
        <td colspan="4" class="px-4 py-6 text-center text-slate-400">No sites yet — add one above.</td>
      </tr>
    </tbody>
  </table>
</template>
