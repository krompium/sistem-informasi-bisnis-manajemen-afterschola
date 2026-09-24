<script setup>
// Dashboard Sekolah (role: sekolah) — read-only, semua data otomatis discope
// ke sekolah milik akun yang login (backend baca dari token, bukan dari parameter).
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { initials, unwrap } from '@/lib/format'

const loading = ref(true)
const summary = ref(null)
const students = ref([])
const schedule = ref([])

const view = ref('students') // 'students' | 'schedule'
const selectedStudent = ref(null)
const loadingDetail = ref(false)
const detailError = ref('')

const exporting = ref(false)
const exportError = ref('')

const studentsByLevel = computed(() => {
  const groups = {}
  for (const st of students.value) {
    const key = st.classroom?.name || 'Tanpa Mata Pelajaran'
    ;(groups[key] ||= []).push(st)
  }
  return Object.entries(groups).map(([level, list]) => ({ level, list }))
})

async function loadAll() {
  loading.value = true
  try {
    const [{ data: sum }, { data: st }, { data: sc }] = await Promise.all([
      api.get('/my-school/summary'),
      api.get('/my-school/students'),
      api.get('/my-school/schedule'),
    ])
    summary.value = sum
    students.value = unwrap(st)
    schedule.value = unwrap(sc)
  } finally {
    loading.value = false
  }
}

async function openStudent(student) {
  selectedStudent.value = null
  detailError.value = ''
  loadingDetail.value = true
  try {
    const { data } = await api.get(`/my-school/students/${student.id}`)
    selectedStudent.value = data
  } catch (e) {
    detailError.value = 'Gagal memuat detail murid.'
  } finally {
    loadingDetail.value = false
  }
}

function closeStudent() {
  selectedStudent.value = null
}

