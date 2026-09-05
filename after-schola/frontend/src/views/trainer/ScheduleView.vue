<script setup>
// Jadwal Saya (Trainer) — read-only sesuai FR-4.1: trainer hanya melihat & menjalankan.
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import MonthCalendar from '@/components/MonthCalendar.vue'
import { formatDate, isOnsite, modeLabel, sessionStatus, sessionTime, unwrap } from '@/lib/format'

const router = useRouter()

const loading = ref(true)
const errorMessage = ref('')
const sessions = ref([])
const tab = ref('today') // today | upcoming | all
const viewMode = ref('list') // 'list' | 'kalender'
const selectedDate = ref('')

const sessionsOnSelected = computed(() =>
  selectedDate.value
    ? sessions.value.filter((s) => String(s.date || '').slice(0, 10) === selectedDate.value)
    : [],
)

function onDayClick(dateStr) {
  selectedDate.value = selectedDate.value === dateStr ? '' : dateStr
}

const tabs = [
  { key: 'today', label: 'Hari Ini' },
  { key: 'upcoming', label: 'Akan Datang' },
  { key: 'all', label: 'Semua' },
]

const filtered = computed(() => {
  if (tab.value === 'all') return sessions.value
  if (tab.value === 'today')
    return sessions.value.filter((s) => ['today', 'pending'].includes(sessionStatus(s).key))
  return sessions.value.filter((s) => sessionStatus(s).key === 'upcoming')
})

const counts = computed(() => ({
  today: sessions.value.filter((s) => sessionStatus(s).key === 'today').length,
  upcoming: sessions.value.filter((s) => sessionStatus(s).key === 'upcoming').length,
  all: sessions.value.length,
}))

function canAbsen(s) {
  const k = sessionStatus(s).key
  return !s.is_locked && (k === 'today' || k === 'pending')
}

