<script setup>
// Rekap Absensi (Management) — langkah 1: daftar sekolah + import absensi dari Excel.
// Klik sekolah → halaman detail rekap sekolah tsb.
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { unwrap } from '@/lib/format'

const router = useRouter()
const loading = ref(true)
const errorMessage = ref('')
const schools = ref([])
const search = ref('')

// ---- Import Absensi dari Excel ----
const showImport = ref(false)
const importStep = ref('form') // 'form' | 'preview' | 'done'
const trainers = ref([])
const classroomsForSchool = ref([])
const importForm = reactive({
  school_id: '',
  classroom_id: '',
  trainer_id: '',
  mode: 'onsite',
  start_meeting_no: 1,
  file: null,
})
const importError = ref('')
const previewing = ref(false)
const committing = ref(false)
const previewResult = ref(null) // { token, meetings, skipped_columns, matched_count, unmatched_names }
const commitResult = ref(null) // { created_sessions, created_students, updated_attendance }
const createMissingStudents = ref(true)

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

// ---- Import ----
function openImport() {
  Object.assign(importForm, {
    school_id: '',
    classroom_id: '',
    trainer_id: '',
    mode: 'onsite',
    start_meeting_no: 1,
    file: null,
  })
  classroomsForSchool.value = []
  importError.value = ''
  previewResult.value = null
  commitResult.value = null
  importStep.value = 'form'
  showImport.value = true
  loadTrainersOnce()
}

let trainersLoaded = false
async function loadTrainersOnce() {
  if (trainersLoaded) return
  try {
    const { data } = await api.get('/users')
    trainers.value = unwrap(data).filter((u) => (u.roles ?? []).includes('trainer'))
    trainersLoaded = true
  } catch (e) {
    trainers.value = []
  }
}

async function onImportSchoolChange() {
  importForm.classroom_id = ''
  classroomsForSchool.value = []
  if (!importForm.school_id) return
  try {
    const { data } = await api.get('/classrooms', { params: { school_id: importForm.school_id } })
    classroomsForSchool.value = unwrap(data).filter((c) => c.is_active !== false)
  } catch (e) {
    classroomsForSchool.value = []
  }
}

function onFileChange(e) {
  importForm.file = e.target.files?.[0] ?? null
}

