<script setup>
// Rekap Absensi → detail satu trainer: tabel sesi
// (tanggal, jam, sekolah, pertemuan, mode, check-in trainer, dokumentasi).
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { formatDate, formatStamp, isOnsite, modeLabel, unwrap } from '@/lib/format'
import { normalizeSession } from '@/lib/sessionRecap'

const route = useRoute()
const trainerId = String(route.params.id)

const loading = ref(true)
const errorMessage = ref('')
const trainer = ref(null)
const sessions = ref([])

// Kalau daftar /sessions tidak membawa trainer_attendances, ambil dari /sessions/:id per sesi.
const details = reactive({}) // id sesi → { docs, checkin }
const loadingDetails = ref(false)

const filters = reactive({ month: '', school: '', mode: '' }) // mode: '' | 'onsite' | 'online'
const failedImg = reactive({}) // id sesi → thumbnail gagal dimuat
const lightbox = ref(null) // { urls: [], i: 0 }

const statusMeta = {
  hadir: { label: 'Hadir', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  terlambat: { label: 'Terlambat', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  tidak_hadir: { label: 'Tidak Hadir', cls: 'bg-error-container text-error' },
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [usersRes, sessionsRes] = await Promise.all([api.get('/users'), api.get('/sessions')])
    trainer.value = unwrap(usersRes.data).find((u) => String(u.id) === trainerId) ?? null
    sessions.value = unwrap(sessionsRes.data)
      .map(normalizeSession)
      .filter((s) => String(s.trainerId) === trainerId)
  } catch (e) {
    errorMessage.value = 'Gagal memuat data trainer.'
    return
  } finally {
    loading.value = false
  }
  loadMissingDetails() // tidak di-await: tabel tampil dulu, foto menyusul
}

async function loadMissingDetails() {
  const queue = sessions.value.filter((s) => !s.hasCheckinData && !(s.id in details))
  if (!queue.length) return
  loadingDetails.value = true
  const worker = async () => {
    while (queue.length) {
      const s = queue.shift()
      try {
        const { data } = await api.get(`/sessions/${s.id}`)
        const n = normalizeSession(data.data ?? data)
        details[s.id] = { docs: n.docs, checkin: n.checkin }
      } catch (e) {
        details[s.id] = { docs: [], checkin: null }
      }
    }
  }
  await Promise.all(Array.from({ length: 5 }, worker)) // 5 permintaan paralel
  loadingDetails.value = false
}

const enrich = (s) => (details[s.id] ? { ...s, ...details[s.id] } : s)
const isPending = (r) => !r.hasCheckinData && !(r.id in details)

const schoolOptions = computed(() => [...new Set(sessions.value.map((s) => s.school).filter(Boolean))].sort())

const rows = computed(() =>
  sessions.value
    .map(enrich)
    .filter((s) => !filters.month || s.monthKey === filters.month)
    .filter((s) => !filters.school || s.school === filters.school)
    .filter((s) => !filters.mode || (filters.mode === 'onsite') === isOnsite(s.mode))
    .sort((a, b) => b.date.localeCompare(a.date) || (b.meetingNo ?? 0) - (a.meetingNo ?? 0)),
)

const summary = computed(() => {
  const onsite = rows.value.filter((s) => isOnsite(s.mode)).length
  return {
    total: rows.value.length,
    onsite,
    online: rows.value.length - onsite,
    schools: new Set(rows.value.map((s) => s.school).filter(Boolean)).size,
  }
})

const hasFilter = computed(() => filters.month || filters.school || filters.mode)
function resetFilters() {
  Object.assign(filters, { month: '', school: '', mode: '' })
}

function openLightbox(urls, i = 0) {
  lightbox.value = { urls, i }
}
function stepLightbox(d) {
  const n = lightbox.value.urls.length
  lightbox.value.i = (lightbox.value.i + d + n) % n
}

// Unduh CSV (bisa dibuka di Excel). BOM supaya karakter Indonesia tidak rusak.
function exportCsv() {
  const esc = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const head = ['Tanggal', 'Jam', 'Sekolah', 'Mata Pelajaran', 'Pertemuan', 'Mode', 'Check-in', 'Dokumentasi']
  const lines = rows.value.map((r) =>
    [
      r.date,
      r.time,
      r.school,
      r.classroom,
      r.meetingNo ?? '',
      modeLabel(r.mode),
      r.checkin ? (statusMeta[r.checkin.status]?.label ?? r.checkin.status) : 'Belum check-in',
      r.docs.length ? `${r.docs.length} file` : '-',
    ]
      .map(esc)
      .join(','),
  )
  const blob = new Blob(['\ufeff' + [head.map(esc).join(','), ...lines].join('\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `rekap-trainer-${(trainer.value?.name || trainerId).replace(/\s+/g, '-').toLowerCase()}.csv`
  a.click()
  URL.revokeObjectURL(a.href)
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl pt-space-lg">
    <RouterLink
      to="/management/absensi?tab=trainer"
      class="mb-space-sm inline-flex w-fit items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline"
    >
      <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali ke daftar trainer
    </RouterLink>

    <div class="mb-space-lg flex flex-col justify-between gap-space-md lg:flex-row lg:items-end">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Rekap Absensi Trainer</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          {{ trainer?.name || 'Trainer' }}
        </h1>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-surface-container px-space-lg py-2.5 font-label-lg text-label-lg text-primary shadow-sm transition-all hover:bg-surface-container-high disabled:opacity-50 lg:self-auto"
        :disabled="!rows.length"
        @click="exportCsv"
      >
        <span class="material-symbols-outlined text-[18px]">download</span>
        <span>Unduh CSV</span>
      </button>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-if="loading" class="space-y-space-md">
      <div class="grid grid-cols-2 gap-space-md lg:grid-cols-4">
        <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
      </div>
      <div class="h-72 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else-if="!errorMessage">
      <!-- Ringkasan -->
      <div class="mb-space-lg grid grid-cols-2 gap-space-md lg:grid-cols-4">
        <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
          <p class="text-[13px] font-semibold uppercase tracking-wider text-outline">Total Sesi</p>
          <p class="mt-1 text-[32px] font-extrabold leading-none text-on-surface">{{ summary.total }}</p>
        </div>
        <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
          <p class="text-[13px] font-semibold uppercase tracking-wider text-outline">Onsite</p>
          <p class="mt-1 text-[32px] font-extrabold leading-none text-on-surface">{{ summary.onsite }}</p>
        </div>
        <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
          <p class="text-[13px] font-semibold uppercase tracking-wider text-outline">Online</p>
          <p class="mt-1 text-[32px] font-extrabold leading-none text-on-surface">{{ summary.online }}</p>
        </div>
        <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
          <p class="text-[13px] font-semibold uppercase tracking-wider text-outline">Sekolah</p>
          <p class="mt-1 text-[32px] font-extrabold leading-none text-on-surface">{{ summary.schools }}</p>
        </div>
      </div>

      <!-- Filter -->
      <div class="mb-space-md flex flex-wrap items-center gap-space-sm">
        <input
          v-model="filters.month"
          type="month"
          class="rounded-xl bg-surface-container-lowest px-space-sm py-2 font-body-md text-body-md text-on-surface shadow-sm focus:outline-none focus:ring-2 focus:ring-primary"
        />
        <select
          v-model="filters.school"
          class="rounded-xl bg-surface-container-lowest px-space-sm py-2 font-body-md text-body-md text-on-surface shadow-sm focus:outline-none focus:ring-2 focus:ring-primary"
        >
          <option value="">Semua sekolah</option>
          <option v-for="s in schoolOptions" :key="s" :value="s">{{ s }}</option>
        </select>
        <div class="inline-flex rounded-xl bg-surface-container-low p-1">
          <button
            v-for="m in [{ k: '', l: 'Semua' }, { k: 'onsite', l: 'Onsite' }, { k: 'online', l: 'Online' }]"
            :key="m.k"
            type="button"
            class="rounded-lg px-space-md py-1.5 font-label-md text-label-md font-semibold transition-all"
            :class="filters.mode === m.k ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'"
            @click="filters.mode = m.k"
          >
            {{ m.l }}
          </button>
        </div>
        <button v-if="hasFilter" class="font-label-md text-label-md font-semibold text-primary hover:underline" @click="resetFilters">Reset filter</button>
        <span v-if="loadingDetails" class="ml-auto inline-flex items-center gap-1 font-label-sm text-label-sm text-outline">
          <span class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
          Memuat check-in & dokumentasi…
        </span>
      </div>

      <!-- Tabel -->
      <div v-if="!rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
        <span class="material-symbols-outlined text-[36px] text-outline">event_busy</span>
        <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">
          {{ hasFilter ? 'Tidak ada sesi sesuai filter' : 'Belum ada sesi untuk trainer ini' }}
        </p>
      </div>

      <div v-else class="overflow-x-auto rounded-2xl bg-surface-container-lowest shadow-sm">
        <table class="w-full min-w-[980px] text-left">
          <thead>
            <tr class="border-b border-surface-container text-[12px] font-semibold uppercase tracking-wider text-outline">
              <th class="px-space-md py-space-sm">Tanggal</th>
              <th class="px-space-md py-space-sm">Jam</th>
              <th class="px-space-md py-space-sm">Sekolah</th>
              <th class="px-space-md py-space-sm">Pertemuan</th>
              <th class="px-space-md py-space-sm">Mode</th>
              <th class="px-space-md py-space-sm">Check-in</th>
              <th class="px-space-md py-space-sm">Dokumentasi</th>
              <th class="px-space-md py-space-sm"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" class="border-b border-surface-container last:border-0 hover:bg-surface-container-low">
              <td class="whitespace-nowrap px-space-md py-space-sm font-body-md text-body-md text-on-surface">
                {{ formatDate(r.date, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}
              </td>
              <td class="whitespace-nowrap px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant">{{ r.time || '—' }}</td>
              <td class="px-space-md py-space-sm">
                <p class="font-body-md font-semibold text-on-surface">{{ r.school || '—' }}</p>
                <p v-if="r.classroom" class="font-body-sm text-body-sm text-on-surface-variant">{{ r.classroom }}</p>
              </td>
              <td class="whitespace-nowrap px-space-md py-space-sm font-body-md text-body-md text-on-surface">
                {{ r.meetingNo != null ? `Pertemuan ${r.meetingNo}` : '—' }}
              </td>
              <td class="px-space-md py-space-sm">
                <span
                  class="inline-flex rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold"
                  :class="isOnsite(r.mode) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-secondary-fixed text-on-secondary-container'"
                  >{{ modeLabel(r.mode) }}</span
                >
              </td>
              <!-- Check-in trainer -->
              <td class="px-space-md py-space-sm">
                <span v-if="isPending(r)" class="inline-block h-5 w-16 animate-pulse rounded-full bg-surface-container"></span>
                <template v-else-if="r.checkin">
                  <span
                    class="inline-flex rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold"
                    :class="statusMeta[r.checkin.status]?.cls || 'bg-surface-container text-on-surface-variant'"
                    >{{ statusMeta[r.checkin.status]?.label || r.checkin.status }}</span
                  >
                  <p v-if="r.checkin.at" class="mt-0.5 font-label-sm text-label-sm text-outline">{{ formatStamp(r.checkin.at) }}</p>
                </template>
                <span v-else class="font-body-sm text-body-sm text-outline">Belum check-in</span>
              </td>
              <!-- Dokumentasi -->
              <td class="px-space-md py-space-sm">
                <span v-if="isPending(r)" class="inline-block h-11 w-11 animate-pulse rounded-lg bg-surface-container"></span>
                <button
                  v-else-if="r.docs.length"
                  type="button"
                  class="relative flex h-11 w-11 items-center justify-center overflow-hidden rounded-lg bg-surface-container"
                  :title="`Lihat dokumentasi (${r.docs.length})`"
                  @click="openLightbox(r.docs)"
                >
                  <img v-if="!failedImg[r.id]" :src="r.docs[0]" alt="" class="h-full w-full object-cover" @error="failedImg[r.id] = true" />
                  <span v-else class="material-symbols-outlined text-[20px] text-primary">image</span>
                  <span v-if="r.docs.length > 1" class="absolute bottom-0 right-0 rounded-tl-md bg-black/60 px-1 text-[10px] font-bold text-white">+{{ r.docs.length - 1 }}</span>
                </button>
                <span v-else class="font-body-sm text-body-sm text-outline">Belum ada</span>
              </td>
              <td class="px-space-md py-space-sm text-right">
                <RouterLink
                  :to="`/management/absensi/${r.id}`"
                  class="inline-flex items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline"
                >
                  Detail <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Lightbox dokumentasi -->
    <div
      v-if="lightbox"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="lightbox = null"
    >
      <button class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="lightbox = null">
        <span class="material-symbols-outlined">close</span>
      </button>
      <button v-if="lightbox.urls.length > 1" class="absolute left-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="stepLightbox(-1)">
        <span class="material-symbols-outlined">chevron_left</span>
      </button>
      <img :src="lightbox.urls[lightbox.i]" alt="Dokumentasi sesi" class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl" />
      <a
        :href="lightbox.urls[lightbox.i]"
        target="_blank"
        rel="noopener"
        class="absolute bottom-14 max-w-[90vw] truncate rounded-full bg-black/60 px-3 py-1 text-[12px] text-white underline"
        title="Buka gambar di tab baru"
        >{{ lightbox.urls[lightbox.i] }}</a
      >
      <button v-if="lightbox.urls.length > 1" class="absolute right-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20" @click="stepLightbox(1)">
        <span class="material-symbols-outlined">chevron_right</span>
      </button>
      <span v-if="lightbox.urls.length > 1" class="absolute bottom-4 rounded-full bg-black/60 px-3 py-1 text-[13px] text-white">{{ lightbox.i + 1 }} / {{ lightbox.urls.length }}</span>
    </div>
  </div>
</template>