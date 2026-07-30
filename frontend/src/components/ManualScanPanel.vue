<script setup>
import { ref } from 'vue'
import { api } from '../api/client'

const props = defineProps({
  siteId: { type: [Number, String], required: true },
})

const emit = defineEmits(['scanned'])

const running = ref(false)
const error = ref('')
const result = ref(null)

async function runScan() {
  error.value = ''
  running.value = true
  result.value = null
  try {
    result.value = await api.scanSite(props.siteId)
    emit('scanned', result.value)
  } catch (e) {
    error.value = e.message
  } finally {
    running.value = false
  }
}
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="flex items-center justify-between">
      <h3 class="font-heading text-lg">Manual test</h3>
      <button
        type="button"
        :disabled="running"
        class="rounded-md bg-slate-900 px-4 py-2 text-sm text-white hover:bg-slate-700 disabled:opacity-50"
        @click="runScan"
      >
        {{ running ? 'Testing…' : 'Test now' }}
      </button>
    </div>

    <p v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</p>

    <div v-if="result" class="mt-4 space-y-2 text-sm">
      <p>
        Result:
        <span class="font-medium" :class="result.tampered ? 'text-red-600' : 'text-green-700'">
          {{ result.status }}
        </span>
        <span class="text-slate-500"> (similarity {{ (result.similarity * 100).toFixed(1) }}%)</span>
      </p>
      <pre
        v-if="result.diff_summary"
        class="whitespace-pre-wrap rounded-md bg-slate-50 p-3 text-slate-700"
      >{{ result.diff_summary }}</pre>
    </div>
  </div>
</template>
