<script setup>
defineProps({
  status: { type: String, required: true },
})

const styles = {
  ok: 'bg-green-100 text-green-800',
  tampered: 'bg-red-100 text-red-800',
  unknown: 'bg-slate-100 text-slate-600',
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
