<script setup>
// Panel rekap absensi satu sekolah: matriks murid × pertemuan per level + export.
import { computed, ref, watch } from 'vue'
import api from '@/lib/api'
import { formatDateShort, initials } from '@/lib/format'

const props = defineProps({
  schoolId: { type: [Number, String], required: true },
})

const recap = ref(null)
const loading = ref(false)
const error = ref('')
const levelFilter = ref('')
const from = ref('')
const to = ref('')
const exporting = ref('')
const exportError = ref('')

const visibleGroups = computed(() => {
  if (!recap.value) return []
  if (!levelFilter.value) return recap.value.groups
  return recap.value.groups.filter((g) => String(g.classroom.id) === String(levelFilter.value))
})
const totalStudents = computed(() =>
  (recap.value?.groups || []).reduce((n, g) => n + g.students.length, 0),
)
const totalSessions = computed(() =>
  (recap.value?.groups || []).reduce((n, g) => n + g.sessions.length, 0),
)

function isPresent(group, sessionId, studentId) {
  return !!group.present?.[sessionId]?.[studentId]
}
function studentPresentCount(group, studentId) {
  return group.sessions.reduce((n, s) => n + (isPresent(group, s.id, studentId) ? 1 : 0), 0)
}

async function loadRecap() {
  loading.value = true
  error.value = ''
  try {
    const params = { school_id: props.schoolId }
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value
    const { data } = await api.get('/exports/attendance/recap', { params })
    recap.value = data
  } catch (err) {
    error.value =
      err?.response?.status === 403
        ? 'Anda tidak berwenang melihat rekap sekolah ini.'
        : 'Gagal memuat rekap absensi.'
    recap.value = null
  } finally {
    loading.value = false
  }
}

