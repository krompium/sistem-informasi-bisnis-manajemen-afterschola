<script setup>
// Gantt chart satu proyek: bar per task (mulai -> tenggat), progres, dan panah ketergantungan.
// Read-only untuk Developer maupun Management; tanggal dan ketergantungan diubah dari modal task di board.
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateShort, initials } from '@/lib/format'
import { usePolling } from '@/lib/usePolling'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const projectId = route.params.id
const boardPath = computed(() => `${auth.isManagement ? '/management/proyek' : '/developer/proyek'}/${projectId}`)

const ROW_H = 36
const BAR_H = 20
const HEAD_H = 52
const DAY_MS = 86400000
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
const ZOOMS = [
  { key: 'day', label: 'Hari', width: 28 },
  { key: 'week', label: 'Minggu', width: 12 },
  { key: 'month', label: 'Bulan', width: 4 },
]
const STATUS = {
  todo: { label: 'To Do', color: '#94A3B8' },
  in_progress: { label: 'In Progress', color: '#2563EB' },
  review: { label: 'Review', color: '#F59E0B' },
  done: { label: 'Done', color: '#16A34A' },
}

const zoom = ref('week')
const data = ref(null)
const loading = ref(true)
const error = ref('')
const scroller = ref(null)

const W = computed(() => ZOOMS.find((z) => z.key === zoom.value).width)

// 'YYYY-MM-DD' -> nomor hari (UTC), supaya selisih hari tidak terpengaruh zona waktu atau DST.
const toDay = (s) => {
  const [y, m, d] = s.split('-').map(Number)
  return Math.floor(Date.UTC(y, m - 1, d) / DAY_MS)
}
const dayDate = (n) => new Date(n * DAY_MS)
const today = toDay(new Date().toLocaleDateString('en-CA'))

const tasks = computed(() => data.value?.tasks ?? [])

// Task tanpa tanggal mulai digambar satu hari di tenggatnya.
function span(t) {
  const start = toDay(t.start_date ?? t.due_date)
  return { start, end: Math.max(start, toDay(t.due_date)) }
}

const range = computed(() => {
  if (!data.value) return null
  const days = [today, toDay(data.value.project.start_date), toDay(data.value.project.due_date)]
  for (const t of tasks.value) {
    const s = span(t)
    days.push(s.start, s.end)
  }
  return { start: Math.min(...days) - 3, end: Math.max(...days) + 3 }
})
const chartW = computed(() => (range.value ? (range.value.end - range.value.start + 1) * W.value : 0))
const bodyH = computed(() => tasks.value.length * ROW_H)

const bars = computed(() =>
  tasks.value.map((t, i) => {
    const s = span(t)
    return {
      t,
      start: s.start,
      end: s.end,
      x: (s.start - range.value.start) * W.value,
      w: (s.end - s.start + 1) * W.value,
      y: i * ROW_H + (ROW_H - BAR_H) / 2,
    }
  }),
)
const barById = computed(() => Object.fromEntries(bars.value.map((b) => [b.t.id, b])))

// Panah dari ujung prasyarat ke awal task yang bergantung. Merah bila task mulai sebelum prasyaratnya selesai.
const arrows = computed(() =>
  (data.value?.dependencies ?? []).flatMap((d) => {
    const from = barById.value[d.depends_on_id]
    const to = barById.value[d.task_id]
    if (!from || !to) return []
    const x1 = from.x + from.w
    const y1 = from.y + BAR_H / 2
    const x2 = to.x
    const y2 = to.y + BAR_H / 2
    const mid = x1 + 8
    const path =
      x2 > mid + 4
        ? `M${x1},${y1} H${mid} V${y2} H${x2}`
        : `M${x1},${y1} H${mid} V${(y1 + y2) / 2} H${x2 - 8} V${y2} H${x2}`
    return [{ id: d.id, path, conflict: to.start < from.end }]
  }),
)

const months = computed(() => {
  const out = []
  let d = range.value.start
  while (d <= range.value.end) {
    const month = dayDate(d).getUTCMonth()
    let e = d
    while (e < range.value.end && dayDate(e + 1).getUTCMonth() === month) e++
    out.push({
      label: `${MONTHS[month]} ${dayDate(d).getUTCFullYear()}`,
      left: (d - range.value.start) * W.value,
      width: (e - d + 1) * W.value,
    })
    d = e + 1
  }
  return out
})

