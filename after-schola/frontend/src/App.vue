<script setup>
// Shell aplikasi: hanya menampilkan route aktif.
// Tiap view mengatur lebarnya sendiri (login = full-screen desktop,
// dashboard trainer = mobile-first max-width).
</script>

<template>
  <div class="min-h-screen bg-background">
    <RouterView />
  </div>
</template>
