<script setup>
// Rekap Absensi (Modul 7c, MGT-40..43, KEU-08) — rekap siswa, rekap trainer, dan trainer yang belum check-in hari ini.
// Tab mengikuti permission; backend tetap otoritas (scope cabang di RegularAttendanceReport).
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canStudents = computed(() => auth.can('view all attendance') || auth.can('manage schedule'))
const canTrainers = computed(() => canStudents.value || auth.can('export trainer attendance'))

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const now = new Date()
const filters = reactive({ from: ymd(new Date(now.getFullYear(), now.getMonth(), 1)), to: ymd(now), class_group_id: '' })
const tab = ref(canStudents.value ? 'students' : 'trainers')
const classes = ref([])
const loading = ref(true)
const error = ref('')
const exporting = ref('')
const inputCls = 'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'

const periodValid = computed(() => !!filters.from && !!filters.to && filters.from <= filters.to)
const params = () => ({ from: filters.from, to: filters.to, class_group_id: filters.class_group_id || undefined })

const recap = ref({ rows: [], sessions: 0 })
const trainers = ref({ rows: [], summary: [] })
const missing = ref([])

const statusLabel = { hadir: 'Hadir', terlambat: 'Terlambat', tidak_hadir: 'Tidak hadir', belum_check_in: 'Belum check-in', belum_dicatat: 'Belum dicatat' }
const statusCls = {
  hadir: 'bg-primary-container/10 text-primary', terlambat: 'bg-secondary-container text-on-secondary-container',
  tidak_hadir: 'bg-error-container text-on-error-container', belum_check_in: 'bg-error-container text-on-error-container',
  belum_dicatat: 'bg-surface-container text-on-surface-variant',
}
const fmtDate = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
const fmtTime = (iso) => (iso ? new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }) : '')

