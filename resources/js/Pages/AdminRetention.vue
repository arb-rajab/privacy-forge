<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Retention policy management (US-010/US-011, ADR-0002). Matches
// AdminDsarQueue.vue's house style (plain fetch(), no useForm/Layout).
// Backed by Admin\DataCategoryController / Admin\RetentionPolicyController
// (retention.policy.manage, Session 11). The "past execution history"
// section below was an honest placeholder until this session (B-05):
// GET /api/v1/admin/retention-policies/{id}/executions is the one new
// endpoint this page now calls, sharing the same retention.policy.manage
// gate as every other action here — see that controller's `executions()`
// method.
//
// The one thing this page is careful about: there is no "run real
// execution now" button anywhere below, and that is deliberate, not a
// missing feature. Real execution (US-012) only ever runs on the
// server's own schedule (App\Console\Commands\ExecuteRetentionPoliciesCommand)
// and is explicitly NOT exposed over HTTP by design — see
// docs/project-memory/09-decision-log.md's Session 11 entry and this
// controller's own class comment ("Scheduled real execution... is
// deliberately NOT gated here or anywhere else"). The only retention
// action a staff member can trigger from this screen is the read-only
// dry-run preview; that is stated plainly in the panel below rather than
// left for a reader to infer from the absence of a button.
const page = usePage()

const categories = ref([])
const policies = ref([])
const state = ref('loading') // loading | ready | error

const newCategory = ref({ name: '', description: '', sensitivity: 'standard', subject_table: 'consent_records' })
const categoryState = ref({ busy: false, error: null })

const newPolicy = ref({ data_category_id: '', retention_period_days: 365, post_expiry_action: 'anonymise' })
const policyState = ref({ busy: false, error: null })

// policyId -> { busy, error, preview: { affected_record_count, sample_record_ids } | null }
const previewState = ref({})

// policyId -> { busy, error, executions: array | null } — executions is
// null until "View history" is clicked for that policy (not fetched
// up front for every policy on page load).
const historyState = ref({})
const selectedHistoryPolicyId = ref('')

function headers() {
  return {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': page.props.csrfToken,
  }
}

function categoryName(dataCategoryId) {
  const category = categories.value.find((c) => c.id === dataCategoryId)
  return category ? category.name : dataCategoryId
}

const activePolicies = computed(() => policies.value.filter((p) => p.status === 'active'))
const deprecatedPolicies = computed(() => policies.value.filter((p) => p.status !== 'active'))

async function loadAll() {
  state.value = 'loading'
  try {
    const [categoriesResponse, policiesResponse] = await Promise.all([
      fetch('/api/v1/admin/data-categories', { headers: { Accept: 'application/json' } }),
      fetch('/api/v1/admin/retention-policies', { headers: { Accept: 'application/json' } }),
    ])

    if (!categoriesResponse.ok || !policiesResponse.ok) {
      throw new Error('Unexpected response loading data categories or retention policies')
    }

    categories.value = await categoriesResponse.json()
    policies.value = await policiesResponse.json()
    state.value = 'ready'
  } catch {
    state.value = 'error'
  }
}

async function createCategory() {
  categoryState.value = { busy: true, error: null }
  try {
    const response = await fetch('/api/v1/admin/data-categories', {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify(newCategory.value),
    })
    const body = await response.json()

    if (!response.ok) {
      categoryState.value = { busy: false, error: body.detail || body.title || `Request failed (${response.status})` }
      return
    }

    newCategory.value = { name: '', description: '', sensitivity: 'standard', subject_table: 'consent_records' }
    categoryState.value = { busy: false, error: null }
    await loadAll()
  } catch (error) {
    categoryState.value = { busy: false, error: error.message }
  }
}

