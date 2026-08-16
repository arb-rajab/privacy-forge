<script setup>
import { usePage } from '@inertiajs/vue3'

defineProps({
  status: {
    type: String,
    default: null,
  },
})

// auth.user is shared globally by HandleInertiaRequests (R-05).
const page = usePage()

async function logout() {
  await fetch('/logout', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'X-CSRF-TOKEN': page.props.csrfToken,
    },
  })
  window.location.href = '/login'
}
</script>

<template>
  <div style="font-family: system-ui; padding: 2rem;">
    <h1>privacy-forge</h1>
    <p>{{ status }}</p>

    <p v-if="page.props.auth.user">
      Logged in as {{ page.props.auth.user.name }} ({{ page.props.auth.user.role }})
      — <a
        href="#"
        @click.prevent="logout"
      >Log out</a>
    </p>
    <p v-else>
      <a href="/login">Staff login</a>
    </p>

    <ul>
      <li><a href="/dsar">Submit a data-subject request</a></li>
      <li>
        <a href="/embed-example.html">Consent widget embed example</a>
        — a plain third-party page demonstrating the embeddable widget
        (see README for how to embed it on your own site)
      </li>
      <li v-if="page.props.auth.user">
        <a href="/admin/dsar">DSAR queue</a>
      </li>
    </ul>
  </div>
</template>
