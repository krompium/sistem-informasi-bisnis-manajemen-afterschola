<script setup>
// Umur Piutang (Modul 5) — invoice belum lunas dikelompokkan menurut hari keterlambatan dari jatuh tempo.
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatDate, formatRupiah } from '@/lib/format'
import { toast } from '@/lib/feedback'

const loading = ref(true)
const report = ref(null)

const buckets = [
  { key: 'belum_jatuh_tempo', label: 'Belum jatuh tempo' },
  { key: 'd1_30', label: 'Telat 1–30 hari' },
  { key: 'd31_60', label: 'Telat 31–60 hari' },
  { key: 'd61_90', label: 'Telat 61–90 hari' },
  { key: 'd90_plus', label: 'Telat > 90 hari' },
]

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/finance/aging')
    report.value = data.data
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal memuat umur piutang.')
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Pendaftaran & Pembayaran</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Umur Piutang</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Invoice yang belum lunas, dikelompokkan menurut keterlambatan.</p>
    </div>

    <div v-if="loading" class="grid gap-space-sm sm:grid-cols-2 lg:grid-cols-5">
      <div v-for="i in 5" :key="i" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else-if="report">
      <div class="grid gap-space-sm sm:grid-cols-2 lg:grid-cols-5">
        <div v-for="b in buckets" :key="b.key" class="flex flex-col gap-1 rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">{{ b.label }}</span>
          <span class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ formatRupiah(report.buckets[b.key].total) }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ report.buckets[b.key].count }} invoice</span>
        </div>
      </div>

      <p class="mt-space-md font-body-md text-body-md text-on-surface">
        Total piutang <b>{{ formatRupiah(report.total.total) }}</b> dari {{ report.total.count }} invoice.
      </p>

      <div v-if="!report.invoices.length" class="mt-space-md rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
        Tidak ada piutang.
      </div>
      <div v-else class="mt-space-md flex flex-col gap-space-xs">
        <div v-for="inv in report.invoices" :key="inv.id" class="flex flex-wrap items-center justify-between gap-space-sm rounded-xl bg-surface-container-lowest px-space-lg py-space-md shadow-sm">
          <div class="flex flex-col">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ inv.student_name }}</span>
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ inv.number }} • Wali: {{ inv.parent_name }} ({{ inv.parent_phone }})</span>
          </div>
          <div class="flex items-center gap-space-md font-body-sm text-body-sm text-on-surface-variant">
            <span>Jatuh tempo {{ formatDate(inv.due_date) }}</span>
            <span v-if="inv.is_overdue" class="rounded-full bg-error-container px-2.5 py-1 font-label-sm text-label-sm font-semibold text-on-error-container">{{ inv.days_overdue }} hari</span>
            <b class="text-on-surface">{{ formatRupiah(inv.amount) }}</b>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
