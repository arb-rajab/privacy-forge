<script setup>
defineProps({
  status: {
    type: String,
    default: null,
  },
})
</script>

<template>
  <div style="font-family: system-ui; padding: 2rem;">
    <h1>privacy-forge</h1>
    <p>{{ status }}</p>
    <ul>
      <li><a href="/dsar">Submit a data-subject request</a></li>
      <li>
        <a href="/embed-example.html">Consent widget embed example</a>
        — a plain third-party page demonstrating the embeddable widget
        (see README for how to embed it on your own site)
      </li>
    </ul>
  </div>
</template>
