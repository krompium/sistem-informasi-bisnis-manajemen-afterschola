<script setup>
// Kalender proyek: task per hari menurut tenggat. Dipakai Developer (task team-nya) dan Management (semua).
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const route = useRoute()
const router = useRouter()
const base = route.path.startsWith('/management') ? '/management' : '/developer'

const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']
const MAX_PER_DAY = 3

// Tanggal lokal (bukan UTC) dalam format YYYY-MM-DD.
const ymd = (d) => d.toLocaleDateString('en-CA')
const today = ymd(new Date())

const firstOfThisMonth = () => new Date(new Date().getFullYear(), new Date().getMonth(), 1)
const cursor = ref(firstOfThisMonth())
const monthLabel = computed(() => cursor.value.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }))

// Grid tetap 6 minggu x 7 hari, mulai hari Senin.
const days = computed(() => {
  const first = cursor.value
  const offset = (first.getDay() + 6) % 7
  return Array.from({ length: 42 }, (_, i) => {
    const d = new Date(first.getFullYear(), first.getMonth(), 1 - offset + i)
    return { key: ymd(d), day: d.getDate(), inMonth: d.getMonth() === first.getMonth() }
  })
})

const loading = ref(true)
const errorMessage = ref('')
const tasks = ref([])
const projectNames = ref({})

const tasksByDay = computed(() => {
  const map = {}
  for (const t of tasks.value) (map[t.due_date] ||= []).push(t)
  return map
})

async function loadProjectNames() {
  if (Object.keys(projectNames.value).length) return
  const names = {}
  let page = 1
  let last = 1
  do {
    const { data } = await api.get('/projects', { params: { page } })
    for (const p of unwrap(data)) names[p.id] = p.name
    last = data.meta?.last_page ?? 1
    page += 1
  } while (page <= last)
  projectNames.value = names
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [res] = await Promise.all([
      // TODO(nanti): maksimal 100 task per tampilan bulan — tambah paginasi bila ada yang sebesar itu.
      api.get('/tasks', { params: { due_from: days.value[0].key, due_to: days.value[41].key, per_page: 100 } }),
      loadProjectNames(),
    ])
    tasks.value = unwrap(res.data)
  } catch (err) {
    errorMessage.value = apiErrorMessage(err, 'Gagal memuat kalender.')
  } finally {
    loading.value = false
  }
}

function shiftMonth(delta) {
  cursor.value = new Date(cursor.value.getFullYear(), cursor.value.getMonth() + delta, 1)
}

const isOverdue = (t) => t.status !== 'done' && t.due_date < today

function chipClass(t) {
  if (t.status === 'done') return 'bg-surface-container text-outline line-through'
  if (isOverdue(t) || t.priority === 'high') return 'bg-error-container text-on-error-container'
  return 'bg-secondary-container text-on-secondary-container'
}

const chipTitle = (t) => `${t.title} — ${projectNames.value[t.project_id] ?? 'Proyek'}${isOverdue(t) ? ' (terlambat)' : ''}`
const openProject = (t) => router.push(`${base}/proyek/${t.project_id}`)

watch(cursor, load)
onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Modul Manajemen Proyek</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Kalender</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Tenggat task per hari. Klik task untuk membuka board proyeknya.</p>
      </div>
      <div class="flex items-center gap-space-xs self-start lg:self-center">
        <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container" title="Bulan sebelumnya" @click="shiftMonth(-1)">
          <span class="material-symbols-outlined">chevron_left</span>
        </button>
        <span class="min-w-40 text-center font-headline-sm text-headline-sm font-bold capitalize text-on-surface">{{ monthLabel }}</span>
        <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container" title="Bulan berikutnya" @click="shiftMonth(1)">
          <span class="material-symbols-outlined">chevron_right</span>
        </button>
        <button class="ml-space-xs rounded-xl px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="cursor = firstOfThisMonth()">Hari ini</button>
      </div>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div class="overflow-x-auto">
      <div class="min-w-[640px] overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest" :class="loading ? 'opacity-60' : ''">
        <div class="grid grid-cols-7 border-b border-outline-variant bg-surface-container-low">
          <div v-for="w in WEEKDAYS" :key="w" class="px-space-xs py-space-xs text-center font-label-md text-label-md font-semibold text-on-surface-variant">{{ w }}</div>
        </div>
        <div class="grid grid-cols-7">
          <div
            v-for="d in days"
            :key="d.key"
            class="flex min-h-24 flex-col gap-1 border-b border-r border-surface-container p-1"
            :class="d.inMonth ? '' : 'bg-surface-container-low/60'"
          >
            <span
              class="self-end rounded-full px-1.5 font-label-sm text-label-sm"
              :class="d.key === today ? 'bg-primary-container font-bold text-on-primary' : d.inMonth ? 'text-on-surface' : 'text-outline'"
            >{{ d.day }}</span>
            <button
              v-for="t in (tasksByDay[d.key] ?? []).slice(0, MAX_PER_DAY)"
              :key="t.id"
              type="button"
              class="truncate rounded px-1.5 py-0.5 text-left font-label-sm text-label-sm"
              :class="chipClass(t)"
              :title="chipTitle(t)"
              @click="openProject(t)"
            >{{ t.title }}</button>
            <span
              v-if="(tasksByDay[d.key]?.length ?? 0) > MAX_PER_DAY"
              class="px-1 font-label-sm text-label-sm text-on-surface-variant"
              :title="tasksByDay[d.key].slice(MAX_PER_DAY).map((t) => t.title).join('\n')"
            >+{{ tasksByDay[d.key].length - MAX_PER_DAY }} lagi</span>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-space-sm flex flex-wrap items-center gap-space-md font-label-sm text-label-sm text-on-surface-variant">
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-secondary-container"></span>Berjalan</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-error-container"></span>Prioritas tinggi atau terlambat</span>
      <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded bg-surface-container"></span>Selesai</span>
    </div>
  </div>
</template>