async function doExport() {
  exporting.value = true
  exportError.value = ''
  try {
    const response = await api.get('/my-school/export', { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'rekap-absensi.pdf')
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    exportError.value = 'Gagal mengekspor PDF.'
  } finally {
    exporting.value = false
  }
}

onMounted(loadAll)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Dashboard Sekolah</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          {{ summary ? 'Ringkasan Sekolah' : 'Memuat…' }}
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Data murid, kehadiran, nilai, dan jadwal — khusus sekolah Anda.
        </p>
      </div>
      <button
        :disabled="exporting"
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60 lg:self-center"
        @click="doExport"
      >
        <span v-if="exporting" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
        <span v-else class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
        <span>Export PDF</span>
      </button>
    </div>
    <div v-if="exportError" class="mb-space-md flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
      <span class="material-symbols-outlined text-[18px]">error</span>{{ exportError }}
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md sm:grid-cols-2">
      <div class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
      <div class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else>
      <!-- Ringkasan -->
      <div class="mb-space-lg grid grid-cols-1 gap-space-md sm:grid-cols-2">
        <div class="flex items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-fixed text-primary">
            <span class="material-symbols-outlined text-[24px]">groups</span>
          </span>
          <div>
            <p class="font-headline-md text-headline-md font-bold text-on-surface">{{ summary?.students_count ?? 0 }}</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Total Murid</p>
          </div>
        </div>
        <div class="flex items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-fixed text-primary">
            <span class="material-symbols-outlined text-[24px]">event_upcoming</span>
          </span>
          <div>
            <p class="font-headline-md text-headline-md font-bold text-on-surface">{{ summary?.upcoming_sessions_count ?? 0 }}</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Pertemuan Mendatang</p>
          </div>
        </div>
      </div>

      <!-- Tab -->
      <div class="mb-space-md flex gap-space-xs">
        <button
          class="rounded-xl px-space-md py-2 font-label-md text-label-md transition-all"
          :class="view === 'students' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'"
          @click="view = 'students'"
        >
          Daftar Murid
        </button>
        <button
          class="rounded-xl px-space-md py-2 font-label-md text-label-md transition-all"
          :class="view === 'schedule' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'"
          @click="view = 'schedule'"
        >
          Jadwal
        </button>
      </div>

      <!-- Daftar Murid -->
      <div v-if="view === 'students'" class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div v-if="!students.length" class="py-space-xl text-center font-body-sm text-body-sm text-on-surface-variant">
          Belum ada murid terdaftar.
        </div>
        <div v-else class="flex flex-col gap-space-lg">
          <div v-for="g in studentsByLevel" :key="g.level">
            <div class="mb-space-xs flex items-center gap-space-xs">
              <span class="rounded-md bg-primary-fixed px-2 py-0.5 font-label-sm text-label-sm font-semibold text-primary">{{ g.level }}</span>
              <span class="font-label-sm text-label-sm text-on-surface-variant">{{ g.list.length }} murid</span>
            </div>
            <div class="overflow-hidden rounded-xl border border-surface-container">
              <button
                v-for="(st, i) in g.list"
                :key="st.id"
                class="flex w-full items-center gap-space-sm border-b border-surface-container px-space-sm py-space-xs text-left last:border-0 hover:bg-surface-container-low"
                @click="openStudent(st)"
              >
                <span class="w-6 text-center font-label-sm text-label-sm text-outline">{{ i + 1 }}</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-surface-container-high text-[10px] font-bold text-primary">{{ initials(st.name) }}</span>
                <span class="font-body-md text-body-md text-on-surface">{{ st.name }}</span>
                <span v-if="st.origin_class" class="ml-auto rounded bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ st.origin_class }}</span>
                <span class="material-symbols-outlined text-[18px] text-outline">chevron_right</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Jadwal -->
      <div v-else class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div v-if="!schedule.length" class="py-space-xl text-center font-body-sm text-body-sm text-on-surface-variant">
          Belum ada jadwal pertemuan tercatat.
        </div>
        <div v-else class="overflow-hidden rounded-xl border border-surface-container">
          <div
            v-for="s in schedule"
            :key="s.id"
            class="flex flex-wrap items-center gap-space-sm border-b border-surface-container px-space-sm py-space-sm last:border-0"
          >
            <span class="rounded-md bg-primary-fixed px-2 py-0.5 font-label-sm text-label-sm font-semibold text-primary">
              {{ s.classroom?.name }}
            </span>
            <span class="font-body-md text-body-md text-on-surface">{{ s.date }}</span>
            <span v-if="s.start_time" class="font-body-sm text-body-sm text-on-surface-variant">{{ s.start_time }}–{{ s.end_time }}</span>
            <span class="rounded bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ s.mode }}</span>
            <span class="ml-auto font-label-sm text-label-sm text-outline">Pertemuan ke-{{ s.meeting_no }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Modal detail murid -->
    <div
      v-if="loadingDetail || selectedStudent"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="closeStudent"
    >
      <div class="flex max-h-[88vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">
            {{ selectedStudent?.student?.data?.name ?? selectedStudent?.student?.name ?? 'Detail Murid' }}
          </h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="closeStudent">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-space-lg">
          <div v-if="loadingDetail" class="space-y-space-sm">
            <div v-for="i in 4" :key="i" class="h-10 animate-pulse rounded-lg bg-surface-container"></div>
          </div>
          <div v-else-if="detailError" class="rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            {{ detailError }}
          </div>
          <template v-else-if="selectedStudent">
            <div class="mb-space-lg">
              <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">Riwayat Kehadiran</h3>
              <div v-if="!selectedStudent.attendances?.data?.length && !selectedStudent.attendances?.length" class="rounded-xl bg-surface-container px-space-sm py-space-sm text-center font-body-sm text-body-sm text-on-surface-variant">
                Belum ada catatan kehadiran.
              </div>
              <div v-else class="flex flex-col gap-space-2xs">
                <div
                  v-for="att in (selectedStudent.attendances.data ?? selectedStudent.attendances)"
                  :key="att.id"
                  class="flex items-center justify-between rounded-lg border border-surface-container px-space-sm py-space-xs"
                >
                  <span class="font-body-sm text-body-sm text-on-surface">
                    Pertemuan ke-{{ att.class_session?.meeting_no ?? '-' }} — {{ att.class_session?.date ?? '' }}
                  </span>
                  <span
                    class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
                    :class="att.is_present ? 'bg-primary-fixed text-primary' : 'bg-error-container text-on-error-container'"
                  >
                    {{ att.is_present ? 'Hadir' : 'Tidak Hadir' }}
                  </span>
                </div>
              </div>
            </div>

            <div>
              <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">Nilai</h3>
              <div v-if="!selectedStudent.grades?.data?.length && !selectedStudent.grades?.length" class="rounded-xl bg-surface-container px-space-sm py-space-sm text-center font-body-sm text-body-sm text-on-surface-variant">
                Belum ada nilai tercatat.
              </div>
              <div v-else class="flex flex-col gap-space-2xs">
                <div
                  v-for="g in (selectedStudent.grades.data ?? selectedStudent.grades)"
                  :key="g.id"
                  class="flex items-center justify-between rounded-lg border border-surface-container px-space-sm py-space-xs"
                >
                  <span class="font-body-sm text-body-sm text-on-surface">{{ g.period }}</span>
                  <span class="font-label-md text-label-md font-bold text-primary">{{ g.score }}</span>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>