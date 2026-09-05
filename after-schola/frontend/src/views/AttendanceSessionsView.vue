<script setup>
// Daftar sesi untuk Absensi (dipakai Trainer & Management).
// Pilih pertemuan → buka halaman absensi detail. Base path menyesuaikan peran.
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatDate, initials, isOnsite, modeLabel, sessionStatus, sessionTime, unwrap } from '@/lib/format'

const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const errorMessage = ref('')
const sessions = ref([])
const search = ref('')
const tab = ref('active') // active (hari ini + perlu diabsen) | all

const basePath = computed(() => (auth.isManagement ? '/management/absensi' : '/trainer/absensi'))

const filtered = computed(() => {
  let list = sessions.value
  if (tab.value === 'active')
    list = list.filter((s) => ['today', 'pending'].includes(sessionStatus(s).key))
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(
      (s) =>
        (s.school?.name || '').toLowerCase().includes(q) ||
        (s.trainer?.name || '').toLowerCase().includes(q) ||
        (s.classroom?.name || '').toLowerCase().includes(q),
    )
  }
  return list
})

const counts = computed(() => ({
  active: sessions.value.filter((s) => ['today', 'pending'].includes(sessionStatus(s).key)).length,
  all: sessions.value.length,
}))

function open(s) {
  router.push(`${basePath.value}/${s.id}`)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/sessions')
    sessions.value = unwrap(data)
  } catch (err) {
    errorMessage.value = 'Gagal memuat sesi. Coba muat ulang.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
        >Modul Absensi</span
      >
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
        Ambil Absensi
      </h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Pilih pertemuan untuk mencatat kehadiran murid{{ auth.isTrainer ? ' & check-in trainer' : '' }}.
      </p>
    </div>

    <!-- Controls -->
    <div class="mb-space-lg flex flex-col gap-space-sm sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-space-xs">
        <button
          class="inline-flex items-center gap-1.5 rounded-full px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="tab === 'active' ? 'bg-primary-container text-on-primary shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container'"
          @click="tab = 'active'"
        >
          Perlu Diabsen
          <span class="rounded-full px-1.5 text-[11px]" :class="tab === 'active' ? 'bg-white/20' : 'bg-surface-container-high'">{{ counts.active }}</span>
        </button>
        <button
          class="inline-flex items-center gap-1.5 rounded-full px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="tab === 'all' ? 'bg-primary-container text-on-primary shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container'"
          @click="tab = 'all'"
        >
          Semua
          <span class="rounded-full px-1.5 text-[11px]" :class="tab === 'all' ? 'bg-white/20' : 'bg-surface-container-high'">{{ counts.all }}</span>
        </button>
      </div>
      <div class="relative w-full sm:w-72">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline">search</span>
        <input
          v-model="search"
          type="text"
          placeholder="Cari sekolah, trainer, level…"
          class="w-full rounded-xl bg-surface-container-lowest py-2.5 pl-9 pr-space-md font-body-md text-body-md text-on-surface shadow-sm placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>
    </div>

    <!-- Error -->
    <div
      v-if="errorMessage"
      class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container"
    >
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md md:grid-cols-2">
      <div v-for="i in 4" :key="i" class="h-32 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div
      v-else-if="!filtered.length"
      class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center"
    >
      <span class="material-symbols-outlined text-[36px] text-outline">fact_check</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada sesi</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
        {{ tab === 'active' ? 'Tidak ada pertemuan yang perlu diabsen saat ini.' : 'Belum ada pertemuan terjadwal.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md md:grid-cols-2">
      <button
        v-for="s in filtered"
        :key="s.id"
        class="group relative flex flex-col gap-space-sm overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg text-left shadow-sm transition-all hover:shadow-md"
        @click="open(s)"
      >
        <div class="absolute left-0 top-0 h-full w-1.5" :class="isOnsite(s.mode) ? 'bg-emerald-500' : 'bg-primary-container'"></div>
        <div class="flex items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Pertemuan #{{ s.meeting_no }} • {{ formatDate(s.date, { day: 'numeric', month: 'short' }) }}<template v-if="sessionTime(s)"> • {{ sessionTime(s) }}</template></span>
            <h3 class="mt-0.5 font-headline-md text-headline-md font-bold text-on-surface">{{ s.school?.name || 'Sekolah' }}</h3>
          </div>
          <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold" :class="sessionStatus(s).cls">
            <span class="h-1.5 w-1.5 rounded-full" :class="sessionStatus(s).dot"></span>
            {{ sessionStatus(s).label }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-x-space-md gap-y-1 font-body-sm text-body-sm text-on-surface-variant">
          <span class="inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px] text-primary">menu_book</span>
            {{ s.classroom?.name || s.classroom?.level || 'Level' }}
          </span>
          <span class="inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px] text-primary">person</span>
            {{ s.trainer?.name || '—' }}
          </span>
          <span class="inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px] text-primary">{{ isOnsite(s.mode) ? 'location_on' : 'videocam' }}</span>
            {{ modeLabel(s.mode) }}
          </span>
        </div>
        <div class="mt-space-xs flex items-center justify-between border-t border-surface-container pt-space-sm">
          <span class="inline-flex items-center gap-1.5 font-label-sm text-label-sm text-on-surface-variant">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#ABC3FE] text-[10px] font-bold text-[#001A42]">{{ initials(s.trainer?.name) }}</span>
            {{ s.present_count ?? 0 }} hadir tercatat
          </span>
          <span class="inline-flex items-center gap-1 font-label-md text-label-md font-semibold text-primary">
            Buka <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5">arrow_forward</span>
          </span>
        </div>
      </button>
    </div>
  </div>
</template>
