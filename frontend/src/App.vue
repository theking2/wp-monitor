<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

function logout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
        <h1 class="font-heading text-xl tracking-tight">wp-monitor</h1>
        <button
          v-if="auth.isAuthenticated"
          type="button"
          class="text-sm text-slate-500 hover:text-slate-900"
          @click="logout"
        >
          Log out
        </button>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-6 py-8">
      <router-view />
    </main>
  </div>
</template>
