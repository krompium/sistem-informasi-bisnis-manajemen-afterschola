<script setup>
// Kelola Jadwal (Management) — CRUD pertemuan sesuai FR-4:
// Management membuat sekolah + level + trainer + tanggal + pertemuan ke- + mode.
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import MonthCalendar from '@/components/MonthCalendar.vue'
import { formatDate, formatDateShort, initials, isOnsite, modeLabel, sessionStatus, sessionTime, unwrap } from '@/lib/format'
import { usePolling } from '@/lib/usePolling'

const router = useRouter()

const viewMode = ref('tabel') // 'tabel' | 'kalender'
const selectedDate = ref('') // 'YYYY-MM-DD' untuk daftar sesi per hari

const loading = ref(true)
const errorMessage = ref('')
const sessions = ref([])
const schools = ref([])
const trainers = ref([])
const classroomsBySchool = reactive({}) // cache level per school_id

// Filter
const filters = reactive({ school_id: '', trainer_id: '', date: '', status: '' })

// Modal
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  id: null,
  school_id: '',
  classroom_id: '',
  trainer_id: '',
  date: '',
  start_time: '',
  end_time: '',
  meeting_no: 1,
  mode: 'onsite',
  note: '',
})
const modalClassrooms = ref([])

// ----- Modal Jadwal Berulang -----
const showRecurring = ref(false)
const recSaving = ref(false)
const recError = ref('')
const recModalClassrooms = ref([])
const recForm = reactive({
  school_id: '',
  classroom_id: '',
  trainer_id: '',
  mode: 'onsite',
  start_time: '',
  end_time: '',
  start_date: '',
  interval_days: 7,
  count: 16,
  start_meeting_no: 1,
  note: '',
})

// Pratinjau tanggal & nomor pertemuan yang akan dibuat.
const recPreview = computed(() => {
  const out = []
  const count = Math.min(Math.max(Number(recForm.count) || 0, 0), 52)
  const interval = Math.max(Number(recForm.interval_days) || 1, 1)
  const startNo = Number(recForm.start_meeting_no) || 1
  if (!recForm.start_date || !count) return out
  const base = new Date(recForm.start_date)
  for (let i = 0; i < count; i++) {
    const d = new Date(base)
    d.setDate(base.getDate() + interval * i)
    out.push({ no: startNo + i, date: d.toISOString().slice(0, 10) })
  }
  return out
})

const monthPrefix = (() => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
})()

const stats = computed(() => {
  const list = sessions.value
  return {
    total: list.length,
    month: list.filter((s) => String(s.date || '').startsWith(monthPrefix)).length,
    today: list.filter((s) => sessionStatus(s).key === 'today').length,
    pending: list.filter((s) => sessionStatus(s).key === 'pending').length,
    locked: list.filter((s) => s.is_locked).length,
  }
})

// Sesi pada tanggal yang dipilih di kalender.
const sessionsOnSelected = computed(() =>
  selectedDate.value
    ? sessions.value.filter((s) => String(s.date || '').slice(0, 10) === selectedDate.value)
    : [],
)

function onDayClick(dateStr) {
  selectedDate.value = selectedDate.value === dateStr ? '' : dateStr
}

const filteredSessions = computed(() => {
  return sessions.value.filter((s) => {
    if (filters.status && sessionStatus(s).key !== filters.status) return false
    return true
  })
})

async function loadRefData() {
  try {
    const [sc, us] = await Promise.all([api.get('/schools'), api.get('/users')])
    schools.value = unwrap(sc.data)
    trainers.value = unwrap(us.data).filter((u) => (u.roles ?? []).includes('trainer'))
  } catch (e) {
    // Non-fatal; form tetap bisa dicoba, error muncul saat submit.
  }
}

async function loadSessions(silent = false) {
  if (!silent) loading.value = true
  try {
    const params = {}
    if (filters.school_id) params.school_id = filters.school_id
    if (filters.trainer_id) params.trainer_id = filters.trainer_id
    if (filters.date) params.date = filters.date
    const { data } = await api.get('/sessions', { params })
    sessions.value = unwrap(data)
    errorMessage.value = ''
  } catch (err) {
    if (!silent) errorMessage.value = 'Gagal memuat jadwal. Coba muat ulang.'
  } finally {
    if (!silent) loading.value = false
  }
}