async function downloadExport(type) {
  exporting.value = type
  exportError.value = ''
  try {
    const params = { school_id: props.schoolId }
    if (levelFilter.value) params.classroom_id = levelFilter.value
    if (from.value) params.from = from.value
    if (to.value) params.to = to.value
    const url = type === 'excel' ? '/exports/attendance/excel' : '/exports/attendance/pdf'
    const res = await api.get(url, { params, responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const cd = res.headers['content-disposition'] || ''
    const m = /filename="?([^"]+)"?/.exec(cd)
    link.download = m ? m[1] : `rekap-absensi.${type === 'excel' ? 'xlsx' : 'pdf'}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    exportError.value =
      err?.response?.status === 403 ? 'Tidak punya izin export.' : 'Gagal mengunduh berkas.'
  } finally {
    exporting.value = ''
  }
}

watch(() => props.schoolId, loadRecap, { immediate: true })
watch([from, to], loadRecap)

defineExpose({ recap })
</script>

<template>
  <div class="flex flex-col">
    <!-- Toolbar -->
    <div class="mb-space-md flex flex-col gap-space-md rounded-2xl bg-surface-container-lowest p-space-md shadow-sm lg:flex-row lg:items-end lg:justify-between">
      <div class="flex flex-col gap-space-sm sm:flex-row sm:items-end">
        <label class="flex flex-col gap-1">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Level</span>
          <select v-model="levelFilter" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
            <option value="">Semua level</option>
            <option v-for="g in recap?.groups || []" :key="g.classroom.id" :value="g.classroom.id">{{ g.classroom.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Dari</span>
          <input v-model="from" type="date" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant">Sampai</span>
          <input v-model="to" type="date" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
        </label>
      </div>
      <div class="flex items-center gap-space-sm">
        <button class="inline-flex items-center gap-space-xs rounded-xl bg-[#065F46] px-space-md py-2.5 font-label-lg text-label-lg text-white shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60" :disabled="exporting === 'excel' || !totalSessions" @click="downloadExport('excel')">
          <span class="material-symbols-outlined text-[18px]" :class="exporting === 'excel' ? 'animate-spin' : ''">{{ exporting === 'excel' ? 'progress_activity' : 'table_view' }}</span>
          Excel
        </button>
        <button class="inline-flex items-center gap-space-xs rounded-xl bg-error px-space-md py-2.5 font-label-lg text-label-lg text-on-error shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60" :disabled="exporting === 'pdf' || !totalSessions" @click="downloadExport('pdf')">
          <span class="material-symbols-outlined text-[18px]" :class="exporting === 'pdf' ? 'animate-spin' : ''">{{ exporting === 'pdf' ? 'progress_activity' : 'picture_as_pdf' }}</span>
          PDF
        </button>
      </div>
    </div>

    <p v-if="exportError" class="mb-space-sm font-body-sm text-body-sm text-error">{{ exportError }}</p>

    <div class="mb-space-md flex flex-wrap items-center gap-space-md font-body-sm text-body-sm text-on-surface-variant">
      <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">groups</span>{{ totalStudents }} murid</span>
      <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">event</span>{{ totalSessions }} pertemuan</span>
      <span class="inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px] text-primary">date_range</span>{{ recap?.period || '—' }}</span>
    </div>

    <div v-if="loading" class="h-64 animate-pulse rounded-2xl bg-surface-container"></div>
    <div v-else-if="error" class="flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-md text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ error }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadRecap">Coba lagi</button>
    </div>

    <template v-else>
      <div v-for="g in visibleGroups" :key="g.classroom.id" class="mb-space-lg rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <div class="mb-space-sm flex items-center justify-between">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Level: {{ g.classroom.name }}</h2>
          <span class="font-label-sm text-label-sm text-on-surface-variant">{{ g.students.length }} murid • {{ g.sessions.length }} pertemuan</span>
        </div>
        <div v-if="!g.sessions.length" class="py-space-lg text-center font-body-sm text-body-sm text-on-surface-variant">Belum ada pertemuan pada periode ini.</div>
        <div v-else-if="!g.students.length" class="py-space-lg text-center font-body-sm text-body-sm text-on-surface-variant">Belum ada murid pada level ini.</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full border-collapse text-left">
            <thead>
              <tr class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface">
                <th class="sticky left-0 z-10 bg-surface-container-low px-space-sm py-space-xs">Nama</th>
                <th class="bg-surface-container-low px-space-xs py-space-xs text-center">Kelas</th>
                <th v-for="s in g.sessions" :key="s.id" class="bg-surface-container-low px-space-xs py-space-xs text-center" :title="formatDateShort(s.date)">
                  P{{ s.meeting_no }}<br /><span class="font-body-sm text-[10px] font-normal text-outline">{{ formatDateShort(s.date).replace(/ \d{4}$/, '') }}</span>
                </th>
                <th class="bg-surface-container-low px-space-xs py-space-xs text-center">Σ</th>
              </tr>
            </thead>
            <tbody class="font-body-md text-body-md">
              <tr v-for="(st, i) in g.students" :key="st.id" class="border-b border-surface-container hover:bg-surface-container-low">
                <td class="sticky left-0 z-10 bg-surface-container-lowest px-space-sm py-space-xs">
                  <div class="flex items-center gap-space-xs">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-container-high text-[10px] font-bold text-primary">{{ initials(st.name) }}</span>
                    <span class="whitespace-nowrap">{{ i + 1 }}. {{ st.name }}</span>
                  </div>
                </td>
                <td class="px-space-xs py-space-xs text-center text-on-surface-variant">{{ st.origin_class || '—' }}</td>
                <td v-for="s in g.sessions" :key="s.id" class="px-space-xs py-space-xs text-center">
                  <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[13px] font-bold" :class="isPresent(g, s.id, st.id) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-outline'">{{ isPresent(g, s.id, st.id) ? 'H' : '–' }}</span>
                </td>
                <td class="px-space-xs py-space-xs text-center font-semibold text-primary">{{ studentPresentCount(g, st.id) }}</td>
              </tr>
              <tr class="bg-surface-container-low font-label-md text-label-md font-bold text-on-surface">
                <td class="sticky left-0 z-10 bg-surface-container-low px-space-sm py-space-xs">Jumlah Hadir</td>
                <td class="px-space-xs py-space-xs"></td>
                <td v-for="s in g.sessions" :key="s.id" class="px-space-xs py-space-xs text-center text-primary">{{ g.present_totals[s.id] || 0 }}</td>
                <td class="px-space-xs py-space-xs"></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!visibleGroups.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
        <span class="material-symbols-outlined text-[36px] text-outline">summarize</span>
        <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada data rekap</p>
      </div>
    </template>
  </div>
</template>
