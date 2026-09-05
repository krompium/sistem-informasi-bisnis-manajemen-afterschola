<script setup>
// Halaman sementara untuk modul yang belum diimplementasi.
// Judul & ikon diambil dari meta rute.
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => route.meta.title || 'Modul')
const icon = computed(() => route.meta.icon || 'construction')
</script>

<template>
  <div class="mx-auto flex max-w-2xl flex-col items-center justify-center py-space-3xl text-center">
    <div
      class="mb-space-lg flex h-16 w-16 items-center justify-center rounded-2xl bg-primary-fixed text-primary"
    >
      <span class="material-symbols-outlined text-[32px]">{{ icon }}</span>
    </div>
    <span class="font-label-sm text-label-sm uppercase tracking-widest text-secondary"
      >Segera Hadir</span
    >
    <h1 class="mt-space-2xs font-headline-xl text-headline-xl tracking-tight text-on-surface">
      {{ title }}
    </h1>
    <p class="mt-space-sm max-w-md font-body-md text-body-md text-on-surface-variant">
      Modul ini sedang dalam pengembangan. Fokus rilis saat ini: Login, Dashboard Trainer, dan
      Dashboard Management.
    </p>
    <RouterLink
      to="/"
      class="mt-space-lg inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
    >
      <span class="material-symbols-outlined text-[18px]">arrow_back</span>
      <span>Kembali ke Dashboard</span>
    </RouterLink>
  </div>
</template>
