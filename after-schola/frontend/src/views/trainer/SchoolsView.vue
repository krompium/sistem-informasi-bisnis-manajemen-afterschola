<script setup>
// Sekolah (Trainer) — daftar sekolah binaan + rekap absensi murid per level
// (matriks murid × pertemuan) yang bisa diexport ke Excel & PDF.
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatDateShort, initials, unwrap } from '@/lib/format'

const auth = useAuthStore()

const loadingSchools = ref(true)
const schools = ref([])
const selectedSchoolId = ref(null)

const recap = ref(null)
const loadingRecap = ref(false)
const recapError = ref('')
const levelFilter = ref('') // '' = semua level, atau classroom_id
const from = ref('')
const to = ref('')

const exporting = ref('') // 'excel' | 'pdf' | ''
const exportError = ref('')

const selectedSchool = computed(() => schools.value.find((s) => s.id === selectedSchoolId.value))

const visibleGroups = computed(() => {
  if (!recap.value) return []
  if (!levelFilter.value) return recap.value.groups
  return recap.value.groups.filter((g) => String(g.classroom.id) === String(levelFilter.value))
})

const totalStudents = computed(() =>
  (recap.value?.groups || []).reduce((n, g) => n + g.students.length, 0),
)
const totalSessions = computed(() =>
  (recap.value?.groups || []).reduce((n, g) => n + g.sessions.length, 0),
)

function isPresent(group, sessionId, studentId) {
  return !!group.present?.[sessionId]?.[studentId]
}
function studentPresentCount(group, studentId) {
  return group.sessions.reduce((n, s) => n + (isPresent(group, s.id, studentId) ? 1 : 0), 0)
}

async function loadSchools() {
  loadingSchools.value = true
  try {
    const { data } = await api.get('/schools')
    schools.value = unwrap(data)
    if (schools.value.length && !selectedSchoolId.value) {
      selectedSchoolId.value = schools.value[0].id
    }
  } finally {
    loadingSchools.value = false
  }
}

async function loadRecap() {
  if (!selectedSchoolId.value) return
  loadingRecap.value = true
  recapError.value = ''
  try {
    const params = { school_id: selectedSchoolId.value }
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value
    const { data } = await api.get('/exports/attendance/recap', { params })
    recap.value = data
  } catch (err) {
    recapError.value =
      err?.response?.status === 403
        ? 'Anda tidak berwenang melihat rekap sekolah ini.'
        : 'Gagal memuat rekap absensi.'
    recap.value = null
  } finally {
    loadingRecap.value = false
  }
}