// Penanda hari: tiap hari (zoom Hari) atau tiap Senin (zoom Minggu).
const ticks = computed(() => {
  if (zoom.value === 'month') return []
  const out = []
  for (let d = range.value.start; d <= range.value.end; d++) {
    const date = dayDate(d)
    const dow = date.getUTCDay()
    if (zoom.value === 'week' && dow !== 1) continue
    out.push({
      key: d,
      label: date.getUTCDate(),
      left: (d - range.value.start) * W.value,
      weekend: zoom.value === 'day' && (dow === 0 || dow === 6),
    })
  }
  return out
})

const todayX = computed(() => (today - range.value.start) * W.value + W.value / 2)
const isOverdue = (t) => t.status !== 'done' && toDay(t.due_date) < today

function barTitle(t) {
  const start = t.start_date ? `${formatDateShort(t.start_date)} – ` : ''
  return `${t.title}\n${STATUS[t.status].label} · ${t.progress}%\n${start}${formatDateShort(t.due_date)}`
}

async function load() {
  try {
    const { data: res } = await api.get(`/projects/${projectId}/gantt`)
    data.value = res.data
    error.value = ''
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat Gantt.')
  } finally {
    loading.value = false
  }
}

// Gulir ke hari ini saat pertama tampil dan saat zoom berubah (bukan tiap refresh, supaya posisi gulir tidak lompat).
const scrollToToday = () =>
  nextTick(() => {
    if (scroller.value && range.value) scroller.value.scrollLeft = Math.max(0, todayX.value - 160)
  })
let firstLoad = true
watch(data, () => {
  if (firstLoad && data.value) {
    firstLoad = false
    scrollToToday()
  }
})
watch(zoom, scrollToToday)

load()
usePolling(load, 30000)
</script>