async function createPolicy() {
  policyState.value = { busy: true, error: null }
  try {
    const response = await fetch('/api/v1/admin/retention-policies', {
      method: 'POST',
      headers: headers(),
      body: JSON.stringify({
        ...newPolicy.value,
        retention_period_days: Number(newPolicy.value.retention_period_days),
      }),
    })
    const body = await response.json()

    if (!response.ok) {
      policyState.value = { busy: false, error: body.detail || body.title || `Request failed (${response.status})` }
      return
    }

    newPolicy.value = { data_category_id: '', retention_period_days: 365, post_expiry_action: 'anonymise' }
    policyState.value = { busy: false, error: null }
    await loadAll()
  } catch (error) {
    policyState.value = { busy: false, error: error.message }
  }
}

// The one action button on this page that touches real data selection —
// and even this makes no changes: RetentionExecutor::preview() only
// reads via RetentionSelector (ADR-0002), so the result shown below is
// guaranteed to match what a real run would affect, without this screen
// being the thing that ever triggers a real run.
async function runDryRun(policy) {
  previewState.value = { ...previewState.value, [policy.id]: { busy: true, error: null, preview: previewState.value[policy.id]?.preview ?? null } }
  try {
    const response = await fetch(`/api/v1/admin/retention-policies/${policy.id}/dry-run`, {
      method: 'POST',
      headers: headers(),
    })
    const body = await response.json()

    if (!response.ok) {
      previewState.value = { ...previewState.value, [policy.id]: { busy: false, error: body.detail || body.title || `Request failed (${response.status})`, preview: null } }
      return
    }

    previewState.value = { ...previewState.value, [policy.id]: { busy: false, error: null, preview: body } }
  } catch (error) {
    previewState.value = { ...previewState.value, [policy.id]: { busy: false, error: error.message, preview: null } }
  }
}

// B-05: real execution history, fetched for one policy at a time (chosen
// from the dropdown below) rather than eagerly for every policy on page
// load — this page may list many policies; most staff visits won't need
// every one's history at once.
async function loadHistory(policyId) {
  if (!policyId) return
  historyState.value = { ...historyState.value, [policyId]: { busy: true, error: null, executions: historyState.value[policyId]?.executions ?? null } }
  try {
    const response = await fetch(`/api/v1/admin/retention-policies/${policyId}/executions`, {
      headers: { Accept: 'application/json' },
    })
    if (!response.ok) {
      const body = await response.json()
      historyState.value = { ...historyState.value, [policyId]: { busy: false, error: body.detail || body.title || `Request failed (${response.status})`, executions: null } }
      return
    }

    const executions = await response.json()
    historyState.value = { ...historyState.value, [policyId]: { busy: false, error: null, executions } }
  } catch (error) {
    historyState.value = { ...historyState.value, [policyId]: { busy: false, error: error.message, executions: null } }
  }
}

