<template>
  <RouterView />
</template>

<script setup lang="ts">
import { watch } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useOfflineStore } from '@/stores/offline'
import { onCircuitOpen } from '@/adapters/http'

const auth    = useAuthStore()
const offline = useOfflineStore()
const router  = useRouter()

// When session expires mid-session, redirect to login
watch(
  () => auth.isSessionExpired,
  (expired) => {
    if (expired) {
      auth.clearSession()
      router.push({ name: 'login' })
    }
  },
)

// Register circuit-breaker callback from http adapter
onCircuitOpen(() => {
  offline.setReadOnly(true)
})

// Rehydrate pending offline queue on mount
offline.loadQueue()

// When browser reports online again, flip read-only off and replay queued writes
window.addEventListener('online', async () => {
  if (offline.isReadOnly) offline.setReadOnly(false)
  await offline.replayQueue()
})

window.addEventListener('offline', () => {
  offline.setReadOnly(true)
})
</script>
