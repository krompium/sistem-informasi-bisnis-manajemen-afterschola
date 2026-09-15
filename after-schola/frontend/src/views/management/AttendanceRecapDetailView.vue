<script setup>
// Rekap Absensi (Management) — langkah 2: detail rekap satu sekolah.
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import SchoolRecap from '@/components/SchoolRecap.vue'

const route = useRoute()
const schoolId = route.params.id
const recapRef = ref(null)

const schoolName = computed(() => recapRef.value?.recap?.school?.name || 'Sekolah')
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl pt-space-lg">
    <RouterLink
      to="/management/absensi"
      class="mb-space-sm inline-flex w-fit items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline"
    >
      <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali ke daftar sekolah
    </RouterLink>

    <div class="mb-space-md flex flex-col gap-space-2xs">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
        >Rekap Absensi Sekolah</span
      >
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
        {{ schoolName }}
      </h1>
    </div>

    <SchoolRecap ref="recapRef" :school-id="schoolId" />
  </div>
</template>
