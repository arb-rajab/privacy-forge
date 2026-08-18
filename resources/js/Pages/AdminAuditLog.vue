<script setup>
import { ref, onMounted } from 'vue'

// Audit log query view (B-04, this session) — a UI shell around the new
// GET /api/v1/admin/audit-log endpoint (Admin\AuditLogController,
// audit.log.view). Matches AdminRetention.vue/AdminPolicies.vue's house
// style (plain fetch(), no useForm/Layout). Read-only: no button on this
// page ever writes anything.
//
// Row-level scope is decided server-side, not here: an Owner's response
// is the full audit log; a Privacy Manager's response is already
// filtered to entries their own actions produced (02-requirements.md's
// roles matrix — "view audit log entries related to their actions").
// This page does not attempt to replicate or second-guess that scoping
// client-side; it renders whatever rows the API actually returned.
const filters = ref({ resourceType: '', resourceId: '', since: '', until: '' })
const entries = ref([])
const state = ref('idle') // idle | loading | ready | error
const errorMessage = ref('')

function buildQuery() {
  const params = new URLSearchParams()
  if (filters.value.resourceType) params.set('resourceType', filters.value.resourceType)
  if (filters.value.resourceId) params.set('resourceId', filters.value.resourceId)
  if (filters.value.since) params.set('since', filters.value.since)
  if (filters.value.until) params.set('until', filters.value.until)
  const query = params.toString()
  return query ? `?${query}` : ''
}

async function load() {
  state.value = 'loading'
  errorMessage.value = ''
  try {
    const response = await fetch(`/api/v1/admin/audit-log${buildQuery()}`, {
      headers: { Accept: 'application/json' },
    })
    const body = await response.json()

    if (!response.ok) {
      state.value = 'error'
      errorMessage.value = body.detail || body.title || `Request failed (${response.status})`
      return
    }

    entries.value = body
    state.value = 'ready'
  } catch (error) {
    state.value = 'error'
    errorMessage.value = error.message
  }
}

onMounted(load)
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 60rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>Audit log</h1>
    <p>
      <a href="/">&larr; Back</a>
      · <a href="/admin/dsar">DSAR queue</a>
      · <a href="/admin/retention">Retention policies</a>
      · <a href="/admin/ropa">RoPA export</a>
      · <a href="/admin/policies">ABAC policies</a>
    </p>

    <div
      role="note"
      style="border: 1px solid #999; padding: 0.75rem 1rem; margin-bottom: 1.5rem; background: #f6f6f6;"
    >
      <strong>What you see here depends on your role.</strong> An Owner
      sees the full audit log. A Privacy Manager sees only entries their
      own actions produced. Support Staff cannot open this page at all.
      This is decided by the server on every request — not a client-side
      filter.
    </div>

    <form @submit.prevent="load">
      <div>
        <label for="filter_resource_type">Resource type</label>
        <input
          id="filter_resource_type"
          v-model="filters.resourceType"
          type="text"
          placeholder="e.g. retention_policy"
        >
      </div>
      <div>
        <label for="filter_resource_id">Resource ID</label>
        <input
          id="filter_resource_id"
          v-model="filters.resourceId"
          type="text"
          placeholder="uuid"
        >
      </div>
      <div>
        <label for="filter_since">Since</label>
        <input
          id="filter_since"
          v-model="filters.since"
          type="datetime-local"
        >
      </div>
      <div>
        <label for="filter_until">Until</label>
        <input
          id="filter_until"
          v-model="filters.until"
          type="datetime-local"
        >
      </div>
      <button type="submit">
        Apply filters
      </button>
    </form>

    <p v-if="state === 'loading'">
      Loading…
    </p>
    <p
      v-else-if="state === 'error'"
      role="alert"
    >
      {{ errorMessage }}
    </p>
    <p v-else-if="state === 'ready' && entries.length === 0">
      No matching audit log entries.
    </p>
    <table v-else-if="state === 'ready'">
      <thead>
        <tr>
          <th>When</th>
          <th>Actor type</th>
          <th>Action</th>
          <th>Resource</th>
          <th>Decision</th>
          <th>Reason</th>
          <th>Policy ID</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="entry in entries"
          :key="entry.id"
        >
          <td>{{ entry.created_at }}</td>
          <td>{{ entry.actor_type }}</td>
          <td>{{ entry.action }}</td>
          <td>{{ entry.resource_type }} / {{ entry.resource_id }}</td>
          <td>{{ entry.decision }}</td>
          <td>{{ entry.reason_code || '—' }}</td>
          <td>{{ entry.policy_id || '—' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
