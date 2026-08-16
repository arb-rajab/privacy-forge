<script setup>
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Admin DSAR queue (this session) — the last mile of Success Metric #1
// (00-project-brief.md): verify-identity and approve-erasure previously
// had no real buttons anywhere, only a DevTools console fetch() snippet
// (see README history / Session 14 handoff). Matches DsarSubmit.vue's
// house style (plain fetch(), no useForm/Layout). Backed unchanged by
// Admin\DsarQueueController (GET /api/v1/admin/dsar, Session 10) and
// Admin\DsarController (verify-identity/approve-erasure, ADR-0001/
// ADR-0007) — no new endpoints.
const page = usePage()

const dsars = ref([])
const state = ref('loading') // loading | ready | error
const actionState = ref({}) // dsarId -> { busy: bool, error: string|null }

function headers() {
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': page.props.csrfToken,
  }
}

async function loadQueue() {
  state.value = 'loading'
  try {
    const response = await fetch('/api/v1/admin/dsar', { headers: { Accept: 'application/json' } })
    if (!response.ok) {
      throw new Error(`Unexpected response (${response.status})`)
    }
    dsars.value = await response.json()
    state.value = 'ready'
  } catch {
    state.value = 'error'
  }
}

// Both actions share the same shape: POST, refresh the queue on success
// (the response is DsarStatusResource's data-subject-facing shape, not
// DsarQueueItemResource's staff-facing one, so re-fetching the queue is
// simpler than merging the two). On failure, the existing ProblemDetail
// body (type/title/status/detail/policy_id — see ADR-0001) is shown
// as-is for that row, not swallowed into a generic message — this is
// what makes an ABAC separation-of-duties denial (ADR-0007) visible as
// itself rather than as an indistinguishable error.
async function performAction(dsar, path) {
  actionState.value = { ...actionState.value, [dsar.id]: { busy: true, error: null } }
  try {
    const response = await fetch(`/api/v1/admin/dsar/${dsar.id}/${path}`, {
      method: 'POST',
      headers: headers(),
    })
    const body = await response.json()

    if (!response.ok) {
      actionState.value = { ...actionState.value, [dsar.id]: { busy: false, error: body.detail || body.title || `Request failed (${response.status})` } }
      return
    }

    actionState.value = { ...actionState.value, [dsar.id]: { busy: false, error: null } }
    await loadQueue()
  } catch (error) {
    actionState.value = { ...actionState.value, [dsar.id]: { busy: false, error: error.message } }
  }
}

function verifyIdentity(dsar) {
  return performAction(dsar, 'verify-identity')
}

function approveErasure(dsar) {
  return performAction(dsar, 'approve-erasure')
}

function canVerify(dsar) {
  return dsar.status === 'pending_verification'
}

function canApproveErasure(dsar) {
  return dsar.request_type === 'erasure' && dsar.status === 'in_progress' && dsar.erasure_approved_by === null
}

onMounted(loadQueue)
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 60rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>DSAR queue</h1>
    <p><a href="/">&larr; Back</a></p>

    <p v-if="state === 'loading'">
      Loading…
    </p>
    <p
      v-else-if="state === 'error'"
      role="alert"
    >
      Something went wrong loading the DSAR queue.
    </p>

    <p v-else-if="dsars.length === 0">
      No requests yet.
    </p>

    <table v-else>
      <thead>
        <tr>
          <th>Request</th>
          <th>Status</th>
          <th>Identity verified</th>
          <th>Erasure approved</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="dsar in dsars"
          :key="dsar.id"
        >
          <td>
            {{ dsar.request_type }}<br>
            <small>{{ dsar.id }}</small>
          </td>
          <td>{{ dsar.status }}</td>
          <td>{{ dsar.identity_verified_by ? `by ${dsar.identity_verified_by}` : '—' }}</td>
          <td>{{ dsar.erasure_approved_by ? `by ${dsar.erasure_approved_by}` : '—' }}</td>
          <td>
            <button
              v-if="canVerify(dsar)"
              type="button"
              :disabled="actionState[dsar.id]?.busy"
              @click="verifyIdentity(dsar)"
            >
              Verify identity
            </button>
            <button
              v-if="canApproveErasure(dsar)"
              type="button"
              :disabled="actionState[dsar.id]?.busy"
              @click="approveErasure(dsar)"
            >
              Approve erasure
            </button>
            <p
              v-if="actionState[dsar.id]?.error"
              role="alert"
            >
              {{ actionState[dsar.id].error }}
            </p>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