async function fetchClassrooms(schoolId) {
  if (!schoolId) return []
  if (classroomsBySchool[schoolId]) return classroomsBySchool[schoolId]
  const { data } = await api.get('/classrooms', { params: { school_id: schoolId } })
  classroomsBySchool[schoolId] = unwrap(data)
  return classroomsBySchool[schoolId]
}

function openCreate() {
  Object.assign(form, {
    id: null,
    school_id: '',
    classroom_id: '',
    trainer_id: '',
    date: new Date().toISOString().slice(0, 10),
    start_time: '',
    end_time: '',
    meeting_no: 1,
    mode: 'onsite',
    note: '',
  })
  modalClassrooms.value = []
  formError.value = ''
  showModal.value = true
}

async function openEdit(s) {
  Object.assign(form, {
    id: s.id,
    school_id: s.school_id,
    classroom_id: s.classroom_id,
    trainer_id: s.trainer_id,
    date: s.date,
    start_time: s.start_time || '',
    end_time: s.end_time || '',
    meeting_no: s.meeting_no,
    mode: isOnsite(s.mode) ? 'onsite' : 'online',
    note: s.note || '',
  })
  formError.value = ''
  showModal.value = true
  modalClassrooms.value = await fetchClassrooms(s.school_id)
}

async function onSchoolChange() {
  form.classroom_id = ''
  modalClassrooms.value = await fetchClassrooms(form.school_id)
}

async function submitForm() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      school_id: form.school_id,
      classroom_id: form.classroom_id,
      trainer_id: form.trainer_id,
      date: form.date,
      start_time: form.start_time || null,
      end_time: form.end_time || null,
      meeting_no: Number(form.meeting_no),
      mode: form.mode,
      note: form.note || null,
    }
    if (form.id) {
      await api.put(`/sessions/${form.id}`, payload)
    } else {
      await api.post('/sessions', payload)
    }
    showModal.value = false
    await loadSessions()
  } catch (err) {
    const res = err?.response
    if (res?.status === 422) {
      formError.value = Object.values(res.data?.errors ?? {})
        .flat()
        .join(' ') || 'Data tidak valid.'
    } else if (res?.status === 403) {
      formError.value = 'Anda tidak berwenang membuat/mengubah jadwal.'
    } else {
      formError.value = 'Gagal menyimpan. Coba lagi.'
    }
  } finally {
    saving.value = false
  }
}

async function removeSession(s) {
  if (!window.confirm(`Hapus pertemuan #${s.meeting_no} di ${s.school?.name || 'sekolah'}?`)) return
  try {
    await api.delete(`/sessions/${s.id}`)
    await loadSessions()
  } catch (e) {
    window.alert('Gagal menghapus pertemuan.')
  }
}

function goAbsensi(s) {
  router.push(`/management/absensi/${s.id}`)
}

// ---- Jadwal Berulang ----
function openRecurring() {
  Object.assign(recForm, {
    school_id: '',
    classroom_id: '',
    trainer_id: '',
    mode: 'onsite',
    start_time: '',
    end_time: '',
    start_date: new Date().toISOString().slice(0, 10),
    interval_days: 7,
    count: 16,
    start_meeting_no: 1,
    note: '',
  })
  recModalClassrooms.value = []
  recError.value = ''
  showRecurring.value = true
}

async function onRecSchoolChange() {
  recForm.classroom_id = ''
  recModalClassrooms.value = await fetchClassrooms(recForm.school_id)
}

async function submitRecurring() {
  recSaving.value = true
  recError.value = ''
  try {
    await api.post('/sessions/bulk', {
      school_id: recForm.school_id,
      classroom_id: recForm.classroom_id,
      trainer_id: recForm.trainer_id,
      mode: recForm.mode,
      start_time: recForm.start_time || null,
      end_time: recForm.end_time || null,
      start_date: recForm.start_date,
      interval_days: Number(recForm.interval_days) || 7,
      count: Number(recForm.count),
      start_meeting_no: Number(recForm.start_meeting_no) || 1,
      note: recForm.note || null,
    })
    showRecurring.value = false
    await loadSessions()
  } catch (err) {
    const res = err?.response
    if (res?.status === 422) {
      recError.value =
        Object.values(res.data?.errors ?? {})
          .flat()
          .join(' ') || 'Data tidak valid.'
    } else if (res?.status === 403) {
      recError.value = 'Anda tidak berwenang membuat jadwal.'
    } else {
      recError.value = 'Gagal membuat jadwal berulang. Coba lagi.'
    }
  } finally {
    recSaving.value = false
  }
}

