<script setup>
// Dashboard Manajemen Bisnis jalur reguler (MGT-60 sampai 62): KPI, funnel, dan panel operasional.
// Data dari GET /api/dashboard/kpi, /funnel, /panel; scope cabang dan permission dijaga backend.
// Dashboard sekolah lama (ekstrakurikuler) pindah ke /management/ekstrakurikuler.
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'
const cardCls = 'flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm'

const loading = ref(true)
const loadError = ref('')
const downloadError = ref('')
const kpi = ref(null)
const funnel = ref([])
const panel = ref(null)
const filters = reactive({ from: '', to: '', branch_id: '' })

const canView = computed(() => auth.can('view dashboard'))
const percent = (metric) => (metric.value === null ? '–' : `${metric.value}%`)
const funnelMax = computed(() => Math.max(1, ...funnel.value.map((stage) => stage.count)))

async function load() {
  loading.value = true
  loadError.value = ''
  const period = { from: filters.from || undefined, to: filters.to || undefined, branch_id: filters.branch_id || undefined }
  try {
    const [kpiRes, funnelRes, panelRes] = await Promise.all([
      api.get('/dashboard/kpi', { params: period }),
      api.get('/dashboard/funnel', { params: period }),
      api.get('/dashboard/panel', { params: { branch_id: period.branch_id } }),
    ])
    kpi.value = kpiRes.data.data
    funnel.value = funnelRes.data.data.stages
    panel.value = panelRes.data.data
    // Periode default dari server (awal bulan sampai hari ini) ditampilkan di filter.
    filters.from = kpi.value.period.from
    filters.to = kpi.value.period.to
  } catch (err) {
    const errors = err.response?.data?.errors
    loadError.value = (errors && Object.values(errors).flat()[0]) || err.response?.data?.message || 'Gagal memuat dashboard.'
  } finally {
    loading.value = false
  }
}