onMounted(loadAll)
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 60rem; margin: 2rem auto; padding: 0 1rem;">
    <h1>Retention policies</h1>
    <p>
      <a href="/">&larr; Back</a>
      · <a href="/admin/dsar">DSAR queue</a>
      · <a href="/admin/ropa">RoPA export</a>
      · <a href="/admin/policies">ABAC policies</a>
      · <a href="/admin/audit-log">Audit log</a>
    </p>

    <div
      role="note"
      style="border: 1px solid #999; padding: 0.75rem 1rem; margin-bottom: 1.5rem; background: #f6f6f6;"
    >
      <strong>What this page can and can't do:</strong> the button on each
      policy below is labelled <strong>"Preview (dry run)"</strong> — it
      only reads candidate records, never deletes or anonymises anything.
      There is deliberately no "run now" button for real execution
      anywhere on this page: real execution only ever runs on the
      server's own schedule, never from a staff HTTP request (see
      <code>09-decision-log.md</code>, Session 11) — so the dry-run
      preview is the only retention action available here beyond
      defining policies themselves.
    </div>

    <p v-if="state === 'loading'">
      Loading…
    </p>
    <p
      v-else-if="state === 'error'"
      role="alert"
    >
      Something went wrong loading data categories or retention policies.
    </p>

    <template v-else>
      <section style="margin-bottom: 2rem;">
        <h2>Data categories</h2>
        <p v-if="categories.length === 0">
          No data categories defined yet.
        </p>
        <table v-else>
          <thead>
            <tr>
              <th>Name</th>
              <th>Sensitivity</th>
              <th>Subject table</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="category in categories"
              :key="category.id"
            >
              <td>{{ category.name }}</td>
              <td>{{ category.sensitivity }}</td>
              <td>{{ category.subject_table }}</td>
              <td>{{ category.description || '—' }}</td>
            </tr>
          </tbody>
        </table>

        <h3>Add a data category</h3>
        <form @submit.prevent="createCategory">
          <div>
            <label for="category_name">Name</label>
            <input
              id="category_name"
              v-model="newCategory.name"
              type="text"
              required
              :disabled="categoryState.busy"
            >
          </div>
          <div>
            <label for="category_description">Description</label>
            <input
              id="category_description"
              v-model="newCategory.description"
              type="text"
              :disabled="categoryState.busy"
            >
          </div>
          <div>
            <label for="category_sensitivity">Sensitivity</label>
            <select
              id="category_sensitivity"
              v-model="newCategory.sensitivity"
              :disabled="categoryState.busy"
            >
              <option value="standard">
                Standard
              </option>
              <option value="elevated">
                Elevated
              </option>
              <option value="special_category">
                Special category
              </option>
            </select>
          </div>
          <div>
            <label for="category_subject_table">Governs table</label>
            <select
              id="category_subject_table"
              v-model="newCategory.subject_table"
              :disabled="categoryState.busy"
            >
              <option value="consent_records">
                consent_records
              </option>
              <option value="dsar_requests">
                dsar_requests
              </option>
            </select>
          </div>
          <button
            type="submit"
            :disabled="categoryState.busy"
          >
            {{ categoryState.busy ? 'Saving…' : 'Add data category' }}
          </button>
          <p
            v-if="categoryState.error"
            role="alert"
          >
            {{ categoryState.error }}
          </p>
        </form>
      </section>

      <section style="margin-bottom: 2rem;">
        <h2>Retention policies</h2>

        <h3>Active</h3>
        <p v-if="activePolicies.length === 0">
          No active retention policies.
        </p>
        <table v-else>
          <thead>
            <tr>
              <th>Data category</th>
              <th>Retention period</th>
              <th>Post-expiry action</th>
              <th>Version</th>
              <th>Preview (dry run)</th>
            </tr>
          </thead>
          <tbody>
            <template
              v-for="policy in activePolicies"
              :key="policy.id"
            >
              <tr>
                <td>{{ categoryName(policy.data_category_id) }}</td>
                <td>{{ policy.retention_period_days }} days</td>
                <td>{{ policy.post_expiry_action }}</td>
                <td>v{{ policy.version }}</td>
                <td>
                  <button
                    type="button"
                    :disabled="previewState[policy.id]?.busy"
                    @click="runDryRun(policy)"
                  >
                    {{ previewState[policy.id]?.busy ? 'Previewing…' : 'Preview (dry run) — no changes made' }}
                  </button>
                </td>
              </tr>
              <tr v-if="previewState[policy.id]?.preview || previewState[policy.id]?.error">
                <td colspan="5">
                  <div
                    v-if="previewState[policy.id]?.preview"
                    role="status"
                    style="border: 1px solid #2a6; padding: 0.5rem 0.75rem; background: #eefaf0;"
                  >
                    <strong>Preview result — no records were changed.</strong>
                    {{ previewState[policy.id].preview.affected_record_count }} record(s) would be affected.
                    <span v-if="previewState[policy.id].preview.sample_record_ids.length">
                      Sample IDs: {{ previewState[policy.id].preview.sample_record_ids.join(', ') }}
                    </span>
                  </div>
                  <p
                    v-else
                    role="alert"
                  >
                    {{ previewState[policy.id].error }}
                  </p>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <template v-if="deprecatedPolicies.length > 0">
          <h3>Deprecated (superseded versions)</h3>
          <table>
            <thead>
              <tr>
                <th>Data category</th>
                <th>Retention period</th>
                <th>Post-expiry action</th>
                <th>Version</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="policy in deprecatedPolicies"
                :key="policy.id"
              >
                <td>{{ categoryName(policy.data_category_id) }}</td>
                <td>{{ policy.retention_period_days }} days</td>
                <td>{{ policy.post_expiry_action }}</td>
                <td>v{{ policy.version }}</td>
              </tr>
            </tbody>
          </table>
        </template>

        <h3>Add a retention policy</h3>
        <form @submit.prevent="createPolicy">
          <div>
            <label for="policy_category">Data category</label>
            <select
              id="policy_category"
              v-model="newPolicy.data_category_id"
              required
              :disabled="policyState.busy"
            >
              <option
                value=""
                disabled
              >
                Select a data category
              </option>
              <option
                v-for="category in categories"
                :key="category.id"
                :value="category.id"
              >
                {{ category.name }}
              </option>
            </select>
          </div>
          <div>
            <label for="policy_period">Retention period (days)</label>
            <input
              id="policy_period"
              v-model="newPolicy.retention_period_days"
              type="number"
              min="1"
              required
              :disabled="policyState.busy"
            >
          </div>
          <div>
            <label for="policy_action">Post-expiry action</label>
            <select
              id="policy_action"
              v-model="newPolicy.post_expiry_action"
              :disabled="policyState.busy"
            >
              <option value="anonymise">
                Anonymise
              </option>
              <option value="erase">
                Erase
              </option>
            </select>
          </div>
          <button
            type="submit"
            :disabled="policyState.busy"
          >
            {{ policyState.busy ? 'Saving…' : 'Add retention policy' }}
          </button>
          <p
            v-if="policyState.error"
            role="alert"
          >
            {{ policyState.error }}
          </p>
        </form>
      </section>

      <section>
        <h2>Past execution history</h2>
        <p>
          Every dry-run and real execution of a policy is recorded — pick
          a policy below to see its history, most recent first, including
          the deletion certificate a real execution produced (a dry run
          never produces one, ADR-0002).
        </p>

        <label for="history_policy">Policy</label>
        <select
          id="history_policy"
          v-model="selectedHistoryPolicyId"
          @change="loadHistory(selectedHistoryPolicyId)"
        >
          <option value="">
            Select a policy
          </option>
          <option
            v-for="policy in policies"
            :key="policy.id"
            :value="policy.id"
          >
            {{ categoryName(policy.data_category_id) }} v{{ policy.version }} ({{ policy.status }})
          </option>
        </select>

        <template v-if="selectedHistoryPolicyId">
          <p v-if="historyState[selectedHistoryPolicyId]?.busy">
            Loading…
          </p>
          <p
            v-else-if="historyState[selectedHistoryPolicyId]?.error"
            role="alert"
          >
            {{ historyState[selectedHistoryPolicyId].error }}
          </p>
          <p v-else-if="historyState[selectedHistoryPolicyId]?.executions?.length === 0">
            No executions recorded yet for this policy.
          </p>
          <table v-else-if="historyState[selectedHistoryPolicyId]?.executions">
            <thead>
              <tr>
                <th>Mode</th>
                <th>Affected records</th>
                <th>Executed at</th>
                <th>Certificate</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="execution in historyState[selectedHistoryPolicyId].executions"
                :key="execution.id"
              >
                <td>{{ execution.mode === 'real' ? 'Real execution' : 'Dry run (preview)' }}</td>
                <td>{{ execution.affected_record_count }}</td>
                <td>{{ execution.executed_at }}</td>
                <td>
                  <span v-if="!execution.certificate">—</span>
                  <span v-else>
                    {{ execution.certificate.summary }}
                    <template v-if="execution.certificate.exceptions">
                      <br><strong>Exceptions:</strong> {{ execution.certificate.exceptions }}
                    </template>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </template>
      </section>
    </template>
  </div>
</template>
