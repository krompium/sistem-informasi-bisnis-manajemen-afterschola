<script setup>
// Rekap Saya (Modul 7d, TRN-11/12) — rekap kehadiran trainer sendiri dan riwayat absensi siswa di kelas saya.
// Mobile-first: satu kolom, tab besar. Data dibatasi backend ke sesi dan kelas milik trainer yang login.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const now = new Date()
const filters = reactive({ from: ymd(new Date(now.getFullYear(), now.getMonth(), 1)), to: ymd(now) })
const tab = ref('recap')
const loading = ref(true)
const error = ref('')
const exporting = ref(false)
const recap = ref({ rows: [], summary: null })
const history = ref([])

const periodValid = computed(() => !!filters.from && !!filters.to && filters.from <= filters.to)
const statusLabel = { hadir: 'Hadir', terlambat: 'Terlambat', tidak_hadir: 'Tidak hadir', belum_check_in: 'Belum check-in' }
const statusCls = {
  hadir: 'bg-primary-container/10 text-primary', terlambat: 'bg-secondary-container text-on-secondary-container',
  tidak_hadir: 'bg-error-container text-on-error-container', belum_check_in: 'bg-error-container text-on-error-container',
}
const fmtDate = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
const fmtTime = (iso) => (iso ? new Date(iso).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' }) : '')

async function load() {
  if (!periodValid.value) return
  loading.value = true
  error.value = ''
  try {
    if (tab.value === 'recap') {
      const { data } = await api.get('/trainer/recap', { params: { from: filters.from, to: filters.to } })
      recap.value = { rows: data.data, summary: data.summary[0] ?? null }
    } else {
      const { data } = await api.get('/trainer/class-history', { params: { from: filters.from, to: filters.to } })
      history.value = data.data
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat data.')
  } finally {
    loading.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  error.value = ''
  try {
    const res = await api.get('/exports/trainer/recap/excel', { params: { from: filters.from, to: filters.to }, responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const m = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '')
    link.download = m ? m[1] : 'rekap-absensi-saya.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    error.value = 'Gagal mengunduh berkas.'
  } finally {
    exporting.value = false
  }
}

watch([tab, filters], load)
onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-lg">
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Rekap Saya</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Kehadiran Anda sebagai trainer dan riwayat absensi siswa di kelas Anda.</p>
    </div>

    <div class="mb-space-md grid grid-cols-2 gap-space-xs">
      <button :class="['min-h-[44px] rounded-xl font-label-lg text-label-lg', tab === 'recap' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface']" @click="tab = 'recap'">Kehadiran Saya</button>
      <button :class="['min-h-[44px] rounded-xl font-label-lg text-label-lg', tab === 'history' ? 'bg-primary-container text-on-primary' : 'bg-surface-container text-on-surface']" @click="tab = 'history'">Riwayat Kelas</button>
    </div>

    <div class="mb-space-md grid grid-cols-2 gap-space-xs">
      <input v-model="filters.from" type="date" aria-label="Dari tanggal" class="min-h-[44px] rounded-lg border border-outline-variant bg-white px-space-sm font-body-md text-body-md text-on-surface" />
      <input v-model="filters.to" type="date" aria-label="Sampai tanggal" class="min-h-[44px] rounded-lg border border-outline-variant bg-white px-space-sm font-body-md text-body-md text-on-surface" />
    </div>
    <p v-if="!periodValid" class="mb-space-sm font-body-sm text-body-sm text-error">Tanggal akhir tidak boleh sebelum tanggal awal.</p>
    <p v-if="error" class="mb-space-sm font-body-sm text-body-sm text-error">{{ error }}</p>

    <div v-if="loading" class="space-y-space-sm"><div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-xl bg-surface-container"></div></div>

    <!-- Kehadiran saya -->
    <template v-else-if="tab === 'recap'">
      <div v-if="recap.summary" class="mb-space-md grid grid-cols-2 gap-space-xs sm:grid-cols-4">
        <div v-for="item in [['Sesi', recap.summary.sessions], ['Hadir', recap.summary.hadir], ['Terlambat', recap.summary.terlambat], ['Belum check-in', recap.summary.belum_check_in]]" :key="item[0]" class="rounded-2xl bg-surface-container-lowest p-space-md text-center shadow-sm">
          <div class="font-headline-md text-headline-md font-bold text-on-surface">{{ item[1] }}</div>
          <div class="font-body-sm text-body-sm text-on-surface-variant">{{ item[0] }}</div>
        </div>
      </div>
      <button :disabled="exporting || !periodValid" class="mb-space-md min-h-[44px] self-start rounded-xl border border-outline-variant px-space-md font-label-lg text-label-lg text-on-surface disabled:opacity-60" @click="exportExcel">
        {{ exporting ? 'Mengunduh...' : 'Unduh Excel' }}
      </button>

      <div v-if="!recap.rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">Tidak ada sesi Anda pada periode ini.</div>
      <div v-else class="flex flex-col gap-space-xs">
        <RouterLink v-for="r in recap.rows" :key="r.session_id" :to="`/trainer/sesi-kelas/${r.session_id}`" class="flex min-h-[56px] items-center gap-space-sm rounded-2xl bg-surface-container-lowest px-space-md py-space-sm shadow-sm">
          <div class="flex flex-1 flex-col gap-0.5">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ fmtDate(r.date) }} • {{ r.start_time }}–{{ r.end_time }}</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ r.class_group.name }}{{ r.check_in_at ? ` • check-in ${fmtTime(r.check_in_at)}` : '' }}</span>
          </div>
          <span :class="['rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold', statusCls[r.status]]">{{ statusLabel[r.status] }}</span>
        </RouterLink>
      </div>
    </template>

    <!-- Riwayat kelas -->
    <template v-else>
      <div v-if="!history.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">Belum ada sesi pada periode ini.</div>
      <div v-else class="flex flex-col gap-space-xs">
        <RouterLink v-for="h in history" :key="h.session_id" :to="`/trainer/sesi-kelas/${h.session_id}`" class="flex min-h-[56px] items-center gap-space-sm rounded-2xl bg-surface-container-lowest px-space-md py-space-sm shadow-sm">
          <div class="flex flex-1 flex-col gap-0.5">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ fmtDate(h.date) }} • {{ h.class_group.name }}</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">Pertemuan ke-{{ h.meeting_no }} • {{ h.trainer.name }}</span>
          </div>
          <span v-if="h.status === 'dicatat'" class="font-label-lg text-label-lg font-semibold text-on-surface">{{ h.present_count }}/{{ h.total }}</span>
          <span v-else class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-surface-variant">Belum dicatat</span>
          <span v-if="h.is_locked" class="material-symbols-outlined text-[18px] text-outline" title="Sesi dikunci">lock</span>
        </RouterLink>
      </div>
    </template>
  </div>
</template>
