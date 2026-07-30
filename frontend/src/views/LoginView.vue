<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { api } from '../api/client'

const username = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const auth = useAuthStore()
const router = useRouter()

async function submit() {
  error.value = ''
  loading.value = true
  try {
    const { token } = await api.login(username.value, password.value)
    auth.setToken(token)
    router.push({ name: 'sites' })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="mx-auto max-w-sm">
    <h2 class="font-heading mb-6 text-2xl">Sign in</h2>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="block text-sm font-medium text-slate-700" for="username">Username</label>
        <input
          id="username"
          v-model="username"
          type="text"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none"
        >
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          required
          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none"
        >
      </div>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <button
        type="submit"
        :disabled="loading"
        class="w-full rounded-md bg-slate-900 px-4 py-2 text-white hover:bg-slate-700 disabled:opacity-50"
      >
        {{ loading ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>
  </div>
</template>
