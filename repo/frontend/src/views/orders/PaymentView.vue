<template>
  <div class="payment-view">
    <RouterLink :to="{ name: 'order-detail', params: { id } }" class="back-link">← Back to Order</RouterLink>
    <h1>Complete Payment</h1>
    <p class="payment-view__subtitle">Order #{{ id }} — staffed-office or kiosk payment</p>

    <AlertBanner v-if="store.conflict" type="warning" :message="store.conflict" />
    <AlertBanner v-if="offline.isReadOnly" type="error" message="System is in read-only mode. Payment is unavailable." />

    <template v-if="!paymentAttempt">
      <BaseField id="payment-method" label="Payment Method" required>
        <select id="payment-method" v-model="method" class="field__input" :disabled="store.submitting">
          <option value="cash">Cash</option>
          <option value="check">Check</option>
          <option value="local_terminal">Local Terminal</option>
          <option value="waiver">Waiver</option>
        </select>
      </BaseField>

      <BaseField id="kiosk-id" label="Kiosk / Terminal ID (optional)">
        <input id="kiosk-id" v-model="kioskId" type="text" class="field__input" :disabled="store.submitting" />
      </BaseField>

      <button
        class="btn btn--primary"
        :disabled="store.submitting || offline.isReadOnly"
        @click="initiatePayment()"
      >
        {{ store.submitting ? 'Initiating…' : 'Initiate Payment' }}
      </button>
    </template>

    <!-- Step 2: staff confirms payment completion -->
    <template v-else>
      <div class="payment-confirmation">
        <p>Payment initiated via <strong>{{ paymentAttempt.method }}</strong>.</p>
        <p>Have the operator confirm receipt and then click <strong>Complete Payment</strong>.</p>
        <p class="payment-view__idempotency-note">
          Idempotency key: <code>{{ currentIdempotencyKey }}</code> — safe to retry.
        </p>
      </div>

      <button
        class="btn btn--primary"
        :disabled="store.submitting || offline.isReadOnly"
        @click="completePayment()"
      >
        {{ store.submitting ? 'Completing…' : 'Complete Payment' }}
      </button>
      <button class="btn btn--ghost" :disabled="store.submitting" @click="paymentAttempt = null">
        Back
      </button>
    </template>

    <p v-if="store.error" class="form-error" role="alert">{{ store.error }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useOrdersStore }  from '@/stores/orders'
import { useOfflineStore } from '@/stores/offline'
import type { PaymentAttempt } from '@/types/api'
import AlertBanner from '@/components/ui/AlertBanner.vue'
import BaseField   from '@/components/ui/BaseField.vue'

const route  = useRoute()
const router = useRouter()
const id     = Number(route.params.id)
const store  = useOrdersStore()
const offline = useOfflineStore()

const method               = ref('cash')
const kioskId              = ref('')
const paymentAttempt       = ref<PaymentAttempt | null>(null)
const currentIdempotencyKey = ref('')

async function initiatePayment() {
  const result = await store.initiatePayment(id, method.value, kioskId.value || undefined)
  if (result) {
    paymentAttempt.value        = result.attempt
    currentIdempotencyKey.value = result.idempotencyKey
  }
}

async function completePayment() {
  if (!paymentAttempt.value) return
  const order = await store.completePayment(id, paymentAttempt.value.id, currentIdempotencyKey.value)
  if (order) {
    router.push({ name: 'order-receipt', params: { id } })
  }
}

onMounted(() => store.fetchOrder(id))
</script>