<template>
  <div class="mx-auto flex max-w-7xl flex-col gap-space-md p-space-lg">
    <div>
      <router-link :to="boardPath" class="inline-flex items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>Kembali ke board
      </router-link>
    </div>

    <div class="flex flex-col gap-space-sm lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="font-headline-xl text-headline-xl font-bold text-on-surface">Gantt{{ data ? ` · ${data.project.name}` : '' }}</h1>
        <p v-if="data" class="font-body-md text-body-md text-on-surface-variant">
          {{ formatDateShort(data.project.start_date) }} – {{ formatDateShort(data.project.due_date) }} · {{ tasks.length }} task
        </p>
      </div>
      <div class="inline-flex self-start rounded-xl bg-surface-container p-1 lg:self-auto">
        <button
          v-for="z in ZOOMS"
          :key="z.key"
          class="rounded-lg px-space-md py-1.5 font-label-md text-label-md transition-colors"
          :class="zoom === z.key ? 'bg-white font-semibold text-primary shadow-sm' : 'text-on-surface-variant hover:text-on-surface'"
          @click="zoom = z.key"
        >
          {{ z.label }}
        </button>
      </div>
    </div>

    <p v-if="error" class="rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ error }}</p>
    <p v-if="loading" class="font-body-md text-body-md text-on-surface-variant">Memuat Gantt…</p>
    <div v-else-if="data && !tasks.length" class="flex flex-col items-center gap-1 rounded-2xl bg-surface-container-lowest p-space-xl text-center ring-1 ring-surface-container">
      <span class="material-symbols-outlined text-[32px] text-outline">view_timeline</span>
      <p class="font-body-md text-body-md text-on-surface-variant">Belum ada task di proyek ini.</p>
    </div>

    <template v-else-if="data">
      <div class="flex flex-wrap items-center gap-x-space-md gap-y-1 font-label-sm text-label-sm text-on-surface-variant">
        <span v-for="(s, key) in STATUS" :key="key" class="inline-flex items-center gap-1">
          <span class="h-3 w-3 rounded-sm" :style="{ background: s.color }"></span>{{ s.label }}
        </span>
        <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-sm ring-2 ring-red-500"></span>Terlambat</span>
        <span class="inline-flex items-center gap-1"><span class="h-0.5 w-4 bg-red-600"></span>Mulai sebelum prasyarat selesai</span>
      </div>

      <div class="flex overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-surface-container">
        <!-- Kolom judul task -->
        <div class="w-56 shrink-0 border-r border-surface-container">
          <div class="flex items-end border-b border-surface-container px-space-sm pb-1 font-label-md text-label-md font-semibold text-on-surface-variant" :style="{ height: HEAD_H + 'px' }">Task</div>
          <button
            v-for="t in tasks"
            :key="t.id"
            class="flex w-full items-center gap-1 border-b border-surface-container px-space-sm text-left hover:bg-surface-container-low"
            :style="{ height: ROW_H + 'px' }"
            :title="t.title"
            @click="router.push(boardPath)"
          >
            <span class="min-w-0 flex-1 truncate font-body-sm text-body-sm text-on-surface">{{ t.title }}</span>
            <span
              v-if="t.assignee"
              class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-[9px] font-bold text-primary"
              :title="t.assignee.name"
            >{{ initials(t.assignee.name) }}</span>
          </button>
        </div>

        <!-- Timeline -->
        <div ref="scroller" class="min-w-0 flex-1 overflow-x-auto">
          <div class="relative" :style="{ width: chartW + 'px' }">
            <div class="relative border-b border-surface-container" :style="{ height: HEAD_H + 'px' }">
              <div
                v-for="m in months"
                :key="m.left"
                class="absolute top-0 flex h-6 items-center overflow-hidden whitespace-nowrap border-l border-surface-container px-1 font-label-sm text-label-sm font-semibold text-on-surface"
                :style="{ left: m.left + 'px', width: m.width + 'px' }"
              >{{ m.label }}</div>
              <div
                v-for="k in ticks"
                :key="k.key"
                class="absolute bottom-0 flex h-6 items-center justify-center text-[10px]"
                :class="k.weekend ? 'text-outline' : 'text-on-surface-variant'"
                :style="{ left: k.left + 'px', width: (zoom === 'week' ? W * 7 : W) + 'px' }"
              >{{ k.label }}</div>
            </div>

            <div class="relative" :style="{ height: bodyH + 'px' }">
              <div
                v-for="k in ticks.filter((x) => x.weekend)"
                :key="'w' + k.key"
                class="absolute top-0 bottom-0 bg-surface-container-low/70"
                :style="{ left: k.left + 'px', width: W + 'px' }"
              ></div>
              <div v-for="m in months" :key="'l' + m.left" class="absolute top-0 bottom-0 border-l border-surface-container" :style="{ left: m.left + 'px' }"></div>
              <div v-for="(t, i) in tasks" :key="'r' + t.id" class="absolute left-0 right-0 border-b border-surface-container" :style="{ top: i * ROW_H + 'px', height: ROW_H + 'px' }"></div>
              <div class="absolute top-0 bottom-0 w-px bg-red-500" :style="{ left: todayX + 'px' }" title="Hari ini"></div>

              <svg class="pointer-events-none absolute left-0 top-0" :width="chartW" :height="bodyH">
                <defs>
                  <marker id="gantt-arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="6" markerHeight="6" orient="auto">
                    <path d="M0,0 L8,4 L0,8 z" fill="#64748B" />
                  </marker>
                  <marker id="gantt-arrow-bad" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="6" markerHeight="6" orient="auto">
                    <path d="M0,0 L8,4 L0,8 z" fill="#DC2626" />
                  </marker>
                </defs>
                <path
                  v-for="a in arrows"
                  :key="a.id"
                  :d="a.path"
                  fill="none"
                  stroke-width="1.5"
                  :stroke="a.conflict ? '#DC2626' : '#64748B'"
                  :marker-end="a.conflict ? 'url(#gantt-arrow-bad)' : 'url(#gantt-arrow)'"
                />
              </svg>

              <button
                v-for="b in bars"
                :key="b.t.id"
                class="absolute overflow-hidden rounded-md shadow-sm focus:outline-none"
                :class="isOverdue(b.t) ? 'ring-2 ring-red-500' : ''"
                :style="{ left: b.x + 'px', top: b.y + 'px', width: b.w + 'px', height: BAR_H + 'px', background: STATUS[b.t.status].color }"
                :title="barTitle(b.t)"
                @click="router.push(boardPath)"
              >
                <span class="absolute inset-y-0 left-0 bg-black/25" :style="{ width: b.t.progress + '%' }"></span>
                <span class="relative block truncate px-1 text-left text-[10px] font-semibold text-white">{{ b.t.title }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
