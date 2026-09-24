<script setup>
defineProps({
  title: { type: String, required: true },
  count: { type: Number, required: true },
  open: { type: Boolean, default: false },
})
</script>

<template>
  <details :open="open" class="group">
    <summary
      class="flex cursor-pointer list-none items-center gap-2 [&::-webkit-details-marker]:hidden"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="h-4 w-4 text-slate-400 transition-transform group-open:rotate-90 dark:text-slate-500"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      >
        <path d="M9 18l6-6-6-6" />
      </svg>
      <h3 class="font-heading text-lg">{{ title }}</h3>
      <span
        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400"
      >
        {{ count }}
      </span>
    </summary>
    <div class="mt-2">
      <slot />
    </div>
  </details>
</template>
