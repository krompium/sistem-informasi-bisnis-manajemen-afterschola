<script setup>
// Kalender bulanan pertemuan. Menandai hari yang punya sesi (dot per mode),
// navigasi bulan, dan menghitung jumlah pertemuan pada bulan yang ditampilkan.
import { computed, ref } from 'vue'
import { isOnsite } from '@/lib/format'

const props = defineProps({
  sessions: { type: Array, default: () => [] },
  selectedDate: { type: String, default: '' }, // 'YYYY-MM-DD'
})
const emit = defineEmits(['day-click'])

const MONTHS = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']

const today = new Date()
today.setHours(0, 0, 0, 0)

const view = ref({ y: today.getFullYear(), m: today.getMonth() })

function pad(n) {
  return String(n).padStart(2, '0')
}
function keyOf(d) {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

// Peta tanggal -> daftar sesi.
const byDay = computed(() => {
  const map = {}
  for (const s of props.sessions) {
    if (!s.date) continue
    const k = String(s.date).slice(0, 10)
    ;(map[k] ||= []).push(s)
  }
  return map
})

const monthPrefix = computed(() => `${view.value.y}-${pad(view.value.m + 1)}`)
const monthCount = computed(
  () => props.sessions.filter((s) => String(s.date || '').startsWith(monthPrefix.value)).length,
)

const cells = computed(() => {
  const first = new Date(view.value.y, view.value.m, 1)
  const startWeekday = (first.getDay() + 6) % 7 // Senin = 0
  const start = new Date(view.value.y, view.value.m, 1 - startWeekday)
  const out = []
  for (let i = 0; i < 42; i++) {
    const d = new Date(start)
    d.setDate(start.getDate() + i)
    const k = keyOf(d)
    const list = byDay.value[k] || []
    out.push({
      key: k,
      day: d.getDate(),
      inMonth: d.getMonth() === view.value.m,
      isToday: d.getTime() === today.getTime(),
      onsite: list.filter((s) => isOnsite(s.mode)).length,
      online: list.filter((s) => !isOnsite(s.mode)).length,
      count: list.length,
    })
  }
  return out
})

function prevMonth() {
  const m = view.value.m - 1
  view.value = m < 0 ? { y: view.value.y - 1, m: 11 } : { y: view.value.y, m }
}
function nextMonth() {
  const m = view.value.m + 1
  view.value = m > 11 ? { y: view.value.y + 1, m: 0 } : { y: view.value.y, m }
}
function goToday() {
  view.value = { y: today.getFullYear(), m: today.getMonth() }
}
function clickDay(cell) {
  emit('day-click', cell.key)
}

defineExpose({ monthCount })
</script>

<template>
  <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
    <!-- Header bulan -->
    <div class="mb-space-md flex flex-col gap-space-sm sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-space-sm">
        <h2 class="font-headline-md text-headline-md font-bold text-on-surface">
          {{ MONTHS[view.m] }} {{ view.y }}
        </h2>
        <span
          class="inline-flex items-center gap-1 rounded-full bg-primary-fixed px-2.5 py-0.5 font-label-sm text-label-sm font-semibold text-primary"
        >
          <span class="material-symbols-outlined text-[14px]">event</span>
          {{ monthCount }} pertemuan
        </span>
      </div>
      <div class="flex items-center gap-space-xs">
        <button
          class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md font-semibold text-primary transition-colors hover:bg-surface-container-low"
          @click="goToday"
        >
          Hari Ini
        </button>
        <button
          class="flex h-8 w-8 items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-surface-container-low"
          aria-label="Bulan sebelumnya"
          @click="prevMonth"
        >
          <span class="material-symbols-outlined text-[20px]">chevron_left</span>
        </button>
        <button
          class="flex h-8 w-8 items-center justify-center rounded-lg text-on-surface-variant transition-colors hover:bg-surface-container-low"
          aria-label="Bulan berikutnya"
          @click="nextMonth"
        >
          <span class="material-symbols-outlined text-[20px]">chevron_right</span>
        </button>
      </div>
    </div>

    <!-- Nama hari -->
    <div class="mb-1 grid grid-cols-7 gap-1">
      <div
        v-for="w in WEEKDAYS"
        :key="w"
        class="py-1 text-center font-label-sm text-label-sm font-semibold uppercase tracking-wider text-outline"
      >
        {{ w }}
      </div>
    </div>

    <!-- Grid tanggal -->
    <div class="grid grid-cols-7 gap-1">
      <button
        v-for="c in cells"
        :key="c.key"
        type="button"
        class="relative flex min-h-[52px] flex-col items-center rounded-lg border p-1 text-left transition-all sm:min-h-[64px]"
        :class="[
          c.inMonth ? 'bg-surface-container-lowest' : 'bg-surface-container-low/40',
          c.key === selectedDate
            ? 'border-primary-container ring-2 ring-primary/15'
            : 'border-surface-container hover:border-primary-container/40 hover:bg-surface-container-low',
        ]"
        @click="clickDay(c)"
      >
        <span
          class="flex h-6 w-6 items-center justify-center rounded-full font-label-md text-label-md"
          :class="[
            c.isToday ? 'bg-primary-container font-bold text-on-primary' : '',
            !c.inMonth ? 'text-outline-variant' : c.isToday ? '' : 'text-on-surface',
          ]"
        >
          {{ c.day }}
        </span>

        <!-- Penanda sesi -->
        <div v-if="c.count" class="mt-auto flex w-full flex-col items-center gap-0.5 pb-0.5">
          <div class="flex items-center justify-center gap-1">
            <span v-if="c.onsite" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
            <span v-if="c.online" class="h-1.5 w-1.5 rounded-full bg-primary-container"></span>
          </div>
          <span class="font-label-sm text-[10px] font-semibold text-on-surface-variant">{{ c.count }} sesi</span>
        </div>
      </button>
    </div>

    <!-- Legenda -->
    <div class="mt-space-md flex items-center gap-space-md border-t border-surface-container pt-space-sm font-label-sm text-label-sm text-on-surface-variant">
      <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Onsite</span>
      <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary-container"></span> Online</span>
      <span class="ml-auto flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-primary-container"></span> Hari ini</span>
    </div>
  </div>
</template>
