<template>
  <main class="login-page">
    <div class="login-card">
      <h1>CampusLearn</h1>
      <p class="subtitle">Sign in to continue</p>

      <div v-if="errorMessage" class="alert alert--error" role="alert">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleSubmit" novalidate>
        <div class="field">
          <label for="email">Email address</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="username"
            required
            :disabled="submitting"
          />
        </div>

        <div class="field">
          <label for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            :disabled="submitting"
          />
        </div>

        <button type="submit" :disabled="submitting">
          {{ submitting ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>
    </div>
  </main>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import http from '@/adapters/http'
import type { ApiError } from '@/types'
import type { AxiosError } from 'axios'

const router   = useRouter()
const authStore = useAuthStore()

const email        = ref('')
const password     = ref('')
const submitting   = ref(false)
const errorMessage = ref('')

async function handleSubmit() {
  errorMessage.value = ''
  submitting.value   = true

  try {
    const response = await http.post<{
      data: { token: string; expires_at: string; user: import('@/types').User }
    }>('/auth/login', {
      email:    email.value,
      password: password.value,
    })

    const { token, expires_at, user } = response.data.data
    authStore.setSession(token, expires_at)
    authStore.setUser(user)

    router.push({ name: 'home' })
  } catch (err) {
    const axiosErr = err as AxiosError<{ error: ApiError }>
    const code     = axiosErr.response?.data?.error?.code

    if (code === 'ACCOUNT_LOCKED') {
      errorMessage.value =
        'Your account has been temporarily locked due to too many failed attempts. Please try again later.'
    } else if (code === 'INVALID_CREDENTIALS') {
      errorMessage.value = 'Email or password is incorrect.'
    } else if (code === 'VALIDATION_FAILED') {
      errorMessage.value = 'Please enter a valid email and password.'
    } else {
      errorMessage.value = 'An unexpected error occurred. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>
