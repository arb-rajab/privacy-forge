<script setup>
import { ref, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

// ABAC policy definition view/edit (stretch item, Session 21) — a UI
// shell around the unchanged Admin\PolicyController JSON API
// (policy.update, ADR-0006). Owner-only at the API layer (PolicyEvaluator
// denies anyone else with a real policy_id, surfaced below as an inline
// error like every other admin page).
//
// This is the most consequential admin screen in the product — editing
// separation-of-duties logic (e.g. ADR-0007's identity-verifier ≠
// erasure-approver rule) by accident would be a real security regression,
// not just a UX slip. So editing here is deliberately friction-heavy
// compared to every other form on this page: raw JSON textareas (these
// are genuinely raw ABAC condition objects, not a small fixed set of
// fields something friendlier could safely abstract) plus a required,
// unchecked-by-default confirmation checkbox that must be ticked before
// "Save new version" is enabled at all.
const page = usePage()

const policies = ref([])
const state = ref('loading') // loading | ready | error

// action_name -> { subjectConditionsText, resourceConditionsText, environmentConditionsText, effect, confirmed, busy, error }
const editState = ref({})

function headers() {
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': page.props.csrfToken,
  }
}

function activeVersionsByAction() {
  const byAction = {}
  for (const policy of policies.value) {
    if (policy.status !== 'active') continue
    byAction[policy.action_name] = policy
  }
  return byAction
}

function initEditState() {
  const active = activeVersionsByAction()
  const next = {}
  for (const [actionName, policy] of Object.entries(active)) {
    next[actionName] = {
      policyId: policy.id,
      subjectConditionsText: JSON.stringify(policy.subject_conditions ?? {}, null, 2),
      resourceConditionsText: JSON.stringify(policy.resource_conditions ?? {}, null, 2),
      environmentConditionsText: JSON.stringify(policy.environment_conditions ?? {}, null, 2),
      effect: policy.effect,
      confirmed: false,
      busy: false,
      error: null,
    }
  }
  editState.value = next
}

async function loadPolicies() {
  state.value = 'loading'
  try {
    const response = await fetch('/api/v1/admin/policies', { headers: { Accept: 'application/json' } })
    if (!response.ok) {
      throw new Error(`Unexpected response (${response.status})`)
    }
    policies.value = await response.json()
    initEditState()
    state.value = 'ready'
  } catch {
    state.value = 'error'
  }
}

function supersededVersions(actionName) {
  return policies.value
    .filter((p) => p.action_name === actionName && p.status !== 'active')
    .sort((a, b) => b.version - a.version)
}

async function saveVersion(actionName) {
  const edit = editState.value[actionName]
  if (!edit || !edit.confirmed) return

  let subjectConditions
  let resourceConditions
  let environmentConditions
  try {
    subjectConditions = JSON.parse(edit.subjectConditionsText || '{}')
    resourceConditions = JSON.parse(edit.resourceConditionsText || '{}')
    environmentConditions = JSON.parse(edit.environmentConditionsText || '{}')
  } catch {
    editState.value = { ...editState.value, [actionName]: { ...edit, error: 'Conditions must each be valid JSON objects.' } }
    return
  }

  editState.value = { ...editState.value, [actionName]: { ...edit, busy: true, error: null } }
  try {
    const response = await fetch(`/api/v1/admin/policies/${edit.policyId}`, {
      method: 'PATCH',
      headers: headers(),
      body: JSON.stringify({
        subject_conditions: subjectConditions,
        resource_conditions: resourceConditions,
        environment_conditions: environmentConditions,
        effect: edit.effect,
      }),
    })
    const body = await response.json()

    if (!response.ok) {
      editState.value = { ...editState.value, [actionName]: { ...edit, busy: false, error: body.detail || body.title || `Request failed (${response.status})` } }
      return
    }

    await loadPolicies()
  } catch (error) {
    editState.value = { ...editState.value, [actionName]: { ...edit, busy: false, error: error.message } }
  }
}