async function downloadExport(type) {
  if (!selectedSchoolId.value) return
  exporting.value = type
  exportError.value = ''
  try {
    const params = { school_id: selectedSchoolId.value }
    if (levelFilter.value) params.classroom_id = levelFilter.value
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value
    const url = type === 'excel' ? '/exports/attendance/excel' : '/exports/attendance/pdf'
    const res = await api.get(url, { params, responseType: 'blob' })

    const blob = new Blob([res.data])
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    const cd = res.headers['content-disposition'] || ''
    const m = /filename="?([^"]+)"?/.exec(cd)
    link.download = m ? m[1] : `rekap-absensi.${type === 'excel' ? 'xlsx' : 'pdf'}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    exportError.value =
      err?.response?.status === 403
        ? 'Anda tidak punya izin export.'
        : 'Gagal mengunduh berkas. Coba lagi.'
  } finally {
    exporting.value = ''
  }
}

function selectSchool(id) {
  selectedSchoolId.value = id
  levelFilter.value = ''
}

watch(selectedSchoolId, loadRecap)
watch([from, to], loadRecap)

onMounted(loadSchools)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <!-- Header -->
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
        >{{ auth.isManagement ? 'Modul Absensi • Rekap' : 'Portal Pelatih • Binaan' }}</span
      >
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
        {{ auth.isManagement ? 'Rekap Absensi Sekolah' : 'Sekolah Saya' }}
      </h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        {{
          auth.isManagement
            ? 'Pilih sekolah untuk melihat rekap kehadiran murid per level dan unduh ke Excel / PDF.'
            : 'Lihat rekap kehadiran murid per level dan unduh ke Excel / PDF untuk laporan sekolah.'
        }}
      </p>
    </div>

    <!-- Pemilih sekolah -->
    <div v-if="loadingSchools" class="mb-space-lg grid grid-cols-1 gap-space-md sm:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>
    <div
      v-else-if="!schools.length"
      class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center"
    >
      <span class="material-symbols-outlined text-[36px] text-outline">domain_disabled</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada sekolah</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
        Kamu belum ditugaskan pada sekolah mana pun. Hubungi Management.
      </p>
    </div>
    <div v-else class="mb-space-lg grid grid-cols-1 gap-space-md sm:grid-cols-2 xl:grid-cols-3">
      <button
        v-for="s in schools"
        :key="s.id"
        class="flex items-center gap-space-sm rounded-2xl border-2 p-space-md text-left shadow-sm transition-all"
        :class="s.id === selectedSchoolId ? 'border-primary-container bg-primary-fixed/40' : 'border-transparent bg-surface-container-lowest hover:bg-surface-container-low'"
        @click="selectSchool(s.id)"
      >
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-fixed text-primary">
          <span class="material-symbols-outlined text-[24px]">school</span>
        </span>
        <span class="flex min-w-0 flex-col">
          <span class="truncate font-headline-sm text-headline-sm font-bold text-on-surface">{{ s.name }}</span>
          <span class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ s.address || 'Sekolah binaan' }}</span>
        </span>
        <span v-if="s.id === selectedSchoolId" class="material-symbols-outlined ml-auto text-[20px] text-primary">check_circle</span>
      </button>
    </div>

    <!-- Rekap -->
    <template v-if="selectedSchool">
      <!-- Toolbar: filter + export -->
      <div class="mb-space-md flex flex-col gap-space-md rounded-2xl bg-surface-container-lowest p-space-md shadow-sm lg:flex-row lg:items-end lg:justify-between">
        <div class="flex flex-col gap-space-sm sm:flex-row sm:items-end">
          <label class="flex flex-col gap-1">
            <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Level</span>
            <select
              v-model="levelFilter"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
              <option value="">Semua level</option>
              <option v-for="g in recap?.groups || []" :key="g.classroom.id" :value="g.classroom.id">
                {{ g.classroom.name }}
              </option>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Dari</span>
            <input v-model="from" type="date" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Sampai</span>
            <input v-model="to" type="date" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
        </div>
        <div class="flex items-center gap-space-sm">
          <button
            class="inline-flex items-center gap-space-xs rounded-xl bg-[#065F46] px-space-md py-2.5 font-label-lg text-label-lg text-white shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            :disabled="exporting === 'excel' || !totalSessions"
            @click="downloadExport('excel')"
          >
            <span class="material-symbols-outlined text-[18px]" :class="exporting === 'excel' ? 'animate-spin' : ''">{{ exporting === 'excel' ? 'progress_activity' : 'table_view' }}</span>
            Excel
          </button>
          <button
            class="inline-flex items-center gap-space-xs rounded-xl bg-error px-space-md py-2.5 font-label-lg text-label-lg text-on-error shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            :disabled="exporting === 'pdf' || !totalSessions"
            @click="downloadExport('pdf')"
          >
            <span class="material-symbols-outlined text-[18px]" :class="exporting === 'pdf' ? 'animate-spin' : ''">{{ exporting === 'pdf' ? 'progress_activity' : 'picture_as_pdf' }}</span>
            PDF
          </button>
        </div>
      </div>

      <p v-if="exportError" class="mb-space-sm font-body-sm text-body-sm text-error">{{ exportError }}</p>

      <!-- Ringkasan -->
      <div class="mb-space-md flex flex-wrap items-center gap-space-md font-body-sm text-body-sm text-on-surface-variant">
        <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">groups</span>{{ totalStudents }} murid</span>
        <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">event</span>{{ totalSessions }} pertemuan</span>
        <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">date_range</span>{{ recap?.period || '—' }}</span>
      </div>

      <!-- Loading / error -->
      <div v-if="loadingRecap" class="h-64 animate-pulse rounded-2xl bg-surface-container"></div>
      <div
        v-else-if="recapError"
        class="flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-md text-on-error-container"
      >
        <span class="material-symbols-outlined">error</span>
        <span class="font-body-md text-body-md">{{ recapError }}</span>
        <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadRecap">Coba lagi</button>
      </div>

      <!-- Tabel rekap per level -->
      <template v-else>
        <div
          v-for="g in visibleGroups"
          :key="g.classroom.id"
          class="mb-space-lg rounded-2xl bg-surface-container-lowest p-space-md shadow-sm"
        >
          <div class="mb-space-sm flex items-center justify-between">
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Level: {{ g.classroom.name }}</h2>
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ g.students.length }} murid • {{ g.sessions.length }} pertemuan</span>
          </div>

          <div v-if="!g.sessions.length" class="py-space-lg text-center font-body-sm text-body-sm text-on-surface-variant">
            Belum ada pertemuan pada periode ini.
          </div>
          <div v-else-if="!g.students.length" class="py-space-lg text-center font-body-sm text-body-sm text-on-surface-variant">
            Belum ada murid pada level ini.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="w-full border-collapse text-left">
              <thead>
                <tr class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface">
                  <th class="sticky left-0 z-10 bg-surface-container-low px-space-sm py-space-xs">Nama</th>
                  <th class="bg-surface-container-low px-space-xs py-space-xs text-center">Kelas</th>
                  <th
                    v-for="s in g.sessions"
                    :key="s.id"
                    class="bg-surface-container-low px-space-xs py-space-xs text-center"
                    :title="formatDateShort(s.date)"
                  >
                    P{{ s.meeting_no }}<br /><span class="font-body-sm text-[10px] font-normal text-outline">{{ formatDateShort(s.date).replace(/ \d{4}$/, '') }}</span>
                  </th>
                  <th class="bg-surface-container-low px-space-xs py-space-xs text-center">Σ</th>
                </tr>
              </thead>
              <tbody class="font-body-md text-body-md">
                <tr v-for="(st, i) in g.students" :key="st.id" class="border-b border-surface-container hover:bg-surface-container-low">
                  <td class="sticky left-0 z-10 bg-surface-container-lowest px-space-sm py-space-xs">
                    <div class="flex items-center gap-space-xs">
                      <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-container-high text-[10px] font-bold text-primary">{{ initials(st.name) }}</span>
                      <span class="whitespace-nowrap">{{ i + 1 }}. {{ st.name }}</span>
                    </div>
                  </td>
                  <td class="px-space-xs py-space-xs text-center text-on-surface-variant">{{ st.origin_class || '—' }}</td>
                  <td v-for="s in g.sessions" :key="s.id" class="px-space-xs py-space-xs text-center">
                    <span
                      class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[13px] font-bold"
                      :class="isPresent(g, s.id, st.id) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-outline'"
                    >{{ isPresent(g, s.id, st.id) ? 'H' : '–' }}</span>
                  </td>
                  <td class="px-space-xs py-space-xs text-center font-semibold text-primary">{{ studentPresentCount(g, st.id) }}</td>
                </tr>
                <!-- Baris jumlah hadir -->
                <tr class="bg-surface-container-low font-label-md text-label-md font-bold text-on-surface">
                  <td class="sticky left-0 z-10 bg-surface-container-low px-space-sm py-space-xs">Jumlah Hadir</td>
                  <td class="px-space-xs py-space-xs"></td>
                  <td v-for="s in g.sessions" :key="s.id" class="px-space-xs py-space-xs text-center text-primary">{{ g.present_totals[s.id] || 0 }}</td>
                  <td class="px-space-xs py-space-xs"></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="!visibleGroups.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
          <span class="material-symbols-outlined text-[36px] text-outline">summarize</span>
          <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada data rekap</p>
          <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Belum ada level/pertemuan untuk sekolah ini.</p>
        </div>
      </template>
    </template>
  </div>
</template>
