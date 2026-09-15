<script setup>
// Absensi Pertemuan (Trainer & Management).
// - Absensi murid: boolean Hadir/Tidak (FR-5), simpan bulk, kunci pertemuan.
// - Check-in trainer (FR-6): status + foto (onsite wajib) + GPS (onsite) / screenshot (online).
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatStamp, initials, isOnsite, modeLabel, sessionTime } from '@/lib/format'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const sessionId = route.params.id
const backPath = computed(() => (auth.isManagement ? '/management/jadwal' : '/trainer/absensi'))

const loading = ref(true)
const errorMessage = ref('')
const session = ref(null)
const roster = ref([]) // {student_id,name,origin_class,is_present,note,noteOpen}
const isLocked = ref(false)
const search = ref('')

const saving = ref(false)
const locking = ref(false)
const starting = ref(false)
const toast = reactive({ show: false, message: '', ok: true })

// ---- Trainer check-in state ----
const myAttendance = ref(null)
const checkin = reactive({ status: 'hadir', note: '', latitude: null, longitude: null })
const photoFile = ref(null)
const photoPreview = ref('')
const gpsBusy = ref(false)
const gpsError = ref('')
const checkinBusy = ref(false)
const checkinError = ref('')

const sessionOnsite = computed(() => (session.value ? isOnsite(session.value.mode) : true))
const started = computed(() => !!session.value?.started_at)
const isTrainerUser = computed(() => auth.isTrainer && !auth.isManagement)

// Management: bebas lihat/ubah/kunci tanpa "mulai sesi".
// Trainer: harus mulai sesi dulu (buka → mulai → absen).
const canInput = computed(
  () => !isLocked.value && (auth.isManagement || (isTrainerUser.value && started.value)),
)
// Tombol "Mulai Sesi" hanya untuk trainer (management tak perlu).
const canStart = computed(
  () => isTrainerUser.value && !started.value && !isLocked.value,
)
const canLock = computed(
  () =>
    auth.isManagement ||
    (isTrainerUser.value &&
      session.value?.trainer_id === auth.user?.id &&
      (started.value || isLocked.value)),
)
const showCheckin = computed(
  () => started.value && isTrainerUser.value && auth.can('input trainer attendance'),
)

const filteredRoster = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return roster.value
  return roster.value.filter(
    (r) => r.name.toLowerCase().includes(q) || (r.origin_class || '').toLowerCase().includes(q),
  )
})

const presentCount = computed(() => roster.value.filter((r) => r.is_present).length)
const total = computed(() => roster.value.length)
const percent = computed(() => (total.value ? Math.round((presentCount.value / total.value) * 100) : 0))

function showToast(message, ok = true) {
  toast.message = message
  toast.ok = ok
  toast.show = true
  setTimeout(() => (toast.show = false), 3500)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [sRes, rRes] = await Promise.all([
      api.get(`/sessions/${sessionId}`),
      api.get(`/sessions/${sessionId}/student-attendances`),
    ])
    session.value = sRes.data.data ?? sRes.data
    isLocked.value = rRes.data.is_locked
    roster.value = (rRes.data.data ?? []).map((r) => ({
      ...r,
      is_present: !!r.is_present,
      note: r.note || '',
      noteOpen: false,
    }))
    // Check-in trainer yang sudah ada (milik user aktif).
    const mine = (session.value.trainer_attendances ?? []).find(
      (a) => a.trainer_id === auth.user?.id,
    )
    if (mine) {
      myAttendance.value = mine
      checkin.status = mine.status
      checkin.note = mine.note || ''
    }
  } catch (err) {
    errorMessage.value =
      err?.response?.status === 403
        ? 'Anda tidak berwenang mengakses pertemuan ini.'
        : 'Gagal memuat data pertemuan.'
  } finally {
    loading.value = false
  }
}

function setPresent(row, val) {
  if (!canInput.value) return
  row.is_present = val
}

function markAllPresent() {
  if (!canInput.value) return
  roster.value.forEach((r) => (r.is_present = true))
}