function resetFilters() {
  filters.school_id = ''
  filters.trainer_id = ''
  filters.date = ''
  filters.status = ''
  loadSessions()
}

onMounted(async () => {
  await Promise.all([loadRefData(), loadSessions()])
})

// Live monitoring: segarkan daftar jadwal tiap detik (jeda saat modal buka/tab tersembunyi).
usePolling(() => {
  if (!showModal.value && !showRecurring.value) return loadSessions(true)
}, 1000)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <!-- Header + stats -->
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Absensi • Penjadwalan</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          Kelola Jadwal Pertemuan
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Atur sesi belajar: sekolah, level, trainer, tanggal, pertemuan ke-, dan mode. Trainer
          hanya melihat &amp; menjalankan.
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start lg:self-center">
        <button
          class="inline-flex items-center gap-space-xs rounded-xl bg-surface-container px-space-md py-2.5 font-label-lg text-label-lg text-primary shadow-sm transition-all hover:bg-surface-container-high"
          @click="openRecurring"
        >
          <span class="material-symbols-outlined text-[18px]">event_repeat</span>
          <span>Jadwal Berulang</span>
        </button>
        <button
          class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
          @click="openCreate"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          <span>Buat Pertemuan</span>
        </button>
      </div>
    </div>

    <!-- Stats strip -->
    <div class="mb-space-lg grid grid-cols-2 gap-space-md sm:grid-cols-3 lg:grid-cols-5">
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Bulan Ini</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-primary">{{ stats.month }}</p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Total Sesi</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-on-surface">{{ stats.total }}</p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Hari Ini</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-primary">{{ stats.today }}</p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Perlu Diabsen</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-[#92400E]">{{ stats.pending }}</p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Terkunci</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-[#065F46]">{{ stats.locked }}</p>
      </div>
    </div>

    <!-- Toggle Tabel / Kalender -->
    <div class="mb-space-md flex items-center gap-space-xs">
      <div class="inline-flex rounded-xl bg-surface-container p-1">
        <button
          class="inline-flex items-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
          :class="viewMode === 'tabel' ? 'bg-surface-container-lowest text-primary shadow-sm' : 'text-on-surface-variant'"
          @click="viewMode = 'tabel'"
        >
          <span class="material-symbols-outlined text-[18px]">table_rows</span> Tabel
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

    <!-- Filter bar -->
    <div
      v-if="viewMode === 'tabel'"
      class="mb-space-lg flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-md shadow-sm sm:flex-row sm:flex-wrap sm:items-end"
    >
      <label class="flex flex-1 flex-col gap-1">
        <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Sekolah</span>
        <select
          v-model="filters.school_id"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
          @change="loadSessions"
        >
          <option value="">Semua sekolah</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </label>
      <label class="flex flex-1 flex-col gap-1">
        <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Trainer</span>
        <select
          v-model="filters.trainer_id"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
          @change="loadSessions"
        >
          <option value="">Semua trainer</option>
          <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </label>
      <label class="flex flex-1 flex-col gap-1">
        <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Tanggal</span>
        <input
          v-model="filters.date"
          type="date"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
          @change="loadSessions"
        />
      </label>
      <label class="flex flex-1 flex-col gap-1">
        <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Status</span>
        <select
          v-model="filters.status"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
        >
          <option value="">Semua status</option>
          <option value="today">Hari Ini</option>
          <option value="upcoming">Akan Datang</option>
          <option value="pending">Perlu Diabsen</option>
          <option value="locked">Terkunci</option>
        </select>
      </label>
      <button
        class="rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold text-primary hover:bg-surface-container-low"
        @click="resetFilters"
      >
        Reset
      </button>
    </div>

    <!-- Error -->
    <div
      v-if="errorMessage"
      class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container"
    >
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadSessions">
        Muat ulang
      </button>
    </div>

    <!-- Tabel -->
    <div v-if="viewMode === 'tabel'" class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
      <div v-if="loading" class="space-y-space-sm">
        <div v-for="i in 5" :key="i" class="h-12 animate-pulse rounded-lg bg-surface-container"></div>
      </div>

      <div
        v-else-if="!filteredSessions.length"
        class="flex flex-col items-center justify-center py-space-2xl text-center"
      >
        <span class="material-symbols-outlined text-[36px] text-outline">event_busy</span>
        <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada pertemuan</p>
        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
          Klik "Buat Pertemuan" untuk menjadwalkan sesi belajar pertama.
        </p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[820px] text-left">
          <thead>
            <tr class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-on-surface">
              <th class="rounded-l-xl p-space-sm">Pertemuan</th>
              <th class="p-space-sm">Sekolah</th>
              <th class="p-space-sm">Level</th>
              <th class="p-space-sm">Trainer</th>
              <th class="p-space-sm">Mode</th>
              <th class="p-space-sm">Hadir</th>
              <th class="p-space-sm">Status</th>
              <th class="rounded-r-xl p-space-sm text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="font-body-md text-body-md">
            <tr
              v-for="s in filteredSessions"
              :key="s.id"
              class="border-b border-surface-container transition-colors last:border-0 hover:bg-surface-container-low"
            >
              <td class="p-space-sm">
                <div class="flex flex-col">
                  <span class="font-semibold text-on-surface">Pertemuan #{{ s.meeting_no }}</span>
                  <span class="font-body-sm text-body-sm text-outline"
                    >{{ formatDateShort(s.date) }}<template v-if="sessionTime(s)"> • {{ sessionTime(s) }}</template></span
                  >
                </div>
              </td>
              <td class="p-space-sm text-on-surface-variant">{{ s.school?.name || '—' }}</td>
              <td class="p-space-sm">
                <span class="rounded-md bg-surface-container-high px-2 py-0.5 font-label-sm text-label-sm font-semibold text-primary">{{
                  s.classroom?.name || s.classroom?.level || '—'
                }}</span>
              </td>
              <td class="p-space-sm">
                <div class="flex items-center gap-space-xs">
                  <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#ABC3FE] text-[11px] font-bold text-[#001A42]">{{
                    initials(s.trainer?.name)
                  }}</span>
                  <span class="text-on-surface">{{ s.trainer?.name || '—' }}</span>
                </div>
              </td>
              <td class="p-space-sm">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold"
                  :class="isOnsite(s.mode) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-secondary-fixed text-on-secondary-container'"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="isOnsite(s.mode) ? 'bg-[#10B981]' : 'bg-primary-container'"></span>
                  {{ modeLabel(s.mode) }}
                </span>
              </td>
              <td class="p-space-sm font-semibold text-on-surface">{{ s.present_count ?? 0 }}</td>
              <td class="p-space-sm">
                <span
                  class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold"
                  :class="sessionStatus(s).cls"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="sessionStatus(s).dot"></span>
                  {{ sessionStatus(s).label }}
                </span>
              </td>
              <td class="p-space-sm">
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="rounded-lg p-1.5 text-primary transition-colors hover:bg-surface-container"
                    title="Absensi"
                    @click="goAbsensi(s)"
                  >
                    <span class="material-symbols-outlined text-[18px]">fact_check</span>
                  </button>
                  <button
                    class="rounded-lg p-1.5 text-secondary transition-colors hover:bg-surface-container"
                    title="Edit"
                    @click="openEdit(s)"
                  >
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                  </button>
                  <button
                    class="rounded-lg p-1.5 text-error transition-colors hover:bg-error-container"
                    title="Hapus"
                    @click="removeSession(s)"
                  >
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Kalender -->
    <div v-if="viewMode === 'kalender'" class="flex flex-col gap-space-md">
      <MonthCalendar :sessions="sessions" :selected-date="selectedDate" @day-click="onDayClick" />

      <!-- Daftar sesi pada hari terpilih -->
      <div v-if="selectedDate" class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <div class="mb-space-sm flex items-center justify-between">
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">
            {{ formatDate(selectedDate) }}
          </h3>
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
                <span class="font-label-lg text-label-lg font-semibold text-on-surface"
                  >{{ s.school?.name }} • #{{ s.meeting_no }}</span
                >
                <span class="font-body-sm text-body-sm text-on-surface-variant"
                  >{{ s.classroom?.name || s.classroom?.level }} • {{ s.trainer?.name }} • {{ modeLabel(s.mode) }}<template v-if="sessionTime(s)"> • {{ sessionTime(s) }}</template></span
                >
              </div>
            </div>
            <div class="flex items-center gap-space-xs self-end sm:self-center">
              <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold" :class="sessionStatus(s).cls">
                <span class="h-1.5 w-1.5 rounded-full" :class="sessionStatus(s).dot"></span>
                {{ sessionStatus(s).label }}
              </span>
              <button class="rounded-lg p-1.5 text-primary hover:bg-surface-container" title="Absensi" @click="goAbsensi(s)">
                <span class="material-symbols-outlined text-[18px]">fact_check</span>
              </button>
              <button class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Edit" @click="openEdit(s)">
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <p v-else class="text-center font-body-sm text-body-sm text-outline">
        Klik salah satu tanggal untuk melihat detail pertemuannya.
      </p>
    </div>

    <!-- ===================== MODAL ===================== -->
    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showModal = false"
    >
      <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div class="flex items-center gap-space-sm">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-primary">
              <span class="material-symbols-outlined">{{ form.id ? 'edit_calendar' : 'calendar_add_on' }}</span>
            </div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">
              {{ form.id ? 'Ubah Pertemuan' : 'Buat Pertemuan Baru' }}
            </h2>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="flex flex-col gap-space-md p-space-lg" @submit.prevent="submitForm">
          <div
            v-if="formError"
            class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container"
          >
            <span class="material-symbols-outlined text-[18px]">error</span>
            {{ formError }}
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sekolah Mitra</span>
            <select
              v-model="form.school_id"
              required
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              @change="onSchoolChange"
            >
              <option value="" disabled>Pilih sekolah…</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </label>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Level (Kelas)</span>
            <select
              v-model="form.classroom_id"
              required
              :disabled="!form.school_id"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15 disabled:bg-surface-container-low disabled:text-outline"
            >
              <option value="" disabled>{{ form.school_id ? 'Pilih level…' : 'Pilih sekolah dulu' }}</option>
              <option v-for="c in modalClassrooms" :key="c.id" :value="c.id">
                {{ c.name }}<span v-if="c.level"> — {{ c.level }}</span>
              </option>
            </select>
          </label>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Trainer Pengampu</span>
            <select
              v-model="form.trainer_id"
              required
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
              <option value="" disabled>Pilih trainer…</option>
              <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </label>

          <div class="grid grid-cols-2 gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tanggal</span>
              <input
                v-model="form.date"
                type="date"
                required
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              />
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Pertemuan Ke-</span>
              <input
                v-model.number="form.meeting_no"
                type="number"
                min="1"
                required
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              />
            </label>
          </div>

          <div class="grid grid-cols-2 gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant"
                >Jam Mulai <span class="font-normal text-outline">(opsional)</span></span
              >
              <input
                v-model="form.start_time"
                type="time"
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              />
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant"
                >Jam Selesai <span class="font-normal text-outline">(opsional)</span></span
              >
              <input
                v-model="form.end_time"
                type="time"
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              />
            </label>
          </div>

          <div class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mode Sesi</span>
            <div class="inline-flex rounded-xl bg-surface-container-low p-1">
              <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
                :class="form.mode === 'onsite' ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'"
                @click="form.mode = 'onsite'"
              >
                <span class="material-symbols-outlined text-[18px]">location_on</span> Onsite
              </button>
              <button
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
                :class="form.mode === 'online' ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'"
                @click="form.mode = 'online'"
              >
                <span class="material-symbols-outlined text-[18px]">videocam</span> Online
              </button>
            </div>
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant"
              >Catatan <span class="font-normal text-outline">(opsional)</span></span
            >
            <textarea
              v-model="form.note"
              rows="2"
              placeholder="Mis. ruangan Lab Komputer 2, atau link Zoom untuk sesi online"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            ></textarea>
          </label>

          <div class="flex items-center justify-end gap-space-sm pt-space-xs">
            <button
              type="button"
              class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container"
              @click="showModal = false"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="saving"
              class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            >
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              <span>{{ form.id ? 'Simpan Perubahan' : 'Simpan Pertemuan' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ===================== MODAL JADWAL BERULANG ===================== -->
    <div
      v-if="showRecurring"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showRecurring = false"
    >
      <div class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div class="flex items-center gap-space-sm">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-primary">
              <span class="material-symbols-outlined">event_repeat</span>
            </div>
            <div>
              <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Jadwal Berulang</h2>
              <p class="font-body-sm text-body-sm text-on-surface-variant">Buat banyak pertemuan sekaligus (mis. 16 pertemuan mingguan)</p>
            </div>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showRecurring = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="grid grid-cols-1 gap-space-md overflow-y-auto p-space-lg md:grid-cols-2" @submit.prevent="submitRecurring">
          <div
            v-if="recError"
            class="md:col-span-2 flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container"
          >
            <span class="material-symbols-outlined text-[18px]">error</span>{{ recError }}
          </div>

          <!-- Kolom kiri: parameter -->
          <div class="flex flex-col gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sekolah Mitra</span>
              <select v-model="recForm.school_id" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" @change="onRecSchoolChange">
                <option value="" disabled>Pilih sekolah…</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Level (Kelas)</span>
              <select v-model="recForm.classroom_id" required :disabled="!recForm.school_id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15 disabled:bg-surface-container-low disabled:text-outline">
                <option value="" disabled>{{ recForm.school_id ? 'Pilih level…' : 'Pilih sekolah dulu' }}</option>
                <option v-for="c in recModalClassrooms" :key="c.id" :value="c.id">{{ c.name }}<span v-if="c.level"> — {{ c.level }}</span></option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Trainer Pengampu</span>
              <select v-model="recForm.trainer_id" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option value="" disabled>Pilih trainer…</option>
                <option v-for="t in trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </label>
            <div class="grid grid-cols-2 gap-space-md">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Jam Mulai</span>
                <input v-model="recForm.start_time" type="time" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Jam Selesai</span>
                <input v-model="recForm.end_time" type="time" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
              </label>
            </div>
            <div class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mode Sesi</span>
              <div class="inline-flex rounded-xl bg-surface-container-low p-1">
                <button type="button" class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all" :class="recForm.mode === 'onsite' ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'" @click="recForm.mode = 'onsite'">
                  <span class="material-symbols-outlined text-[18px]">location_on</span> Onsite
                </button>
                <button type="button" class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all" :class="recForm.mode === 'online' ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'" @click="recForm.mode = 'online'">
                  <span class="material-symbols-outlined text-[18px]">videocam</span> Online
                </button>
              </div>
            </div>
          </div>

          <!-- Kolom kanan: pengulangan + pratinjau -->
          <div class="flex flex-col gap-space-md">
            <div class="grid grid-cols-2 gap-space-md">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tanggal Mulai</span>
                <input v-model="recForm.start_date" type="date" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Pengulangan</span>
                <select v-model.number="recForm.interval_days" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                  <option :value="1">Setiap hari</option>
                  <option :value="7">Mingguan</option>
                  <option :value="14">2 Mingguan</option>
                  <option :value="30">Bulanan (±30 hari)</option>
                </select>
              </label>
            </div>
            <div class="grid grid-cols-2 gap-space-md">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Jumlah Pertemuan</span>
                <input v-model.number="recForm.count" type="number" min="1" max="52" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mulai Pertemuan Ke-</span>
                <input v-model.number="recForm.start_meeting_no" type="number" min="1" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
              </label>
            </div>

            <!-- Pratinjau -->
            <div class="flex flex-1 flex-col rounded-xl border border-surface-container bg-surface-container-low p-space-sm">
              <div class="mb-space-xs flex items-center justify-between">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Pratinjau</span>
                <span class="font-label-sm text-label-sm font-semibold text-primary">{{ recPreview.length }} pertemuan</span>
              </div>
              <div v-if="!recPreview.length" class="py-space-md text-center font-body-sm text-body-sm text-outline">Isi tanggal mulai & jumlah untuk melihat pratinjau.</div>
              <div v-else class="max-h-40 overflow-y-auto pr-1">
                <div v-for="p in recPreview" :key="p.no" class="flex items-center justify-between border-b border-surface-container py-1 last:border-0 font-body-sm text-body-sm">
                  <span class="font-semibold text-on-surface">Pertemuan #{{ p.no }}</span>
                  <span class="text-on-surface-variant">{{ formatDateShort(p.date) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="md:col-span-2 flex items-center justify-between gap-space-sm border-t border-surface-container pt-space-md">
            <p class="font-body-sm text-body-sm text-outline">Tiap pertemuan bisa diubah/dihapus terpisah setelah dibuat.</p>
            <div class="flex items-center gap-space-sm">
              <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showRecurring = false">Batal</button>
              <button type="submit" :disabled="recSaving || !recPreview.length" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
                <span v-if="recSaving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                <span>Buat {{ recPreview.length }} Pertemuan</span>
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
