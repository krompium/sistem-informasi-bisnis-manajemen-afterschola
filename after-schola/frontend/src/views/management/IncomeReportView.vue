<script setup>
// Laporan Pemasukan (Modul 5, KEU-07) — payments terkonfirmasi per periode, cabang, dan program.
// Export Excel dan PDF memakai filter yang sama; scope cabang dijaga backend.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { formatDateShort, formatRupiah } from '@/lib/format'

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

const loading = ref(true)
const report = ref(null)
const exporting = ref('')
const filters = reactive({ from: '', to: '', branch_id: '', program_id: '' })

const params = () => ({
  from: filters.from || undefined,
  to: filters.to || undefined,
  branch_id: filters.branch_id || undefined,
  program_id: filters.program_id || undefined,
})

const errMsg = (err, fallback) => {
  const errors = err.response?.data?.errors
  return (errors && Object.values(errors).flat()[0]) || err.response?.data?.message || fallback
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/finance/income', { params: params() })
    report.value = data.data
    // Periode default dari server (awal bulan sampai hari ini) ditampilkan di filter.
    filters.from = data.data.period.from
    filters.to = data.data.period.to
  } catch (err) {
    alert(errMsg(err, 'Gagal memuat laporan pemasukan.'))
  } finally {
    loading.value = false
  }
}

async function download(type) {
  exporting.value = type
  try {
    const res = await api.get(`/exports/income/${type}`, { params: params(), responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const m = /filename="?([^"]+)"?/.exec(res.headers['content-disposition'] || '')
    link.download = m ? m[1] : `pemasukan.${type === 'excel' ? 'xlsx' : 'pdf'}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    alert('Gagal mengunduh berkas.')
  } finally {
    exporting.value = ''
  }
}

const methodLabel = (m) => ({ transfer: 'Transfer', tunai: 'Tunai' })[m] || m

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Pendaftaran & Pembayaran</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Laporan Pemasukan</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Pembayaran yang sudah dikonfirmasi pada periode terpilih.</p>
    </div>

    <form class="flex flex-wrap items-end gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm" @submit.prevent="load">
      <div class="flex flex-col gap-1">
        <label :class="labelCls" for="income-from">Dari</label>
        <input id="income-from" v-model="filters.from" type="date" :class="inputCls" />
      </div>
      <div class="flex flex-col gap-1">
        <label :class="labelCls" for="income-to">Sampai</label>
        <input id="income-to" v-model="filters.to" type="date" :class="inputCls" />
      </div>
      <div class="flex flex-col gap-1">
        <label :class="labelCls" for="income-branch">Cabang</label>
        <select id="income-branch" v-model="filters.branch_id" :class="inputCls">
          <option value="">Semua cabang</option>
          <option v-for="b in report?.options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label :class="labelCls" for="income-program">Program</label>
        <select id="income-program" v-model="filters.program_id" :class="inputCls">
          <option value="">Semua program</option>
          <option v-for="p in report?.options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
      <button type="submit" class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95">
        Tampilkan
      </button>
      <div class="ml-auto flex gap-space-xs">
        <button type="button" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low disabled:opacity-60" :disabled="!!exporting" @click="download('excel')">
          {{ exporting === 'excel' ? 'Mengunduh…' : 'Excel' }}
        </button>
        <button type="button" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low disabled:opacity-60" :disabled="!!exporting" @click="download('pdf')">
          {{ exporting === 'pdf' ? 'Mengunduh…' : 'PDF' }}
        </button>
      </div>
    </form>

    <div v-if="loading && !report" class="mt-space-md h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else-if="report">
      <div class="mt-space-md grid gap-space-sm md:grid-cols-3">
        <div class="flex flex-col gap-1 rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm md:col-span-1">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Total pemasukan</span>
          <span class="font-headline-md text-headline-md font-bold text-on-surface">{{ formatRupiah(report.total) }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ report.count }} pembayaran</span>
        </div>
        <div class="flex flex-col gap-space-2xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Per cabang</span>
          <p v-if="!report.by_branch.length" class="font-body-sm text-body-sm text-on-surface-variant">Belum ada pemasukan.</p>
          <div v-for="b in report.by_branch" :key="b.branch_id ?? 'none'" class="flex justify-between gap-space-sm font-body-sm text-body-sm">
            <span class="text-on-surface">{{ b.branch_name }} <span class="text-on-surface-variant">({{ b.count }})</span></span>
            <b class="text-on-surface">{{ formatRupiah(b.total) }}</b>
          </div>
        </div>
        <div class="flex flex-col gap-space-2xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Per program</span>
          <p v-if="!report.by_program.length" class="font-body-sm text-body-sm text-on-surface-variant">Belum ada pemasukan.</p>
          <div v-for="p in report.by_program" :key="p.program_id ?? 'none'" class="flex justify-between gap-space-sm font-body-sm text-body-sm">
            <span class="text-on-surface">{{ p.program_name }} <span class="text-on-surface-variant">({{ p.count }})</span></span>
            <b class="text-on-surface">{{ formatRupiah(p.total) }}</b>
          </div>
        </div>
      </div>

      <div v-if="!report.rows.length" class="mt-space-md rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
        Tidak ada pembayaran terkonfirmasi pada periode ini.
      </div>
      <div v-else class="mt-space-md flex flex-col gap-space-xs">
        <div v-for="row in report.rows" :key="row.id" class="flex flex-wrap items-center justify-between gap-space-sm rounded-xl bg-surface-container-lowest px-space-lg py-space-md shadow-sm">
          <div class="flex flex-col">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ row.student_name }}</span>
            <span class="font-label-sm text-label-sm text-on-surface-variant">
              {{ row.invoice_number }} • {{ row.program_name || '-' }} • {{ row.branch_name || '-' }}
            </span>
          </div>
          <div class="flex items-center gap-space-md font-body-sm text-body-sm text-on-surface-variant">
            <span>{{ formatDateShort(row.confirmed_at) }} • {{ methodLabel(row.method) }}</span>
            <b class="text-on-surface">{{ formatRupiah(row.amount) }}</b>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