async function saveAttendance() {
  if (!canInput.value || !roster.value.length) return
  saving.value = true
  try {
    await api.put(`/sessions/${sessionId}/student-attendances`, {
      attendances: roster.value.map((r) => ({
        student_id: r.student_id,
        is_present: r.is_present,
        note: r.note || null,
      })),
    })
    showToast('Absensi murid tersimpan.')
  } catch (err) {
    const msg =
      err?.response?.status === 403
        ? 'Pertemuan terkunci atau Anda tidak berwenang.'
        : 'Gagal menyimpan absensi.'
    showToast(msg, false)
  } finally {
    saving.value = false
  }
}

async function startSession() {
  if (!canStart.value) return
  starting.value = true
  try {
    const { data } = await api.post(`/sessions/${sessionId}/start`)
    session.value = data.data ?? data
    showToast('Sesi dimulai. Absensi kini aktif.')
  } catch (err) {
    showToast(
      err?.response?.status === 403 ? 'Anda tidak berwenang memulai sesi.' : 'Gagal memulai sesi.',
      false,
    )
  } finally {
    starting.value = false
  }
}

async function toggleLock() {
  if (!canLock.value) return
  const target = !isLocked.value
  if (target && !window.confirm('Kunci pertemuan? Absensi tidak dapat diubah lagi setelah dikunci.'))
    return
  locking.value = true
  try {
    // Simpan dulu absensi terkini sebelum mengunci.
    if (target && canInput.value && roster.value.length) {
      await api.put(`/sessions/${sessionId}/student-attendances`, {
        attendances: roster.value.map((r) => ({
          student_id: r.student_id,
          is_present: r.is_present,
          note: r.note || null,
        })),
      })
    }
    await api.post(`/sessions/${sessionId}/lock`, { is_locked: target })
    isLocked.value = target
    showToast(target ? 'Pertemuan dikunci.' : 'Kunci dibuka.')
  } catch (err) {
    showToast('Gagal mengubah status kunci.', false)
  } finally {
    locking.value = false
  }
}

// ---- Check-in trainer ----
function onPhotoChange(e) {
  const f = e.target.files?.[0]
  if (!f) return
  photoFile.value = f
  photoPreview.value = URL.createObjectURL(f)
}

function captureGps() {
  gpsError.value = ''
  if (!navigator.geolocation) {
    gpsError.value = 'Perangkat tidak mendukung GPS.'
    return
  }
  gpsBusy.value = true
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      checkin.latitude = Number(pos.coords.latitude.toFixed(6))
      checkin.longitude = Number(pos.coords.longitude.toFixed(6))
      gpsBusy.value = false
    },
    () => {
      gpsError.value = 'Gagal mengambil lokasi. Izinkan akses lokasi lalu coba lagi.'
      gpsBusy.value = false
    },
    { enableHighAccuracy: true, timeout: 10000 },
  )
}

