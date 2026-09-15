<script setup>
// Rekap Absensi (Management) — langkah 1: daftar sekolah.
// Klik sekolah → halaman detail rekap sekolah tsb.
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { unwrap } from '@/lib/format'

const router = useRouter()
const loading = ref(true)
const errorMessage = ref('')
const schools = ref([])
const search = ref('')

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/schools')
    schools.value = unwrap(data)
  } catch (err) {
    errorMessage.value = 'Gagal memuat daftar sekolah.'
  } finally {
    loading.value = false
  }
}

function open(s) {
  router.push(`/management/absensi/sekolah/${s.id}`)
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
        >Modul Absensi • Rekap</span
      >
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
        Rekap Absensi
      </h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Pilih sekolah untuk melihat rekap kehadiran murid dan mengunduhnya (Excel / PDF).
      </p>
    </div>

    <div class="relative mb-space-lg w-full sm:max-w-sm">
      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline">search</span>
      <input
        v-model="search"
        type="text"
        placeholder="Cari sekolah…"
        class="w-full rounded-xl bg-surface-container-lowest py-2.5 pl-9 pr-space-md font-body-md text-body-md text-on-surface shadow-sm placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
      />
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!schools.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">domain_disabled</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada sekolah</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md sm:grid-cols-2 xl:grid-cols-3">
      <button
        v-for="s in schools.filter((x) => x.name.toLowerCase().includes(search.toLowerCase()))"
        :key="s.id"
        class="group flex items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-lg text-left shadow-sm transition-all hover:shadow-md"
        @click="open(s)"
      >
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary">
          <span class="material-symbols-outlined text-[26px]">school</span>
        </span>
        <span class="flex min-w-0 flex-col">
          <span class="truncate font-headline-sm text-headline-sm font-bold text-on-surface">{{ s.name }}</span>
          <span class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ s.address || 'Sekolah binaan' }}</span>
          <span class="mt-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ s.students_count ?? 0 }} murid • {{ s.classrooms?.length ?? 0 }} level</span>
        </span>
        <span class="material-symbols-outlined ml-auto text-[22px] text-primary transition-transform group-hover:translate-x-0.5">arrow_forward</span>
      </button>
    </div>
  </div>
</template>
