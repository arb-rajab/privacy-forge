<script setup>
import { ref } from 'vue'

// Public DSAR intake portal (US-005, FR-005) — no account, no login,
// matching 05-api-contracts.md's auth model for data subjects. Calls
// POST /api/v1/dsar directly; don't invent a new endpoint for this.
const requestType = ref('access')
const subjectIdentifier = ref('')
const state = ref('idle') // idle | submitting | submitted | error
const errorMessage = ref('')

async function submit() {
  state.value = 'submitting'
  try {
    const response = await fetch('/api/v1/dsar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({
        request_type: requestType.value,
        subject_identifier: subjectIdentifier.value.trim(),
      }),
    })

    const body = await response.json()

    if (!response.ok) {
      throw new Error(body.detail || `Submission failed (${response.status})`)
    }

    // body.status_url is the signed JSON API link
    // (/api/v1/dsar/status/{signedToken}?...) minted by DsarController —
    // rewritten here to this app's own friendly status page, which
    // forwards the same token and query string back to that exact API
    // URL client-side (see DsarStatus.vue). Nothing about the underlying
    // contract changes; this only changes where a human clicking the
    // link lands.
    const url = new URL(body.status_url)
    const friendlyPath = url.pathname.replace('/api/v1/dsar/status/', '/dsar/status/')
    window.location.href = friendlyPath + url.search
  } catch (error) {
    errorMessage.value = error.message
    state.value = 'error'
  }
}
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>Submit a data-subject request</h1>
    <p>
      No account is needed. After submitting, you'll be taken to a status
      page you can bookmark to check progress later.
    </p>

    <form @submit.prevent="submit">
      <div>
        <label for="request_type">What would you like to request?</label>
        <select
          id="request_type"
          v-model="requestType"
          :disabled="state === 'submitting'"
        >
          <option value="access">
            Access — see what data is held about me
          </option>
          <option value="export">
            Export — a copy of my data
          </option>
          <option value="erasure">
            Erasure — delete my data
          </option>
        </select>
      </div>

      <div>
        <label for="subject_identifier">Your email or account identifier</label>
        <input
          id="subject_identifier"
          v-model="subjectIdentifier"
          type="text"
          required
          :disabled="state === 'submitting'"
        >
      </div>

      <button
        type="submit"
        :disabled="state === 'submitting'"
      >
        {{ state === 'submitting' ? 'Submitting…' : 'Submit request' }}
      </button>

      <p
        v-if="state === 'error'"
        role="alert"
      >
        {{ errorMessage }}
      </p>
    </form>
  </div>
</template>
