<script setup>
// Shell aplikasi: hanya menampilkan route aktif.
// Tiap view mengatur lebarnya sendiri (login = full-screen desktop,
// dashboard trainer = mobile-first max-width).
import FeedbackHost from '@/components/FeedbackHost.vue'
</script>

<template>
  <div class="min-h-screen bg-background">
    <RouterView />
    <FeedbackHost />
  </div>
</template>
