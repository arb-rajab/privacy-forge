<script setup>
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

// R-05 (10-risk-register.md) — real staff login. Matches DsarSubmit.vue's
// house style (plain fetch(), no useForm/Layout). csrfToken comes from
// HandleInertiaRequests' global share since this project's frontend
// calls fetch() directly rather than routing through Inertia's own axios
// instance (which would attach the equivalent header automatically).
const page = usePage()

const email = ref('')
const password = ref('')
const state = ref('idle') // idle | submitting | error
const errorMessage = ref('')

async function submit() {
  state.value = 'submitting'
  try {
    const response = await fetch('/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': page.props.csrfToken,
      },
      body: JSON.stringify({
        email: email.value.trim(),
        password: password.value,
      }),
    })

    const body = await response.json()

    if (!response.ok) {
      // Laravel's default JSON validation-exception shape:
      // { message, errors: { email: ["..."] } }. Both "unknown email"
      // and "wrong password" land in the same errors.email message
      // (T-13) — this doesn't distinguish them further.
      const message = body.errors?.email?.[0] || body.message || `Login failed (${response.status})`
      throw new Error(message)
    }

    window.location.href = body.redirect
  } catch (error) {
    errorMessage.value = error.message
    state.value = 'error'
  }
}
</script>

<template>
  <div style="font-family: system-ui, sans-serif; max-width: 24rem; margin: 4rem auto; padding: 0 1rem;">
    <h1>Staff login</h1>

    <form @submit.prevent="submit">
      <div>
        <label for="email">Email</label>
        <input
          id="email"
          v-model="email"
          type="email"
          required
          autocomplete="username"
          :disabled="state === 'submitting'"
        >
      </div>

      <div>
        <label for="password">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          required
          autocomplete="current-password"
          :disabled="state === 'submitting'"
        >
      </div>

      <button
        type="submit"
        :disabled="state === 'submitting'"
      >
        {{ state === 'submitting' ? 'Logging in…' : 'Log in' }}
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
