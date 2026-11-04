<script setup>
// Raport anak (ORT-11): daftar raport yang sudah terkirim per periode, skor per kriteria rubrik, dan unduh PDF.
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/lib/api'
import { errMsg } from './portalCommon'

const route = useRoute()
const reports = ref([])
const loading = ref(true)
const loadError = ref('')
const downloadError = ref('')

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get(`/parent/children/${route.params.id}/reports`)
    reports.value = data.data
  } catch (err) {
    loadError.value = err.response?.status === 404 ? 'Data anak tidak ditemukan.' : errMsg(err, 'Gagal memuat raport.')
  } finally {
    loading.value = false
  }
}

async function downloadPdf(report) {
  downloadError.value = ''
  try {
    const res = await api.get(`/parent/reports/${report.id}/pdf`, { responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    link.download = `raport-${report.enrollment.student.name}-${report.period}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    downloadError.value = 'Gagal mengunduh raport.'
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="pt-space-lg">
      <RouterLink :to="`/portal/anak/${route.params.id}`" class="font-label-lg text-label-lg text-secondary hover:underline">← Kembali ke detail anak</RouterLink>
    </div>

    <h1 class="py-space-lg font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Raport</h1>

    <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    <p v-else-if="!reports.length" class="rounded-2xl bg-surface-container-lowest p-space-lg font-body-md text-body-md text-on-surface-variant shadow-sm">
      Belum ada raport yang terbit. Raport muncul di sini setelah ditinjau koordinator program.
    </p>

    <template v-else>
      <p v-if="downloadError" class="mb-space-sm rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-on-error-container">{{ downloadError }}</p>
      <section v-for="report in reports" :key="report.id" class="mb-space-md flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-space-sm">
          <div class="flex flex-col gap-space-2xs">
            <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Periode {{ report.period }}</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">{{ report.enrollment.student.name }} • {{ report.enrollment.class_group.name }}</p>
          </div>
          <button
            class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low"
            @click="downloadPdf(report)"
          >
            Unduh PDF
          </button>
        </div>
        <div class="grid gap-space-xs sm:grid-cols-2">
          <div v-for="criterion in report.criteria" :key="criterion.id" class="flex items-center justify-between rounded-xl bg-surface-container-low px-space-md py-space-sm">
            <span class="font-label-lg text-label-lg text-on-surface">{{ criterion.name }}</span>
            <b class="font-label-lg text-label-lg text-on-surface">{{ criterion.score ?? '-' }} / {{ criterion.max_score }}</b>
          </div>
        </div>
        <div class="flex flex-col gap-1">
          <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Ringkasan trainer</span>
          <p class="font-body-md text-body-md text-on-surface">{{ report.summary }}</p>
        </div>
      </section>
    </template>
  </div>
</template>