async function load() {
  if (tab.value !== 'missing' && !periodValid.value) return
  loading.value = true
  error.value = ''
  try {
    if (tab.value === 'students') {
      const { data } = await api.get('/regular-attendance/recap', { params: params() })
      recap.value = { rows: data.data, sessions: data.meta.sessions }
    } else if (tab.value === 'trainers') {
      const { data } = await api.get('/regular-attendance/trainers', { params: params() })
      trainers.value = { rows: data.data, summary: data.summary }
    } else {
      const { data } = await api.get('/regular-attendance/trainers/missing-today')
      missing.value = data.data
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat rekap.')
  } finally {
    loading.value = false
  }
}

async function loadClasses() {
  try {
    const { data } = await api.get('/class-groups', { params: { per_page: 100 } })
    classes.value = unwrap(data)
  } catch (err) {
    // non-fatal: Keuangan tidak punya akses daftar kelas; filter kelas disembunyikan
  }
}

async function download(path, filename) {
  exporting.value = path
  error.value = ''
  try {
    const res = await api.get(path, { params: params(), responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const m = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '')
    link.download = m ? m[1] : filename
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    error.value = err?.response?.status === 403 ? 'Tidak punya izin export.' : 'Gagal mengunduh berkas.'
  } finally {
    exporting.value = ''
  }
}

// --- Riwayat satu siswa ---
const history = ref(null) // { student, rows }
const historyLoading = ref(false)
async function openHistory(row) {
  history.value = { student: row.student, rows: [] }
  historyLoading.value = true
  try {
    const { data } = await api.get(`/regular-attendance/students/${row.student.id}`, { params: { from: filters.from, to: filters.to } })
    history.value = { student: data.student, rows: data.data }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat riwayat siswa.')
    history.value = null
  } finally {
    historyLoading.value = false
  }
}

watch(tab, load)
watch(filters, load)
onMounted(() => {
  if (canStudents.value) loadClasses()
  load()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Kelas dan Penjadwalan</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Rekap Absensi</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Kehadiran siswa dan trainer pada kelas reguler, sesuai cabang yang Anda akses.</p>
    </div>

    <div class="mb-space-md flex flex-wrap gap-space-xs">
      <button v-if="canStudents" :class="['rounded-full px-space-md py-1.5 font-label-lg text-label-lg', tab === 'students' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface']" @click="tab = 'students'">Siswa</button>
      <button v-if="canTrainers" :class="['rounded-full px-space-md py-1.5 font-label-lg text-label-lg', tab === 'trainers' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface']" @click="tab = 'trainers'">Trainer</button>
      <button v-if="canStudents" :class="['rounded-full px-space-md py-1.5 font-label-lg text-label-lg', tab === 'missing' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface']" @click="tab = 'missing'">Belum check-in hari ini</button>
    </div>

    <div v-if="tab !== 'missing'" class="mb-space-md flex flex-wrap items-center gap-space-sm">
      <input v-model="filters.from" type="date" :class="inputCls" aria-label="Dari tanggal" />
      <input v-model="filters.to" type="date" :class="inputCls" aria-label="Sampai tanggal" />
      <select v-if="tab === 'students' && classes.length" v-model="filters.class_group_id" :class="inputCls">
        <option value="">Semua kelas</option>
        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <div class="flex gap-space-xs sm:ml-auto">
        <template v-if="tab === 'students'">
          <button :disabled="!periodValid || !!exporting" class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container-low disabled:opacity-60" @click="download('/exports/regular-attendance/excel', 'rekap-absensi-siswa.xlsx')">Excel</button>
          <button :disabled="!periodValid || !!exporting" class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container-low disabled:opacity-60" @click="download('/exports/regular-attendance/pdf', 'rekap-absensi-siswa.pdf')">PDF</button>
        </template>
        <button v-else :disabled="!periodValid || !!exporting" class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container-low disabled:opacity-60" @click="download('/exports/regular-attendance/trainers/excel', 'rekap-absensi-trainer.xlsx')">Excel</button>
      </div>
    </div>
    <p v-if="!periodValid && tab !== 'missing'" class="mb-space-sm font-body-sm text-body-sm text-error">Tanggal akhir tidak boleh sebelum tanggal awal.</p>
    <p v-if="error" class="mb-space-sm font-body-sm text-body-sm text-error">{{ error }}</p>

    <div v-if="loading" class="space-y-space-sm"><div v-for="i in 4" :key="i" class="h-12 animate-pulse rounded-xl bg-surface-container"></div></div>

    <!-- Siswa -->
    <template v-else-if="tab === 'students'">
      <p class="mb-space-xs font-body-sm text-body-sm text-on-surface-variant">{{ recap.sessions }} sesi tercatat pada periode ini. Sesi yang belum diabsen trainer tidak dihitung.</p>
      <div v-if="!recap.rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">Tidak ada data absensi pada periode ini.</div>
      <div v-else class="overflow-x-auto rounded-2xl bg-surface-container-lowest shadow-sm">
        <table class="w-full min-w-[520px] text-left">
          <thead><tr class="border-b border-surface-container font-label-md text-label-md text-on-surface-variant"><th class="px-space-md py-space-xs">Kelas</th><th class="px-space-md py-space-xs">Siswa</th><th class="px-space-md py-space-xs text-right">Hadir</th><th class="px-space-md py-space-xs text-right">Kehadiran</th></tr></thead>
          <tbody>
            <tr v-for="r in recap.rows" :key="`${r.class_group.id}-${r.student.id}`" class="cursor-pointer border-b border-surface-container last:border-0 hover:bg-surface-container-low" @click="openHistory(r)">
              <td class="px-space-md py-space-xs font-body-md text-body-md text-on-surface-variant">{{ r.class_group.name }}</td>
              <td class="px-space-md py-space-xs font-body-md text-body-md text-on-surface">{{ r.student.name }}</td>
              <td class="px-space-md py-space-xs text-right font-body-md text-body-md text-on-surface">{{ r.present }}/{{ r.sessions }}</td>
              <td class="px-space-md py-space-xs text-right font-label-lg text-label-lg font-semibold text-on-surface">{{ r.percentage }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Trainer -->
    <template v-else-if="tab === 'trainers'">
      <div v-if="trainers.summary.length" class="mb-space-md grid gap-space-sm sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="s in trainers.summary" :key="s.trainer.id" class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
          <div class="font-label-lg text-label-lg font-semibold text-on-surface">{{ s.trainer.name }}</div>
          <div class="font-body-sm text-body-sm text-on-surface-variant">{{ s.sessions }} sesi • Hadir {{ s.hadir }} • Terlambat {{ s.terlambat }} • Tidak hadir {{ s.tidak_hadir }} • Belum check-in {{ s.belum_check_in }}</div>
        </div>
      </div>
      <div v-if="!trainers.rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">Tidak ada sesi pada periode ini.</div>
      <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
        <div v-for="r in trainers.rows" :key="r.session_id" class="flex flex-col gap-space-xs border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center">
          <div class="flex flex-col gap-0.5">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ fmtDate(r.date) }} • {{ r.start_time }}–{{ r.end_time }} • {{ r.class_group.name }}</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ r.trainer.name }}{{ r.branch ? ` • ${r.branch.name}` : '' }}{{ r.check_in_at ? ` • check-in ${fmtTime(r.check_in_at)}` : '' }}</span>
          </div>
          <div class="flex items-center gap-space-xs sm:ml-auto">
            <a v-if="r.photo_url" :href="r.photo_url" target="_blank" rel="noopener" class="font-label-md text-label-md text-primary">Bukti</a>
            <a v-if="r.latitude !== null" :href="`https://www.google.com/maps?q=${r.latitude},${r.longitude}`" target="_blank" rel="noopener" class="font-label-md text-label-md text-primary">GPS</a>
            <span :class="['rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold', statusCls[r.status]]">{{ statusLabel[r.status] }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Belum check-in hari ini -->
    <template v-else>
      <div class="mb-space-sm flex items-center justify-between">
        <span class="font-body-sm text-body-sm text-on-surface-variant">Sesi hari ini yang sudah mulai tetapi pelaksananya belum check-in.</span>
        <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container" @click="load">Muat ulang</button>
      </div>
      <div v-if="!missing.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">Semua trainer sudah check-in.</div>
      <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
        <div v-for="r in missing" :key="r.session_id" class="border-b border-surface-container px-space-md py-space-sm last:border-0">
          <div class="font-label-lg text-label-lg font-semibold text-on-surface">{{ r.trainer.name }}</div>
          <div class="font-body-sm text-body-sm text-on-surface-variant">{{ r.start_time }}–{{ r.end_time }} • {{ r.class_group.name }}{{ r.branch ? ` • ${r.branch.name}` : '' }}</div>
        </div>
      </div>
    </template>

    <!-- Riwayat siswa -->
    <div v-if="history" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="history = null">
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ history.student.name }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="history = null"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="flex-1 overflow-y-auto p-space-lg">
          <div v-if="historyLoading" class="h-16 animate-pulse rounded-xl bg-surface-container"></div>
          <p v-else-if="!history.rows.length" class="font-body-md text-body-md text-on-surface-variant">Tidak ada sesi pada periode ini.</p>
          <div v-for="h in history.rows" :key="h.session_id" class="flex items-center gap-space-sm border-b border-surface-container py-space-xs last:border-0">
            <div class="flex-1">
              <div class="font-body-md text-body-md text-on-surface">{{ fmtDate(h.date) }} • {{ h.start_time }}–{{ h.end_time }}</div>
              <div class="font-body-sm text-body-sm text-on-surface-variant">{{ h.class_group.name }} • Pertemuan ke-{{ h.meeting_no }}{{ h.note ? ` • ${h.note}` : '' }}</div>
            </div>
            <span :class="['rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold', statusCls[h.status]]">{{ statusLabel[h.status] }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
