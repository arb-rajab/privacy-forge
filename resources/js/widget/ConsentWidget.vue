<script setup>
import { ref, onMounted } from 'vue'

// Deliberately no client-side persistence of consent state (no
// localStorage/cookies) — 03-architecture.md's component responsibility
// table scopes this widget to "rendering a notice and capturing a
// consent event," explicitly excluding "storing anything client-side
// beyond what's needed to render." "Withdraw" below is only offered for
// the consent record just captured in this same page view (held in a
// plain Vue ref, gone on reload) — an immediate undo, not a persisted
// return-visit lookup, which would need an account or a durable link
// this MVP doesn't have for consent specifically (unlike DSAR's signed
// status link).
const props = defineProps({
  apiBase: { type: String, default: '/api/v1' },
  purposeId: { type: String, required: true },
})

// 'loading' | 'no-notice' | 'ready' | 'submitting' | 'given' | 'withdrawing' | 'withdrawn' | 'error'
const state = ref('loading')
const notice = ref(null)
const subjectIdentifier = ref('')
const consentRecord = ref(null)
const errorMessage = ref('')

async function loadNotice() {
  state.value = 'loading'
  try {
    const response = await fetch(`${props.apiBase}/consent-purposes/${props.purposeId}/notice`)
    if (response.status === 404) {
      state.value = 'no-notice'
      return
    }
    if (!response.ok) {
      throw new Error(`Unexpected response (${response.status})`)
    }
    notice.value = await response.json()
    state.value = 'ready'
  } catch (error) {
    errorMessage.value = error.message
    state.value = 'error'
  }
}

async function submitConsent() {
  if (!subjectIdentifier.value.trim()) return

  state.value = 'submitting'
  try {
    const response = await fetch(`${props.apiBase}/consent`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        purpose_id: props.purposeId,
        notice_version: notice.value.version,
        subject_identifier: subjectIdentifier.value.trim(),
      }),
    })

    if (!response.ok) {
      throw new Error(`Consent capture failed (${response.status})`)
    }

    const record = await response.json()
    consentRecord.value = record
    state.value = 'given'
  } catch (error) {
    errorMessage.value = error.message
    state.value = 'error'
  }
}

async function withdrawConsent() {
  state.value = 'withdrawing'
  try {
    const response = await fetch(`${props.apiBase}/consent/${consentRecord.value.id}/withdraw`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      throw new Error(`Withdrawal failed (${response.status})`)
    }

    const record = await response.json()
    consentRecord.value = record
    state.value = 'withdrawn'
  } catch (error) {
    errorMessage.value = error.message
    state.value = 'error'
  }
}

onMounted(loadNotice)
</script>

<template>
  <div
    class="pf-consent-widget"
    style="font-family: system-ui, sans-serif; max-width: 28rem; border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 1rem;"
  >
    <div v-if="state === 'loading'">
      Loading consent notice…
    </div>

    <div v-else-if="state === 'no-notice'">
      This purpose has no published consent notice yet.
    </div>

    <div v-else-if="state === 'error'">
      <p>Something went wrong: {{ errorMessage }}</p>
      <button
        type="button"
        @click="loadNotice"
      >
        Try again
      </button>
    </div>

    <form
      v-else-if="state === 'ready' || state === 'submitting'"
      @submit.prevent="submitConsent"
    >
      <p class="pf-consent-widget__notice">
        {{ notice.body }}
      </p>
      <label :for="`pf-subject-identifier-${purposeId}`">Email or identifier</label>
      <input
        :id="`pf-subject-identifier-${purposeId}`"
        v-model="subjectIdentifier"
        type="text"
        required
        :disabled="state === 'submitting'"
      >
      <button
        type="submit"
        :disabled="state === 'submitting'"
      >
        {{ state === 'submitting' ? 'Submitting…' : 'I agree' }}
      </button>
    </form>

    <div v-else-if="state === 'given' || state === 'withdrawing'">
      <p>Consent recorded. Thank you.</p>
      <button
        type="button"
        :disabled="state === 'withdrawing'"
        @click="withdrawConsent"
      >
        {{ state === 'withdrawing' ? 'Withdrawing…' : 'Withdraw consent' }}
      </button>
    </div>

    <div v-else-if="state === 'withdrawn'">
      <p>Your consent has been withdrawn.</p>
    </div>
  </div>
</template>