async function submitPreview() {
  importError.value = ''
  if (!importForm.file) {
    importError.value = 'Pilih file Excel/CSV dulu.'
    return
  }

  previewing.value = true
  try {
    const formData = new FormData()
    formData.append('school_id', importForm.school_id)
    formData.append('classroom_id', importForm.classroom_id)
    formData.append('start_meeting_no', importForm.start_meeting_no)
    formData.append('file', importForm.file)

    const { data } = await api.post('/attendance-imports/preview', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    previewResult.value = data
    importStep.value = 'preview'
  } catch (err) {
    const res = err?.response
    importError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'File tidak valid.'
        : 'Gagal membaca file. Coba lagi.'
  } finally {
    previewing.value = false
  }
}

async function submitCommit() {
  if (!previewResult.value?.token) return
  committing.value = true
  importError.value = ''
  try {
    const { data } = await api.post(`/attendance-imports/${previewResult.value.token}/commit`, {
      trainer_id: importForm.trainer_id,
      mode: importForm.mode,
      create_missing_students: createMissingStudents.value,
    })
    commitResult.value = data
    importStep.value = 'done'
  } catch (err) {
    const res = err?.response
    importError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Gagal menyimpan.'
        : 'Gagal menyimpan hasil import.'
  } finally {
    committing.value = false
  }
}

function backToForm() {
  importStep.value = 'form'
  previewResult.value = null
}

function finishImport() {
  showImport.value = false
  load() // refresh jumlah murid/sekolah kalau perlu
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
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
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openImport"
      >
        <span class="material-symbols-outlined text-[18px]">upload_file</span>
        <span>Import Absensi dari Excel</span>
      </button>
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

    <!-- ===================== MODAL IMPORT ABSENSI ===================== -->
    <div
      v-if="showImport"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showImport = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Import Absensi dari Excel</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
              Kolom 1 = Nama, kolom 2 = Kelas (opsional), kolom berikutnya = tiap pertemuan (header wajib mengandung tanggal, mis. 05/09/2026).
            </p>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showImport = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-space-lg">
          <div v-if="importError" class="mb-space-md flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ importError }}
          </div>

          <!-- Langkah 1: form -->
          <form v-if="importStep === 'form'" class="flex flex-col gap-space-md" @submit.prevent="submitPreview">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sekolah</span>
              <select
                v-model="importForm.school_id"
                required
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
                @change="onImportSchoolChange"
              >
                <option value="" disabled>Pilih sekolah…</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mata Pelajaran</span>
              <select
                v-model="importForm.classroom_id"
                required
                :disabled="!importForm.school_id"
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15 disabled:bg-surface-container-low disabled:text-outline"
              >
                <option value="" disabled>{{ importForm.school_id ? 'Pilih mata pelajaran…' : 'Pilih sekolah dulu' }}</option>
                <option v-for="c in classroomsForSchool" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Trainer Pengampu</span>
              <select
                v-model="importForm.trainer_id"
                required
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              >
                <option value="" disabled>Pilih trainer…</option>
                <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <span class="font-body-sm text-body-sm text-outline">Dipakai kalau import ini bikin pertemuan baru yang belum ada di sistem.</span>
            </label>

            <div class="grid grid-cols-2 gap-space-md">
              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mode Sesi</span>
                <div class="inline-flex rounded-xl bg-surface-container-low p-1">
                  <button type="button" class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all" :class="importForm.mode === 'onsite' ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'" @click="importForm.mode = 'onsite'">
                    Onsite
                  </button>
                  <button type="button" class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all" :class="importForm.mode === 'online' ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'" @click="importForm.mode = 'online'">
                    Online
                  </button>
                </div>
              </div>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nomor Pertemuan Awal</span>
                <input
                  v-model.number="importForm.start_meeting_no"
                  type="number"
                  min="1"
                  class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
                />
              </label>
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">File Excel/CSV</span>
              <input
                type="file"
                accept=".xlsx,.csv,.txt"
                required
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface file:mr-space-sm file:rounded-md file:border-0 file:bg-primary-fixed file:px-space-sm file:py-1.5 file:font-label-sm file:text-label-sm file:font-semibold file:text-primary"
                @change="onFileChange"
              />
            </label>

            <div class="flex justify-end pt-space-xs">
              <button
                type="submit"
                :disabled="previewing"
                class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
              >
                <span v-if="previewing" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                Pratinjau
              </button>
            </div>
          </form>

          <!-- Langkah 2: preview -->
          <div v-else-if="importStep === 'preview'" class="flex flex-col gap-space-md">
            <div class="grid grid-cols-2 gap-space-sm">
              <div class="rounded-xl bg-surface-container p-space-sm text-center">
                <p class="font-headline-sm text-headline-sm font-bold text-primary">{{ previewResult.meetings.length }}</p>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Pertemuan terdeteksi</p>
              </div>
              <div class="rounded-xl bg-surface-container p-space-sm text-center">
                <p class="font-headline-sm text-headline-sm font-bold text-primary">{{ previewResult.matched_count }}</p>
                <p class="font-label-sm text-label-sm text-on-surface-variant">Murid cocok otomatis</p>
              </div>
            </div>

            <div>
              <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">Pertemuan</h3>
              <div class="overflow-hidden rounded-xl border border-surface-container">
                <div
                  v-for="m in previewResult.meetings"
                  :key="m.column"
                  class="flex items-center justify-between border-b border-surface-container px-space-sm py-1.5 last:border-0"
                >
                  <span class="font-body-sm text-body-sm text-on-surface">P{{ m.meeting_no }} — {{ m.date }}</span>
                  <span
                    class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
                    :class="m.existing_session_id ? 'bg-surface-container text-on-surface-variant' : 'bg-[#D1FAE5] text-[#065F46]'"
                  >{{ m.existing_session_id ? 'Sudah ada' : 'Baru' }}</span>
                </div>
              </div>
            </div>

            <div v-if="previewResult.skipped_columns?.length">
              <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-error">Kolom dilewati (tanggal tidak terbaca)</h3>
              <div class="rounded-xl bg-error-container p-space-sm">
                <p v-for="sc in previewResult.skipped_columns" :key="sc.column" class="font-body-sm text-body-sm text-on-error-container">
                  Kolom {{ sc.column }} ("{{ sc.header }}") — {{ sc.reason }}
                </p>
              </div>
            </div>

            <div v-if="previewResult.unmatched_names?.length">
              <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">Nama tidak ditemukan di sistem</h3>
              <div class="max-h-32 overflow-y-auto rounded-xl bg-surface-container p-space-sm">
                <p v-for="u in previewResult.unmatched_names" :key="u.row" class="font-body-sm text-body-sm text-on-surface-variant">
                  Baris {{ u.row }}: {{ u.name }}
                </p>
              </div>
              <label class="mt-space-sm flex items-start gap-space-sm rounded-lg bg-primary-fixed/30 p-space-sm">
                <input type="checkbox" v-model="createMissingStudents" class="mt-0.5 h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
                <span class="font-body-sm text-body-sm text-on-surface">
                  Buat murid baru untuk {{ previewResult.unmatched_names.length }} nama di atas (otomatis didaftarkan ke sekolah &amp; mata pelajaran ini), lalu isi absensinya juga.
                </span>
              </label>
            </div>

            <div class="flex items-center justify-between pt-space-xs">
              <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="backToForm">
                Kembali
              </button>
              <button
                type="button"
                :disabled="committing"
                class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
                @click="submitCommit"
              >
                <span v-if="committing" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                Konfirmasi &amp; Simpan
              </button>
            </div>
          </div>

          <!-- Langkah 3: selesai -->
          <div v-else-if="importStep === 'done'" class="flex flex-col items-center gap-space-sm py-space-lg text-center">
            <span class="material-symbols-outlined text-[40px] text-[#065F46]">check_circle</span>
            <p class="font-headline-sm text-headline-sm font-bold text-on-surface">Import berhasil</p>
            <p class="font-body-md text-body-md text-on-surface-variant">
              {{ commitResult.created_sessions }} pertemuan baru dibuat, {{ commitResult.created_students }} murid baru didaftarkan, {{ commitResult.updated_attendance }} data absensi tersimpan.
            </p>
            <button
              type="button"
              class="mt-space-sm rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
              @click="finishImport"
            >
              Selesai
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>