function goAbsensi(s) {
  router.push(`/trainer/absensi/${s.id}`)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/sessions')
    sessions.value = unwrap(data)
  } catch (err) {
    errorMessage.value = 'Gagal memuat jadwal. Coba muat ulang.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <!-- Header -->
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
        >Portal Pelatih • Jadwal Mengajar</span
      >
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Jadwal Saya</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Daftar pertemuan yang ditugaskan kepadamu. Jalankan absensi pada sesi hari ini.
      </p>
    </div>

    <!-- Notice read-only -->
    <div
      class="mb-space-lg flex items-start gap-space-sm rounded-2xl border border-secondary-fixed bg-secondary-fixed/40 p-space-md text-on-secondary-container"
    >
      <span class="material-symbols-outlined text-[22px] text-secondary">info</span>
      <p class="font-body-sm text-body-sm">
        Jadwal disusun oleh Management. Kamu tidak dapat mengubah jadwal, tetapi dapat menjalankan
        absensi murid &amp; check-in dirimu pada sesi yang berjalan.
      </p>
    </div>

    <!-- Toggle Daftar / Kalender -->
    <div class="mb-space-md flex items-center">
      <div class="inline-flex rounded-xl bg-surface-container p-1">
        <button
          class="inline-flex items-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="viewMode === 'list' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'"
          @click="viewMode = 'list'"
        >
          <span class="material-symbols-outlined text-[18px]">view_agenda</span> Daftar
        </button>
        <button
          class="inline-flex items-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="viewMode === 'kalender' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'"
          @click="viewMode = 'kalender'"
        >
          <span class="material-symbols-outlined text-[18px]">calendar_month</span> Kalender
        </button>
      </div>
    </div>

    <!-- Tabs -->
    <div v-if="viewMode === 'list'" class="mb-space-lg flex items-center gap-space-xs">
      <button
        v-for="t in tabs"
        :key="t.key"
        class="inline-flex items-center gap-1.5 rounded-full px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
        :class="tab === t.key ? 'bg-primary-container text-on-primary shadow-sm' : 'bg-surface-container-lowest text-on-surface-variant hover:bg-surface-container'"
        @click="tab = t.key"
      >
        {{ t.label }}
        <span
          class="rounded-full px-1.5 text-[11px]"
          :class="tab === t.key ? 'bg-white/20 text-on-primary' : 'bg-surface-container-high text-on-surface-variant'"
          >{{ counts[t.key] }}</span
        >
      </button>
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

    <!-- Skeleton -->
    <div v-if="viewMode === 'list' && loading" class="grid grid-cols-1 gap-space-md">
      <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <!-- Empty -->
    <div
      v-else-if="viewMode === 'list' && !filtered.length"
      class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center"
    >
      <span class="material-symbols-outlined text-[36px] text-outline">event_available</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada pertemuan</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Tidak ada sesi pada kategori ini.</p>
    </div>

    <!-- List -->
    <div v-else-if="viewMode === 'list'" class="flex flex-col gap-space-md">
      <div
        v-for="s in filtered"
        :key="s.id"
        class="relative flex flex-col gap-space-md overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-all hover:shadow-md md:flex-row md:items-center md:justify-between"
      >
        <div
          class="absolute left-0 top-0 h-full w-1.5"
          :class="isOnsite(s.mode) ? 'bg-emerald-500' : 'bg-primary-container'"
        ></div>

        <div class="flex items-center gap-space-md">
          <div
            class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl text-center"
            :class="isOnsite(s.mode) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-secondary-fixed text-on-secondary-container'"
          >
            <span class="material-symbols-outlined text-[22px]">{{ isOnsite(s.mode) ? 'location_on' : 'videocam' }}</span>
            <span class="font-label-sm text-[10px] font-semibold">#{{ s.meeting_no }}</span>
          </div>
          <div class="flex min-w-0 flex-col">
            <div class="flex flex-wrap items-center gap-space-xs">
              <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ s.school?.name || 'Sekolah' }}</h3>
              <span
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
                :class="sessionStatus(s).cls"
              >
                <span class="h-1.5 w-1.5 rounded-full" :class="sessionStatus(s).dot"></span>
                {{ sessionStatus(s).label }}
              </span>
            </div>
            <div class="mt-0.5 flex flex-wrap items-center gap-x-space-md gap-y-0.5 font-body-sm text-body-sm text-on-surface-variant">
              <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">calendar_today</span>
                {{ formatDate(s.date) }}
              </span>
              <span v-if="sessionTime(s)" class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">schedule</span>
                {{ sessionTime(s) }} WIB
              </span>
              <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">menu_book</span>
                {{ s.classroom?.name || s.classroom?.level || 'Level' }}
              </span>
              <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">{{ isOnsite(s.mode) ? 'location_on' : 'videocam' }}</span>
                {{ modeLabel(s.mode) }}
              </span>
            </div>
            <p v-if="s.note" class="mt-1 font-body-sm text-body-sm text-outline">{{ s.note }}</p>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-space-sm md:justify-end">
          <button
            v-if="canAbsen(s)"
            class="inline-flex items-center justify-center gap-space-xs rounded-xl bg-primary px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:bg-primary-container active:scale-95"
            @click="goAbsensi(s)"
          >
            <span class="material-symbols-outlined text-[20px]">folder_open</span>
            <span>Buka</span>
          </button>
          <button
            v-else
            class="inline-flex items-center justify-center gap-space-xs rounded-xl bg-surface-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-surface-variant transition-colors hover:bg-surface-container-high"
            @click="goAbsensi(s)"
          >
            <span class="material-symbols-outlined text-[18px]">visibility</span>
            <span>Lihat</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Kalender -->
    <div v-if="viewMode === 'kalender'" class="flex flex-col gap-space-md">
      <MonthCalendar :sessions="sessions" :selected-date="selectedDate" @day-click="onDayClick" />

      <div v-if="selectedDate" class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <div class="mb-space-sm flex items-center justify-between">
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ formatDate(selectedDate) }}</h3>
          <span class="font-label-sm text-label-sm text-on-surface-variant">{{ sessionsOnSelected.length }} pertemuan</span>
        </div>
        <div v-if="!sessionsOnSelected.length" class="py-space-lg text-center font-body-sm text-body-sm text-on-surface-variant">
          Tidak ada pertemuan pada tanggal ini.
        </div>
        <div v-else class="flex flex-col gap-space-xs">
          <div
            v-for="s in sessionsOnSelected"
            :key="s.id"
            class="flex flex-col gap-space-sm rounded-xl bg-surface-container-low p-space-sm sm:flex-row sm:items-center sm:justify-between"
          >
            <div class="flex items-center gap-space-sm">
              <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                :class="isOnsite(s.mode) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-secondary-fixed text-on-secondary-container'"
              >
                <span class="material-symbols-outlined text-[18px]">{{ isOnsite(s.mode) ? 'location_on' : 'videocam' }}</span>
              </span>
              <div class="flex flex-col">
                <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ s.school?.name }} • #{{ s.meeting_no }}</span>
                <span class="font-body-sm text-body-sm text-on-surface-variant"
                  >{{ s.classroom?.name || s.classroom?.level }} • {{ modeLabel(s.mode) }}<template v-if="sessionTime(s)"> • {{ sessionTime(s) }}</template></span
                >
              </div>
            </div>
            <div class="flex items-center gap-space-xs self-end sm:self-center">
              <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold" :class="sessionStatus(s).cls">
                <span class="h-1.5 w-1.5 rounded-full" :class="sessionStatus(s).dot"></span>
                {{ sessionStatus(s).label }}
              </span>
              <button
                v-if="canAbsen(s)"
                class="inline-flex items-center gap-1 rounded-lg bg-primary px-space-sm py-1.5 font-label-sm text-label-sm font-semibold text-on-primary hover:bg-primary-container"
                @click="goAbsensi(s)"
              >
                <span class="material-symbols-outlined text-[16px]">folder_open</span> Buka
              </button>
              <button
                v-else
                class="rounded-lg p-1.5 text-primary hover:bg-surface-container"
                title="Lihat"
                @click="goAbsensi(s)"
              >
                <span class="material-symbols-outlined text-[18px]">visibility</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <p v-else class="text-center font-body-sm text-body-sm text-outline">
        Klik salah satu tanggal untuk melihat detail pertemuannya.
      </p>
    </div>
  </div>
</template>
