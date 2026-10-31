<script setup>
// Laporan Pemasukan (Modul 5, KEU-07) — pembayaran terkonfirmasi per periode, dikelompokkan per program dan cabang.
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { formatRupiah } from '@/lib/format'

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const now = new Date()
const from = ref(ymd(new Date(now.getFullYear(), now.getMonth(), 1)))
const to = ref(ymd(now))
const loading = ref(true)
const report = ref(null)
const exporting = ref('')
const error = ref('')

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const periodValid = computed(() => !!from.value && !!to.value && from.value <= to.value)

async function load() {
  if (!periodValid.value) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/finance/income', { params: { from: from.value, to: to.value } })
    report.value = data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memuat laporan pemasukan.'
  } finally {
    loading.value = false
  }
}

// Unduh lewat axios supaya header Authorization ikut terkirim (pola sama dengan export lain).
async function download(kind) {
  exporting.value = kind
  error.value = ''
  try {
    const res = await api.get(`/exports/income/${kind}`, { params: { from: from.value, to: to.value }, responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const m = /filename="?([^";]+)"?/.exec(res.headers['content-disposition'] || '')
    link.download = m ? m[1] : `pemasukan.${kind === 'excel' ? 'xlsx' : 'pdf'}`
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

watch([from, to], load)
onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Pendaftaran & Pembayaran</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Laporan Pemasukan</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Pembayaran yang dikonfirmasi pada periode terpilih, sesuai cabang yang Anda akses.</p>
    </div>

    <div class="mb-space-md flex flex-col gap-space-sm sm:flex-row sm:items-center">
      <input v-model="from" type="date" :class="inputCls" aria-label="Dari tanggal" />
      <span class="hidden text-on-surface-variant sm:inline">s.d.</span>
      <input v-model="to" type="date" :class="inputCls" aria-label="Sampai tanggal" />
      <div class="flex gap-space-xs sm:ml-auto">
        <button :disabled="!periodValid || !!exporting" class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low disabled:opacity-60" @click="download('excel')">
          {{ exporting === 'excel' ? 'Mengunduh...' : 'Unduh Excel' }}
        </button>
        <button :disabled="!periodValid || !!exporting" class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low disabled:opacity-60" @click="download('pdf')">
          {{ exporting === 'pdf' ? 'Mengunduh...' : 'Unduh PDF' }}
        </button>
      </div>
    </div>

    <p v-if="!periodValid" class="mb-space-sm font-body-sm text-body-sm text-error">Tanggal akhir tidak boleh sebelum tanggal awal.</p>
    <p v-if="error" class="mb-space-sm font-body-sm text-body-sm text-error">{{ error }}</p>

    <div v-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else-if="report">
      <div class="flex flex-col gap-1 rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Total pemasukan</span>
        <span class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ formatRupiah(report.total) }}</span>
        <span class="font-body-sm text-body-sm text-on-surface-variant">{{ report.count }} pembayaran</span>
      </div>

      <div v-if="!report.count" class="mt-space-md rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
        Tidak ada pemasukan pada periode ini.
      </div>

      <div v-else class="mt-space-md grid gap-space-md lg:grid-cols-2">
        <section v-for="g in [{ title: 'Per program', rows: report.by_program, key: 'program' }, { title: 'Per cabang', rows: report.by_branch, key: 'branch' }]" :key="g.key" class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <h2 class="mb-space-sm font-label-lg text-label-lg font-semibold text-on-surface">{{ g.title }}</h2>
          <div v-for="row in g.rows" :key="row[g.key]" class="flex items-center justify-between gap-space-sm border-t border-outline-variant py-space-xs first:border-t-0">
            <span class="font-body-md text-body-md text-on-surface">{{ row[g.key] }} <span class="text-on-surface-variant">({{ row.count }})</span></span>
            <b class="font-body-md text-body-md text-on-surface">{{ formatRupiah(row.total) }}</b>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
