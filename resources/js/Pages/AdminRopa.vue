<script setup>
import { ref } from 'vue'

// RoPA export (US-013/FR-016) — a UI shell around the unchanged
// GET /api/v1/admin/ropa/export?format=csv|pdf endpoint
// (App\Http\Controllers\Admin\RopaController, ropa.export sensitive
// action, ADR-0001). Fetches the file via JS rather than a plain <a
// href> so a 403/422 renders as a real inline error (matching
// AdminDsarQueue.vue's convention) instead of the browser navigating
// away to a raw JSON response. A GET request needs no CSRF token
// (Laravel only verifies it for state-changing verbs), unlike every
// other admin page's fetch() calls.
// format -> { busy, error }
const exportState = ref({ csv: { busy: false, error: null }, pdf: { busy: false, error: null } })

async function download(format) {
  exportState.value = { ...exportState.value, [format]: { busy: true, error: null } }
  try {
    const response = await fetch(`/api/v1/admin/ropa/export?format=${format}`, {
      headers: { Accept: format === 'csv' ? 'text/csv' : 'application/pdf' },
    })

    if (!response.ok) {
      const body = await response.json()
      exportState.value = { ...exportState.value, [format]: { busy: false, error: body.detail || body.title || `Request failed (${response.status})` } }
      return
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `ropa-export.${format}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)

    exportState.value = { ...exportState.value, [format]: { busy: false, error: null } }
  } catch (error) {
    exportState.value = { ...exportState.value, [format]: { busy: false, error: error.message } }
  }
}
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 40rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>Record of Processing Activities (RoPA)</h1>
    <p>
      <a href="/">&larr; Back</a>
      · <a href="/admin/dsar">DSAR queue</a>
      · <a href="/admin/retention">Retention policies</a>
      · <a href="/admin/policies">ABAC policies</a>
    </p>

    <p>
      Generated on demand from currently active consent purposes (Art. 30
      RTM row, US-013) — never a stored, independently-drifting copy.
      Choose a format below.
    </p>

    <div style="display: flex; gap: 1rem; align-items: flex-start;">
      <div>
        <button
          type="button"
          :disabled="exportState.csv.busy"
          @click="download('csv')"
        >
          {{ exportState.csv.busy ? 'Preparing…' : 'Download CSV' }}
        </button>
        <p
          v-if="exportState.csv.error"
          role="alert"
        >
          {{ exportState.csv.error }}
        </p>
      </div>

      <div>
        <button
          type="button"
          :disabled="exportState.pdf.busy"
          @click="download('pdf')"
        >
          {{ exportState.pdf.busy ? 'Preparing…' : 'Download PDF' }}
        </button>
        <p
          v-if="exportState.pdf.error"
          role="alert"
        >
          {{ exportState.pdf.error }}
        </p>
      </div>
    </div>

    <p style="margin-top: 1.5rem;">
      <small>
        A deprecated purpose is excluded, per US-013 AC1's own "active
        purposes" wording. Gated by the <code>ropa.export</code> ABAC
        policy — Owner or Privacy Manager only; a denial from the API
        will show here as an inline error, not a silent failure.
      </small>
    </p>
  </div>
</template>
