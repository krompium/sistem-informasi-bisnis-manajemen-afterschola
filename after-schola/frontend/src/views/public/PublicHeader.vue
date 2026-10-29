<script setup>
// Header bersama halaman publik (program, detail program, daftar trial).
// Semua target sentuh minimal 44px supaya nyaman dipakai dari HP (NFR-10).
defineProps({ hideCta: { type: Boolean, default: false } })
</script>

<template>
  <header class="bg-brandNavy text-white">
    <div class="mx-auto flex max-w-5xl items-center justify-between gap-2 px-4 py-2">
      <RouterLink to="/" class="flex min-h-[44px] items-center gap-2 text-lg font-extrabold tracking-tight">
        <span class="material-symbols-outlined text-brandSun" aria-hidden="true">school</span>
        AfterSchola
      </RouterLink>
      <nav class="flex items-center gap-1 text-sm font-semibold">
        <RouterLink
          :to="{ name: 'public.programs' }"
          class="hidden min-h-[44px] items-center px-3 text-white/90 hover:text-brandSun sm:inline-flex"
        >
          Program
        </RouterLink>
        <RouterLink
          :to="{ name: 'login' }"
          class="inline-flex min-h-[44px] items-center gap-1 px-3 text-white/90 hover:text-brandSun"
        >
          <span class="material-symbols-outlined text-lg" aria-hidden="true">login</span>
          Masuk
        </RouterLink>
        <RouterLink
          v-if="!hideCta"
          :to="{ name: 'public.trial-registration' }"
          class="inline-flex min-h-[44px] items-center rounded-full bg-brandGreen px-4 text-white shadow-lg hover:bg-emerald-600"
        >
          Coba Gratis
        </RouterLink>
      </nav>
    </div>
  </header>
</template>
