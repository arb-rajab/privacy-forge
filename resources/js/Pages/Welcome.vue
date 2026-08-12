<script setup>
defineProps({
  status: String,
})
</script>

<template>
  <div style="font-family: system-ui; padding: 2rem;">
    <h1>privacy-forge</h1>
    <p>{{ status }}</p>
    <p>
      If you can read this rendered by Vue via Inertia, the environment
      baseline (Session 5) is working: Laravel is serving requests, Vite
      built the frontend assets, and Inertia is passing props from the
      backend to a Vue component.
    </p>
  </div>
</template>