async function submitCheckin() {
  checkinError.value = ''
  if (sessionOnsite.value) {
    if (!photoFile.value && !myAttendance.value) {
      checkinError.value = 'Sesi onsite wajib mengunggah foto.'
      return
    }
    if (checkin.latitude == null || checkin.longitude == null) {
      checkinError.value = 'Sesi onsite wajib merekam lokasi GPS.'
      return
    }
  }
  checkinBusy.value = true
  try {
    const fd = new FormData()
    fd.append('status', checkin.status)
    if (checkin.note) fd.append('note', checkin.note)
    if (photoFile.value) fd.append('photo', photoFile.value)
    if (sessionOnsite.value) {
      fd.append('latitude', checkin.latitude)
      fd.append('longitude', checkin.longitude)
    }
    const { data } = await api.post(`/sessions/${sessionId}/trainer-attendances`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    myAttendance.value = data.data ?? data
    showToast('Check-in trainer tersimpan.')
  } catch (err) {
    const res = err?.response
    checkinError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data check-in tidak valid.'
        : 'Gagal menyimpan check-in.'
  } finally {
    checkinBusy.value = false
  }
}

const statusMeta = {
  hadir: { label: 'Hadir', icon: 'check_circle', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  terlambat: { label: 'Terlambat', icon: 'schedule', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  tidak_hadir: { label: 'Tidak Hadir', icon: 'cancel', cls: 'bg-error-container text-error' },
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-40 pt-space-lg">
    <!-- Toast -->
    <div
      class="fixed right-6 top-20 z-50 flex items-center gap-space-sm rounded-xl px-space-md py-space-sm text-white shadow-xl transition-all duration-300"
      :class="[toast.show ? 'translate-y-0 opacity-100' : 'pointer-events-none -translate-y-4 opacity-0', toast.ok ? 'bg-[#0B2A5B]' : 'bg-error']"
    >
      <span class="material-symbols-outlined">{{ toast.ok ? 'check_circle' : 'error' }}</span>
      <span class="font-body-md text-body-md font-medium">{{ toast.message }}</span>
    </div>

    <!-- Back -->
    <RouterLink :to="backPath" class="mb-space-sm inline-flex w-fit items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span> {{ auth.isManagement ? 'Kembali ke jadwal' : 'Kembali ke daftar sesi' }}
    </RouterLink>

    <!-- Loading -->
    <div v-if="loading" class="space-y-space-md">
      <div class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
      <div class="h-64 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <!-- Error -->
    <div
      v-else-if="errorMessage"
      class="flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-md text-on-error-container"
    >
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Coba lagi</button>
    </div>

    <template v-else-if="session">
      <!-- Header banner -->
      <section class="relative overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="pointer-events-none absolute -bottom-10 -right-10 h-44 w-44 rounded-full bg-primary/5 blur-2xl"></div>
        <div class="flex flex-col justify-between gap-space-md md:flex-row md:items-center">
          <div class="flex flex-col gap-space-xs">
            <div class="flex flex-wrap items-center gap-space-xs">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-space-sm py-space-2xs font-label-sm text-label-sm font-semibold"
                :class="isLocked ? 'bg-[#D1FAE5] text-[#065F46]' : started ? 'bg-secondary-container text-on-secondary-container' : 'bg-surface-container-high text-on-surface-variant'"
              >
                <span
                  class="h-2 w-2 rounded-full"
                  :class="isLocked ? 'bg-[#10B981]' : started ? 'bg-primary-container animate-pulse' : 'bg-outline'"
                ></span>
                {{ isLocked ? 'Terkunci' : started ? 'Sedang Berlangsung' : 'Belum Dimulai' }}
              </span>
              <span class="rounded-full bg-surface-container-low px-space-sm py-space-2xs font-label-sm text-label-sm text-on-surface-variant">
                Pertemuan #{{ session.meeting_no }}
              </span>
            </div>
            <h1 class="font-headline-lg text-headline-lg tracking-tight text-on-surface">
              {{ session.school?.name }} — {{ session.classroom?.name || session.classroom?.level }}
            </h1>
            <div class="flex flex-wrap items-center gap-x-space-md gap-y-1 font-body-sm text-body-sm text-on-surface-variant">
              <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">calendar_month</span>
                {{ formatDate(session.date) }}
              </span>
              <template v-if="sessionTime(session)">
                <span class="text-outline-variant">•</span>
                <span class="inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px] text-primary">schedule</span>
                  {{ sessionTime(session) }} WIB
                </span>
              </template>
              <span class="text-outline-variant">•</span>
              <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">{{ sessionOnsite ? 'location_on' : 'videocam' }}</span>
                {{ modeLabel(session.mode) }}
              </span>
              <span class="text-outline-variant">•</span>
              <span class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-primary">person</span>
                Trainer: <strong class="font-semibold text-on-surface">{{ session.trainer?.name || '—' }}</strong>
              </span>
            </div>
          </div>
          <!-- Ring progress -->
          <div class="flex items-center gap-space-sm rounded-xl bg-surface-container-low px-space-md py-space-xs">
            <div class="relative flex h-12 w-12 items-center justify-center">
              <svg class="h-12 w-12 -rotate-90">
                <circle class="text-surface-container-high" cx="24" cy="24" fill="transparent" r="20" stroke="currentColor" stroke-width="4" />
                <circle
                  class="text-primary transition-all duration-500"
                  cx="24" cy="24" fill="transparent" r="20" stroke="currentColor" stroke-width="4" stroke-linecap="round"
                  :stroke-dasharray="125.6"
                  :stroke-dashoffset="125.6 - (125.6 * percent) / 100"
                />
              </svg>
              <span class="absolute font-label-sm text-label-sm font-bold text-primary">{{ percent }}%</span>
            </div>
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm text-on-surface-variant">Kehadiran</span>
              <span class="font-label-lg text-label-lg font-bold text-on-surface">{{ presentCount }}/{{ total }} Hadir</span>
            </div>
          </div>
        </div>
      </section>

      <!-- Locked banner -->
      <section
        v-if="isLocked"
        class="mt-space-md flex items-center gap-space-md rounded-2xl bg-inverse-surface p-space-lg text-inverse-on-surface shadow-md"
      >
        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/10">
          <span class="material-symbols-outlined text-[28px] text-secondary-fixed">lock</span>
        </div>
        <div class="flex flex-col">
          <h2 class="font-headline-sm text-headline-sm font-semibold text-surface-container-lowest">Pertemuan Telah Dikunci</h2>
          <p class="mt-0.5 font-body-sm text-body-sm text-surface-variant">
            Data absensi bersifat final. {{ canLock ? 'Buka kunci bila perlu revisi.' : 'Hubungi Management bila perlu revisi.' }}
          </p>
        </div>
        <button
          v-if="canLock"
          class="ml-auto shrink-0 rounded-xl bg-white/10 px-space-md py-2 font-label-md text-label-md font-semibold text-white transition-colors hover:bg-white/20"
          :disabled="locking"
          @click="toggleLock"
        >
          Buka Kunci
        </button>
      </section>

      <!-- ===== Banner Mulai Sesi (belum dimulai) — hanya trainer ===== -->
      <section
        v-if="!isLocked && !started && isTrainerUser"
        class="mt-space-md flex flex-col items-center gap-space-md rounded-2xl border border-primary-container/20 bg-primary-fixed/40 p-space-lg text-center"
      >
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-container text-on-primary shadow-sm">
          <span class="material-symbols-outlined text-[30px]">play_circle</span>
        </div>
        <div>
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Pertemuan Belum Dimulai</h2>
          <p class="mt-1 font-body-md text-body-md text-on-surface-variant">
            {{ canStart
              ? 'Tekan “Mulai Sesi” untuk membuka absensi murid' + (auth.isTrainer ? ' & check-in trainer.' : '.')
              : 'Menunggu trainer/Management memulai sesi ini.' }}
          </p>
        </div>
        <button
          v-if="canStart"
          class="inline-flex items-center gap-space-xs rounded-xl bg-primary px-space-2xl py-space-sm font-label-lg text-label-lg text-on-primary shadow-md transition-all hover:bg-primary-container active:scale-95 disabled:opacity-60"
          :disabled="starting"
          @click="startSession"
        >
          <span v-if="starting" class="material-symbols-outlined animate-spin text-[20px]">progress_activity</span>
          <span v-else class="material-symbols-outlined text-[20px]">play_arrow</span>
          Mulai Sesi
        </button>
      </section>

      <!-- Indikator sesi dimulai -->
      <div
        v-else-if="started && !isLocked"
        class="mt-space-md flex items-center gap-space-xs font-label-sm text-label-sm text-[#065F46]"
      >
        <span class="material-symbols-outlined text-[18px]">play_circle</span>
        Sesi dimulai {{ formatStamp(session.started_at) }}
      </div>

      <!-- ===== Check-in Trainer ===== -->
      <section v-if="showCheckin" class="mt-space-md rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="mb-space-md flex items-center gap-space-sm">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-container/10 text-primary-container">
            <span class="material-symbols-outlined text-[24px]">verified_user</span>
          </div>
          <div>
            <h2 class="font-headline-md text-headline-md font-bold tracking-tight text-on-surface">Check-in Kehadiran Trainer</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
              {{ sessionOnsite ? 'Sesi onsite: foto + lokasi GPS wajib.' : 'Sesi online: unggah screenshot (tanpa GPS).' }}
            </p>
          </div>
          <span
            v-if="myAttendance"
            class="ml-auto inline-flex items-center gap-1.5 rounded-full px-3 py-1 font-label-md text-label-md font-bold"
            :class="statusMeta[myAttendance.status]?.cls"
          >
            <span class="material-symbols-outlined text-[18px]">{{ statusMeta[myAttendance.status]?.icon }}</span>
            {{ statusMeta[myAttendance.status]?.label }} ✓
          </span>
        </div>

        <!-- Sudah check-in -->
        <div v-if="myAttendance" class="flex flex-col gap-space-md rounded-xl bg-surface-container-low p-space-md md:flex-row md:items-center">
          <img
            v-if="myAttendance.photo_url"
            :src="myAttendance.photo_url"
            alt="Bukti kehadiran"
            class="h-20 w-20 rounded-xl object-cover ring-2 ring-primary/20"
          />
          <div class="flex flex-col gap-1">
            <span class="inline-flex w-fit items-center gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
              <span class="material-symbols-outlined text-[16px] text-secondary">schedule</span>
              Check-in tercatat: <strong class="text-on-surface">{{ formatStamp(myAttendance.check_in_at) }}</strong>
            </span>
            <span v-if="myAttendance.latitude" class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-surface-container-highest px-2 py-1 font-label-sm text-label-sm text-on-surface">
              <span class="material-symbols-outlined text-[16px] text-primary">my_location</span>
              GPS: {{ myAttendance.latitude }}, {{ myAttendance.longitude }}
            </span>
            <span v-if="myAttendance.note" class="font-body-sm text-body-sm text-on-surface-variant">Catatan: {{ myAttendance.note }}</span>
            <button class="mt-1 w-fit font-label-sm text-label-sm font-semibold text-primary hover:underline" @click="myAttendance = null">
              Perbarui check-in
            </button>
          </div>
        </div>

        <!-- Form check-in -->
        <div v-else class="flex flex-col gap-space-md">
          <div
            v-if="checkinError"
            class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container"
          >
            <span class="material-symbols-outlined text-[18px]">error</span>{{ checkinError }}
          </div>

          <!-- Status -->
          <div class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Status Kehadiran</span>
            <div class="inline-flex w-fit rounded-xl bg-surface-container-low p-1">
              <button
                v-for="(m, key) in statusMeta"
                :key="key"
                type="button"
                class="rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
                :class="checkin.status === key ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                @click="checkin.status = key"
              >
                {{ m.label }}
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-space-md md:grid-cols-2">
            <!-- Foto / screenshot -->
            <div class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">
                {{ sessionOnsite ? 'Foto Kehadiran (wajib)' : 'Screenshot (opsional)' }}
              </span>
              <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-low px-space-md py-space-md text-center transition-colors hover:border-primary-container">
                <img v-if="photoPreview" :src="photoPreview" alt="Preview" class="h-24 w-24 rounded-lg object-cover" />
                <template v-else>
                  <span class="material-symbols-outlined text-[28px] text-outline">{{ sessionOnsite ? 'photo_camera' : 'image' }}</span>
                  <span class="font-body-sm text-body-sm text-on-surface-variant">Ketuk untuk {{ sessionOnsite ? 'ambil foto' : 'unggah screenshot' }}</span>
                </template>
                <input type="file" accept="image/*" :capture="sessionOnsite ? 'environment' : undefined" class="hidden" @change="onPhotoChange" />
              </label>
            </div>

            <!-- GPS (onsite) -->
            <div v-if="sessionOnsite" class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Lokasi GPS (wajib)</span>
              <div class="flex flex-1 flex-col justify-center gap-space-xs rounded-xl bg-surface-container-low p-space-md">
                <button
                  type="button"
                  class="inline-flex w-fit items-center gap-1.5 rounded-lg bg-primary-container px-space-md py-2 font-label-md text-label-md font-semibold text-on-primary transition-all hover:opacity-95 active:scale-95"
                  :disabled="gpsBusy"
                  @click="captureGps"
                >
                  <span class="material-symbols-outlined text-[18px]" :class="gpsBusy ? 'animate-spin' : ''">{{ gpsBusy ? 'progress_activity' : 'my_location' }}</span>
                  {{ checkin.latitude != null ? 'Perbarui Lokasi' : 'Rekam Lokasi' }}
                </button>
                <span v-if="checkin.latitude != null" class="inline-flex items-center gap-1.5 font-label-sm text-label-sm text-[#065F46]">
                  <span class="material-symbols-outlined text-[16px]">check_circle</span>
                  {{ checkin.latitude }}, {{ checkin.longitude }}
                </span>
                <span v-if="gpsError" class="font-body-sm text-body-sm text-error">{{ gpsError }}</span>
              </div>
            </div>
          </div>

          <!-- Note -->
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Catatan (opsional)</span>
            <input
              v-model="checkin.note"
              type="text"
              placeholder="Mis. terlambat karena macet"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            />
          </label>

          <button
            type="button"
            :disabled="checkinBusy"
            class="inline-flex w-fit items-center gap-space-xs rounded-xl bg-primary px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:bg-primary-container active:scale-95 disabled:opacity-60"
            @click="submitCheckin"
          >
            <span v-if="checkinBusy" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
            <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
            Simpan Check-in
          </button>
        </div>
      </section>

      <!-- ===== Absensi Murid ===== -->
      <section class="mt-space-md rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="mb-space-md flex flex-col justify-between gap-space-sm sm:flex-row sm:items-center">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold tracking-tight text-on-surface">Absensi Murid</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Tandai Hadir / Tidak per murid. {{ isLocked ? 'Pertemuan terkunci (read-only).' : 'Cepat & sederhana.' }}</p>
          </div>
          <button
            v-if="canInput"
            class="inline-flex items-center justify-center gap-space-xs rounded-xl bg-primary-container px-space-md py-2.5 font-label-md text-label-md font-bold text-on-primary shadow-sm transition hover:opacity-95 active:scale-95"
            @click="markAllPresent"
          >
            <span class="material-symbols-outlined text-[18px]">done_all</span>
            Tandai Semua Hadir
          </button>
        </div>

        <!-- Search -->
        <div class="relative mb-space-md">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline">search</span>
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama murid atau kelas asal…"
            class="w-full rounded-xl bg-surface-container-low py-2.5 pl-9 pr-space-md font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
          />
        </div>

        <!-- Empty roster -->
        <div v-if="!roster.length" class="flex flex-col items-center justify-center py-space-2xl text-center">
          <span class="material-symbols-outlined text-[36px] text-outline">group_off</span>
          <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada murid pada level ini</p>
          <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Tambahkan murid melalui modul Sekolah &amp; Murid.</p>
        </div>

        <!-- Roster -->
        <div v-else class="flex flex-col gap-space-xs">
          <article
            v-for="(r, idx) in filteredRoster"
            :key="r.student_id"
            class="flex flex-col gap-space-sm rounded-xl bg-surface-container-low p-space-md transition-all"
          >
            <div class="flex flex-col justify-between gap-space-sm sm:flex-row sm:items-center">
              <div class="flex min-w-0 items-center gap-space-sm">
                <span class="w-5 shrink-0 text-center font-label-md text-label-md text-outline">{{ String(idx + 1).padStart(2, '0') }}</span>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-container-high font-headline-sm text-headline-sm font-bold text-primary">
                  {{ initials(r.name) }}
                </div>
                <div class="flex min-w-0 flex-col">
                  <span class="truncate font-headline-sm text-headline-sm text-on-surface">{{ r.name }}</span>
                  <div class="flex items-center gap-space-xs">
                    <span v-if="r.origin_class" class="rounded bg-surface-container px-space-xs py-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ r.origin_class }}</span>
                    <span v-if="r.note" class="truncate rounded bg-tertiary-fixed px-space-xs py-0.5 font-label-sm text-label-sm text-on-tertiary-container">{{ r.note }}</span>
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-space-xs self-end sm:self-center">
                <div class="inline-flex gap-1 rounded-xl bg-surface-container-lowest p-1">
                  <button
                    type="button"
                    class="flex items-center gap-1 rounded-lg px-3 py-1.5 font-label-md text-label-md transition-all"
                    :class="r.is_present ? 'bg-[#10B981] text-white shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                    :disabled="!canInput"
                    @click="setPresent(r, true)"
                  >
                    <span class="h-2 w-2 rounded-full" :class="r.is_present ? 'bg-white' : 'bg-outline'"></span>
                    Hadir
                  </button>
                  <button
                    type="button"
                    class="flex items-center gap-1 rounded-lg px-3 py-1.5 font-label-md text-label-md transition-all"
                    :class="!r.is_present ? 'bg-error text-on-error shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
                    :disabled="!canInput"
                    @click="setPresent(r, false)"
                  >
                    Tidak
                  </button>
                </div>
                <button
                  type="button"
                  class="rounded-xl p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary disabled:opacity-40"
                  title="Catatan"
                  :disabled="!canInput"
                  @click="r.noteOpen = !r.noteOpen"
                >
                  <span class="material-symbols-outlined text-[20px]">sticky_note_2</span>
                </button>
              </div>
            </div>
            <div v-if="r.noteOpen" class="flex items-center gap-space-xs rounded-lg bg-surface-container-lowest p-space-xs">
              <span class="material-symbols-outlined pl-1 text-[18px] text-outline">edit_note</span>
              <input
                v-model="r.note"
                type="text"
                :disabled="!canInput"
                placeholder="Catatan kehadiran (mis. sakit, izin lomba)…"
                class="w-full border-none bg-transparent font-body-sm text-body-sm text-on-surface outline-none placeholder:text-outline"
              />
            </div>
          </article>
        </div>
      </section>
    </template>

    <!-- Sticky bottom bar -->
    <footer
      v-if="!loading && session && !isLocked && (canInput || canLock)"
      class="fixed bottom-0 left-0 right-0 z-40 border-t border-surface-container bg-surface-container-lowest/95 px-gutter-mobile py-3 shadow-[0_-4px_20px_rgba(11,28,48,0.08)] backdrop-blur-md sm:px-gutter-desktop lg:left-sidebar-width"
    >
      <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-space-sm sm:flex-row">
        <div class="flex items-center gap-space-xs">
          <span class="h-3 w-3 rounded-full bg-[#10B981]"></span>
          <div class="flex flex-col">
            <span class="font-label-sm text-label-sm text-on-surface-variant">Statistik Sesi</span>
            <span class="font-label-lg text-label-lg font-bold text-on-surface">
              {{ presentCount }} dari {{ total }} Murid Hadir <span class="font-semibold text-primary">({{ percent }}%)</span>
            </span>
          </div>
        </div>
        <div class="flex w-full items-center gap-space-sm sm:w-auto">
          <button
            v-if="canInput"
            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-surface-container-low px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-colors hover:bg-surface-container disabled:opacity-60 sm:flex-initial"
            :disabled="saving"
            @click="saveAttendance"
          >
            <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
            <span v-else class="material-symbols-outlined text-[18px]">save</span>
            Simpan
          </button>
          <button
            v-if="canLock"
            class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-error px-space-lg py-2.5 font-label-lg text-label-lg text-on-error shadow-sm transition-all hover:bg-on-error-container active:scale-95 disabled:opacity-60 sm:flex-initial"
            :disabled="locking"
            @click="toggleLock"
          >
            <span v-if="locking" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
            <span v-else class="material-symbols-outlined text-[18px]">lock</span>
            Kunci Pertemuan
          </button>
        </div>
      </div>
    </footer>
  </div>
</template>
