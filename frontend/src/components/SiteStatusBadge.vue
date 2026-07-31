<script setup>
defineProps({
  status: { type: String, required: true },
})

const styles = {
  ok: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  tampered: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
  unknown: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
}
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="styles[status] ?? styles.unknown"
  >
    {{ status }}
  </span>
</template>