onMounted(loadPolicies)
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 60rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>ABAC policy definitions</h1>
    <p>
      <a href="/">&larr; Back</a>
      · <a href="/admin/dsar">DSAR queue</a>
      · <a href="/admin/retention">Retention policies</a>
      · <a href="/admin/ropa">RoPA export</a>
      · <a href="/admin/audit-log">Audit log</a>
    </p>

    <div
      role="note"
      style="border: 1px solid #b33; padding: 0.75rem 1rem; margin-bottom: 1.5rem; background: #fff2f2;"
    >
      <strong>Owner-only, security-sensitive.</strong> Saving here creates
      a new active version of this action's access-control policy
      immediately — including rules like "the identity verifier cannot
      also approve erasure" (ADR-0007). Each policy below requires you to
      tick its own confirmation box before "Save new version" is
      enabled, so this can't be triggered by a stray click.
    </div>

    <p v-if="state === 'loading'">
      Loading…
    </p>
    <p
      v-else-if="state === 'error'"
      role="alert"
    >
      Something went wrong loading policy definitions.
    </p>

    <template v-else>
      <section
        v-for="[actionName, edit] in Object.entries(editState)"
        :key="actionName"
        style="margin-bottom: 2rem; border: 1px solid #ccc; padding: 1rem;"
      >
        <h2><code>{{ actionName }}</code></h2>

        <p>
          Current active version's effect: <strong>{{ edit.effect }}</strong>
        </p>

        <template v-if="supersededVersions(actionName).length > 0">
          <details>
            <summary>Superseded versions ({{ supersededVersions(actionName).length }})</summary>
            <ul>
              <li
                v-for="old in supersededVersions(actionName)"
                :key="old.id"
              >
                v{{ old.version }} — effect: {{ old.effect }}
              </li>
            </ul>
          </details>
        </template>

        <form @submit.prevent="saveVersion(actionName)">
          <div>
            <label :for="`${actionName}-effect`">Effect</label>
            <select
              :id="`${actionName}-effect`"
              v-model="edit.effect"
              :disabled="edit.busy"
            >
              <option value="allow">
                allow
              </option>
              <option value="deny">
                deny
              </option>
            </select>
          </div>

          <div>
            <label :for="`${actionName}-subject`">Subject conditions (JSON)</label>
            <textarea
              :id="`${actionName}-subject`"
              v-model="edit.subjectConditionsText"
              rows="4"
              style="width: 100%; font-family: monospace;"
              :disabled="edit.busy"
            />
          </div>

          <div>
            <label :for="`${actionName}-resource`">Resource conditions (JSON)</label>
            <textarea
              :id="`${actionName}-resource`"
              v-model="edit.resourceConditionsText"
              rows="4"
              style="width: 100%; font-family: monospace;"
              :disabled="edit.busy"
            />
          </div>

          <div>
            <label :for="`${actionName}-environment`">Environment conditions (JSON)</label>
            <textarea
              :id="`${actionName}-environment`"
              v-model="edit.environmentConditionsText"
              rows="4"
              style="width: 100%; font-family: monospace;"
              :disabled="edit.busy"
            />
          </div>

          <div style="margin: 0.75rem 0;">
            <label :for="`${actionName}-confirm`">
              <input
                :id="`${actionName}-confirm`"
                v-model="edit.confirmed"
                type="checkbox"
                :disabled="edit.busy"
              >
              I understand this replaces the live access-control logic for <code>{{ actionName }}</code>.
            </label>
          </div>

          <button
            type="submit"
            :disabled="edit.busy || !edit.confirmed"
          >
            {{ edit.busy ? 'Saving…' : 'Save new version' }}
          </button>

          <p
            v-if="edit.error"
            role="alert"
          >
            {{ edit.error }}
          </p>
        </form>
      </section>
    </template>
  </div>
</template>
