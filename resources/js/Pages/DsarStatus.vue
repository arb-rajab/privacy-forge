<script setup>
import { ref, onMounted } from 'vue'

// Public DSAR status page (US-005/008/009), reachable via the signed
// token link returned by POST /dsar (rewritten by DsarSubmit.vue). This
// page renders no data of its own — it forwards signedToken plus
// whatever query string it was loaded with (expires/signature) straight
// through to the real, unchanged GET /api/v1/dsar/status/{signedToken}
// contract, so the exact same signature that was minted for that API
// path is what gets validated. No new endpoint, no separate signing.
const props = defineProps({
  signedToken: { type: String, required: true },
})

const state = ref('loading') // loading | ready | expired | error
const dsar = ref(null)
const downloadState = ref({})

function apiUrl() {
  return `/api/v1/dsar/status/${props.signedToken}${window.location.search}`
}

async function loadStatus() {
  state.value = 'loading'
  try {
    const response = await fetch(apiUrl(), { headers: { Accept: 'application/json' } })

    if (response.status === 410) {
      state.value = 'expired'
      return
    }

    if (!response.ok) {
      throw new Error(`Unexpected response (${response.status})`)
    }

    dsar.value = await response.json()
    state.value = 'ready'
  } catch {
    state.value = 'error'
  }
}

async function download(bundle) {
  downloadState.value[bundle.format] = 'downloading'
  try {
    const response = await fetch(bundle.download_url, { headers: { Accept: 'application/json' } })
    if (!response.ok) {
      throw new Error(`Download link failed (${response.status})`)
    }
    const { download_url: rawUrl } = await response.json()
    window.location.href = rawUrl
  } finally {
    downloadState.value[bundle.format] = 'idle'
  }
}

onMounted(loadStatus)
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>Your request status</h1>

    <p v-if="state === 'loading'">
      Loading…
    </p>

    <p
      v-else-if="state === 'expired'"
      role="alert"
    >
      This status link is invalid or has expired. Submit a new request
      to get a fresh one.
    </p>

    <p
      v-else-if="state === 'error'"
      role="alert"
    >
      Something went wrong loading your request status.
    </p>

    <div v-else-if="state === 'ready'">
      <p><strong>Request type:</strong> {{ dsar.request_type }}</p>
      <p><strong>Status:</strong> {{ dsar.status }}</p>

      <div v-if="dsar.export_bundles.length > 0">
        <h2>Your export is ready</h2>
        <ul>
          <li
            v-for="bundle in dsar.export_bundles"
            :key="bundle.format"
          >
            <button
              type="button"
              :disabled="downloadState[bundle.format] === 'downloading'"
              @click="download(bundle)"
            >
              Download ({{ bundle.format.toUpperCase() }})
            </button>
            — expires {{ new Date(bundle.expires_at).toLocaleString() }}
          </li>
        </ul>
      </div>

      <div v-if="dsar.deletion_certificate">
        <h2>Deletion certificate</h2>
        <p>Issued {{ new Date(dsar.deletion_certificate.issued_at).toLocaleString() }}</p>
        <p>{{ dsar.deletion_certificate.summary }}</p>
        <p v-if="dsar.deletion_certificate.exceptions">
          <strong>Note:</strong> {{ dsar.deletion_certificate.exceptions }}
        </p>
      </div>
    </div>
  </div>
</template>
