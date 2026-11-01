<script setup>
// Laporan Pemasukan (Modul 5, KEU-07) — payment terkonfirmasi per periode, dirinci per cabang dan program.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { saveBlob } from '@/lib/download'
import { formatRupiah } from '@/lib/format'

const pad = (n) => String(n).padStart(2, '0')
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
const now = new Date()

const range = reactive({ from: ymd(new Date(now.getFullYear(), now.getMonth(), 1)), to: ymd(now) })
const loading = ref(true)
const exporting = ref('')
const error = ref('')
const report = ref(null)

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

const errMsg = (err, fallback) => {
  const errors = err.response?.data?.errors
  return (errors && Object.values(errors).flat()[0]) || err.response?.data?.message || fallback
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/finance/income', { params: range })
    report.value = data.data
  } catch (err) {
    report.value = null
    error.value = errMsg(err, 'Gagal memuat laporan pemasukan.')
  } finally {
    loading.value = false
  }
}

async function exportFile(type) {
  exporting.value = type
  error.value = ''
  try {
    const res = await api.get(`/exports/income/${type}`, { params: range, responseType: 'blob' })
    saveBlob(res, `pemasukan.${type === 'excel' ? 'xlsx' : 'pdf'}`)
  } catch (err) {
    error.value = err?.response?.status === 403 ? 'Tidak punya izin export.' : 'Gagal mengunduh berkas.'
  } finally {
    exporting.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Pendaftaran & Pembayaran</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Laporan Pemasukan</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Pembayaran yang sudah dikonfirmasi pada periode yang dipilih.</p>
    </div>

    <div class="flex flex-wrap items-end gap-space-sm">
      <label class="flex flex-col gap-1"><span :class="labelCls">Dari</span><input v-model="range.from" type="date" :class="inputCls" /></label>
      <label class="flex flex-col gap-1"><span :class="labelCls">Sampai</span><input v-model="range.to" type="date" :class="inputCls" /></label>
      <button class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm active:scale-95" @click="load">Tampilkan</button>
      <button class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface disabled:opacity-60" :disabled="!!exporting" @click="exportFile('excel')">
        {{ exporting === 'excel' ? 'Mengunduh…' : 'Export Excel' }}
      </button>
      <button class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface disabled:opacity-60" :disabled="!!exporting" @click="exportFile('pdf')">
        {{ exporting === 'pdf' ? 'Mengunduh…' : 'Export PDF' }}
      </button>
    </div>
    <p v-if="error" class="mt-space-sm font-body-sm text-body-sm text-error">{{ error }}</p>

    <div v-if="loading" class="mt-space-md h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else-if="report">
      <div class="mt-space-md flex flex-col gap-1 rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <span :class="labelCls">Total pemasukan</span>
        <span class="font-headline-lg text-headline-lg font-bold text-on-surface">{{ formatRupiah(report.total) }}</span>
        <span class="font-body-sm text-body-sm text-on-surface-variant">{{ report.count }} pembayaran</span>
      </div>

      <div class="mt-space-md grid gap-space-md lg:grid-cols-2">
        <div v-for="g in [{ title: 'Per cabang', rows: report.by_branch }, { title: 'Per program', rows: report.by_program }]" :key="g.title" class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <h2 class="mb-space-sm font-headline-sm text-headline-sm font-bold text-on-surface">{{ g.title }}</h2>
          <p v-if="!g.rows.length" class="font-body-sm text-body-sm text-on-surface-variant">Belum ada pemasukan pada periode ini.</p>
          <div v-for="row in g.rows" :key="row.id ?? 0" class="flex items-center justify-between border-t border-outline-variant/40 py-space-xs font-body-md text-body-md first:border-t-0">
            <span class="text-on-surface">{{ row.name }} <span class="text-on-surface-variant">({{ row.count }})</span></span>
            <b class="text-on-surface">{{ formatRupiah(row.total) }}</b>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
