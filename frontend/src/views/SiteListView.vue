<script setup>
import { onMounted, ref } from 'vue'
import { useSitesStore } from '../stores/sites'
import SiteTable from '../components/SiteTable.vue'

const store = useSitesStore()

const name = ref('')
const url = ref('')
const submitting = ref(false)
const formError = ref('')

onMounted(() => store.fetchAll())

async function addSite() {
  formError.value = ''
  submitting.value = true
  try {
    await store.addSite({ name: name.value, url: url.value })
    name.value = ''
    url.value = ''
  } catch (e) {
    formError.value = e.message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <div>
      <h2 class="font-heading text-2xl">Monitored sites</h2>
      <p class="text-slate-500">Sites discovered or added manually, with their latest tamper-check status.</p>
    </div>

    <form
      class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
      @submit.prevent="addSite"
    >
      <div class="min-w-40 flex-1">
        <label class="block text-sm font-medium text-slate-700" for="site-name">Name</label>
        <input
          id="site-name"
          v-model="name"
          type="text"
          placeholder="Optional"
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        >
      </div>
      <div class="min-w-56 flex-[2]">
        <label class="block text-sm font-medium text-slate-700" for="site-url">URL</label>
        <input
          id="site-url"
          v-model="url"
          type="url"
          required
          placeholder="https://example.com"
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2"
        >
      </div>
      <button
        type="submit"
        :disabled="submitting"
        class="rounded-md bg-slate-900 px-4 py-2 text-white hover:bg-slate-700 disabled:opacity-50"
      >
        {{ submitting ? 'Adding…' : 'Add site' }}
      </button>
    </form>
    <p v-if="formError" class="text-sm text-red-600">{{ formError }}</p>

    <p v-if="store.error" class="text-sm text-red-600">{{ store.error }}</p>
    <p v-else-if="store.loading" class="text-slate-500">Loading…</p>
    <SiteTable v-else :sites="store.sites" />
  </div>
</template>