// MGT-63: unduh laporan sesuai filter yang sedang tampil.
async function download(kind) {
  downloadError.value = ''
  const ext = kind === 'excel' ? 'xlsx' : 'pdf'
  try {
    const res = await api.get(`/exports/dashboard/${kind}`, {
      params: { from: filters.from || undefined, to: filters.to || undefined, branch_id: filters.branch_id || undefined },
      responseType: 'blob',
    })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(res.data)
    link.download = `dashboard-${filters.from}-${filters.to}.${ext}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    downloadError.value = 'Gagal mengunduh laporan.'
  }
}

onMounted(() => {
  if (canView.value) load()
  else loading.value = false
})
</script>

<template>
  <div class="flex w-full flex-col gap-space-lg pb-space-3xl">
    <h1 class="pt-space-lg font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Dashboard Manajemen Bisnis</h1>

    <p v-if="!canView" class="rounded-2xl bg-surface-container-lowest p-space-lg font-body-md text-body-md text-on-surface-variant shadow-sm">
      Akun Anda belum punya akses dashboard bisnis. Hubungi admin sistem untuk meminta akses, atau buka menu di samping untuk pekerjaan harian Anda.
    </p>

    <template v-else>
      <div class="flex flex-wrap items-end gap-space-sm">
        <label class="flex flex-col gap-1"><span :class="labelCls">Dari</span><input v-model="filters.from" type="date" :class="inputCls" /></label>
        <label class="flex flex-col gap-1"><span :class="labelCls">Sampai</span><input v-model="filters.to" type="date" :class="inputCls" /></label>
        <label class="flex flex-col gap-1">
          <span :class="labelCls">Cabang</span>
          <select v-model="filters.branch_id" :class="inputCls">
            <option value="">Semua cabang saya</option>
            <option v-for="branch in kpi?.branches ?? []" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
          </select>
        </label>
        <button class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95" @click="load">
          Terapkan
        </button>
        <button class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low" @click="download('excel')">
          Unduh Excel
        </button>
        <button class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low" @click="download('pdf')">
          Unduh PDF
        </button>
      </div>

      <p v-if="downloadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ downloadError }}</p>
      <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
      <div v-else-if="loading" class="grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="n in 4" :key="n" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
      </div>

      <template v-else-if="kpi && panel">
        <!-- KPI (MGT-60) -->
        <section class="grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
          <div :class="cardCls">
            <span :class="labelCls">Siswa aktif</span>
            <b class="font-headline-lg text-headline-lg text-on-surface">{{ kpi.students_active.value }}</b>
            <span class="font-body-sm text-body-sm text-on-surface-variant">per {{ kpi.period.to }}</span>
          </div>
          <div :class="cardCls">
            <span :class="labelCls">Income</span>
            <b class="font-headline-lg text-headline-lg text-on-surface">{{ formatRupiah(kpi.income.value) }}</b>
            <span class="font-body-sm text-body-sm text-on-surface-variant">pembayaran terkonfirmasi di periode</span>
          </div>
          <div :class="cardCls">
            <span :class="labelCls">Retensi</span>
            <b class="font-headline-lg text-headline-lg text-on-surface">{{ percent(kpi.retention) }}</b>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ kpi.retention.numerator }} dari {{ kpi.retention.denominator }} siswa yang berakhir</span>
          </div>
          <div :class="cardCls">
            <span :class="labelCls">Okupansi kelas</span>
            <b class="font-headline-lg text-headline-lg text-on-surface">{{ percent(kpi.occupancy) }}</b>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ kpi.occupancy.numerator }} dari {{ kpi.occupancy.denominator }} kursi</span>
          </div>
        </section>

        <!-- Panel operasional (MGT-62) -->
        <section :class="cardCls">
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Operasional hari ini</h2>
          <div class="grid grid-cols-1 gap-space-sm sm:grid-cols-2 lg:grid-cols-5">
            <div class="flex flex-col rounded-xl bg-surface-container-low px-space-md py-space-sm">
              <span :class="labelCls">Sesi hari ini</span>
              <b class="font-headline-md text-headline-md text-on-surface">{{ panel.sessions_today }}</b>
              <span class="font-body-sm text-body-sm text-on-surface-variant">{{ panel.students_present_today }} siswa hadir</span>
            </div>
            <RouterLink to="/management/verifikasi-pendaftaran" class="flex flex-col rounded-xl bg-surface-container-low px-space-md py-space-sm hover:bg-surface-container">
              <span :class="labelCls">Menunggu verifikasi</span>
              <b class="font-headline-md text-headline-md text-on-surface">{{ panel.registrations_waiting }}</b>
              <span class="font-body-sm text-body-sm text-on-surface-variant">pendaftaran</span>
            </RouterLink>
            <RouterLink to="/management/invoice" class="flex flex-col rounded-xl bg-surface-container-low px-space-md py-space-sm hover:bg-surface-container">
              <span :class="labelCls">Menunggu konfirmasi</span>
              <b class="font-headline-md text-headline-md text-on-surface">{{ panel.payments_waiting }}</b>
              <span class="font-body-sm text-body-sm text-on-surface-variant">pembayaran</span>
            </RouterLink>
            <RouterLink to="/management/umur-piutang" class="flex flex-col rounded-xl bg-surface-container-low px-space-md py-space-sm hover:bg-surface-container">
              <span :class="labelCls">Invoice overdue</span>
              <b class="font-headline-md text-headline-md text-on-surface">{{ panel.invoices_overdue }}</b>
              <span class="font-body-sm text-body-sm text-on-surface-variant">lewat jatuh tempo</span>
            </RouterLink>
          </div>
        </section>

        <!-- Funnel (MGT-61) -->
        <section :class="cardCls">
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Funnel pendaftaran</h2>
          <div v-for="stage in funnel" :key="stage.key" class="flex items-center gap-space-sm">
            <span class="w-44 shrink-0 font-label-lg text-label-lg text-on-surface sm:w-56">{{ stage.label }}</span>
            <div class="h-6 flex-1 rounded-lg bg-surface-container-low">
              <div class="h-6 rounded-lg bg-primary-container" :style="{ width: Math.max(2, (stage.count / funnelMax) * 100) + '%' }"></div>
            </div>
            <b class="w-10 shrink-0 text-right font-label-lg text-label-lg text-on-surface">{{ stage.count }}</b>
          </div>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Tiap tahap dihitung menurut tanggal kejadiannya di periode terpilih.</p>
        </section>
      </template>
    </template>
  </div>
</template>
