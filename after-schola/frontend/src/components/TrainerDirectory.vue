<script setup>
// Tab "Daftar Trainer": profil singkat tiap trainer — mata pelajaran & level yang bisa diajar
// (diisi saat membuat user), sekolah yang dipegang, sesi bulan ini, dan status check-in hari ini.
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { initials, unwrap } from '@/lib/format'
import { currentMonthKey, normalizeSession } from '@/lib/sessionRecap'

const loading = ref(true)
const errorMessage = ref('')
const users = ref([])
const schools = ref([])
const sessions = ref([])
const notCheckedInIds = ref(new Set()) // trainer yang punya sesi hari ini tapi belum check-in (dari /dashboard)
const search = ref('')
const statusFilter = ref('semua') // 'semua' | 'aktif' | 'nonaktif'

const LEVEL_LABEL = { beginner: 'Beginner', intermediate: 'Intermediate' }
const asList = (v) => (Array.isArray(v) ? v : [])

function localDateKey() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [usersRes, schoolsRes, sessionsRes, dashRes] = await Promise.all([
      api.get('/users'),
      api.get('/schools'),
      api.get('/sessions'),
      api.get('/dashboard').catch(() => null), // non-fatal: hanya untuk status check-in hari ini
    ])
    users.value = unwrap(usersRes.data).filter((u) => (u.roles ?? []).includes('trainer'))
    schools.value = unwrap(schoolsRes.data)
    sessions.value = unwrap(sessionsRes.data).map(normalizeSession)
    notCheckedInIds.value = new Set((dashRes?.data?.today?.trainers_not_checked_in ?? []).map((t) => t.id))
  } catch (e) {
    errorMessage.value = 'Gagal memuat data trainer.'
  } finally {
    loading.value = false
  }
}

// Sekolah yang dipegang trainer: dari data user bila ada, kalau tidak dari daftar sekolah.
function schoolNamesOf(u) {
  const direct = u.assignedSchools ?? u.assigned_schools ?? u.schools
  if (Array.isArray(direct) && direct.length) return direct.map((s) => s.name).filter(Boolean)
  return schools.value
    .filter((s) => (s.trainers ?? []).some((t) => t.id === u.id))
    .map((s) => s.name)
}

const rows = computed(() => {
  const today = localDateKey()
  const month = currentMonthKey()
  return users.value.map((u) => {
    const mine = sessions.value.filter((s) => String(s.trainerId) === String(u.id))
    const todayCount = mine.filter((s) => s.date === today).length
    return {
      id: u.id,
      name: u.name,
      email: u.email,
      active: !(u.is_active === false || u.is_active === 0),
      subjects: asList(u.subjects_taught),
      levels: asList(u.levels_taught),
      schools: schoolNamesOf(u),
      monthCount: mine.filter((s) => s.monthKey === month).length,
      todayState: todayCount === 0 ? 'none' : notCheckedInIds.value.has(u.id) ? 'pending' : 'done',
    }
  })
})

const statusTabs = computed(() => {
  const aktif = rows.value.filter((r) => r.active).length
  return [
    { key: 'semua', label: 'Semua', count: rows.value.length },
    { key: 'aktif', label: 'Aktif', count: aktif },
    { key: 'nonaktif', label: 'Nonaktif', count: rows.value.length - aktif },
  ]
})

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return rows.value.filter(
    (r) =>
      (statusFilter.value === 'semua' || (statusFilter.value === 'aktif') === r.active) &&
      (!q || r.name.toLowerCase().includes(q) || (r.email || '').toLowerCase().includes(q)),
  )
})

const todayBadge = {
  none: { label: 'Tidak ada sesi', cls: 'bg-[#EEF1F7] text-[#5B6577]' },
  pending: { label: 'Belum check-in', cls: 'bg-error-container text-error' },
  done: { label: 'Sudah check-in', cls: 'bg-[#D1FAE5] text-[#065F46]' },
}

// Gaya kartu: sama dengan kartu di Dashboard Manajemen Bisnis (border jelas + bayangan halus).
const cardCls =
  'flex flex-col rounded-2xl border border-[#D5DCEB] bg-white p-5 shadow-[0_1px_3px_rgba(11,42,91,0.08),0_6px_16px_-6px_rgba(11,42,91,0.10)] transition-all duration-200 hover:border-[#9DB3E8] hover:shadow-[0_10px_28px_-8px_rgba(11,42,91,0.22)]'
const labelCls = 'text-[11px] font-bold uppercase tracking-wider text-[#5B6577]'

onMounted(load)
</script>

<template>
  <div class="flex flex-col">
    <!-- Cari + filter status -->
    <div class="mb-space-lg flex flex-col gap-space-sm sm:flex-row sm:items-center">
      <div class="relative w-full sm:max-w-sm">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline">search</span>
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama atau email trainer…"
          class="w-full rounded-xl border border-[#D5DCEB] bg-white py-2.5 pl-9 pr-space-md font-body-md text-body-md text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
        />
      </div>
      <div class="inline-flex w-fit rounded-xl bg-surface-container-low p-1">
        <button
          v-for="t in statusTabs"
          :key="t.key"
          type="button"
          class="flex items-center gap-1.5 rounded-lg px-space-md py-1.5 font-label-md text-label-md font-semibold transition-all"
          :class="statusFilter === t.key ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container'"
          @click="statusFilter = t.key"
        >
          {{ t.label }}
          <span class="rounded-full px-1.5 text-[11px] leading-5" :class="statusFilter === t.key ? 'bg-white/20' : 'bg-surface-container'">{{ t.count }}</span>
        </button>
      </div>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-72 animate-pulse rounded-2xl border border-[#D5DCEB] bg-white"></div>
    </div>

    <div v-else-if="!filtered.length" class="rounded-2xl border border-[#D5DCEB] bg-white px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">person_off</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">
        {{ rows.length ? 'Trainer tidak ditemukan' : 'Belum ada trainer' }}
      </p>
      <p v-if="!rows.length" class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
        Tambahkan akun trainer di menu Manajemen User.
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="t in filtered" :key="t.id" :class="[cardCls, !t.active && 'opacity-75']">
        <!-- Kepala: avatar (lingkaran inisial) + nama + status akun -->
        <div class="flex items-start gap-3">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-secondary-container text-[15px] font-bold text-on-secondary-container">{{ initials(t.name) }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[16px] font-bold text-[#0B1C30]">{{ t.name }}</p>
            <p class="truncate text-[13px] text-[#5B6577]">{{ t.email }}</p>
          </div>
          <span
            class="shrink-0 rounded-full px-2.5 py-0.5 text-[12px] font-semibold"
            :class="t.active ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-[#EEF1F7] text-[#5B6577]'"
            >{{ t.active ? 'Aktif' : 'Nonaktif' }}</span
          >
        </div>

        <!-- Kemampuan mengajar + sekolah -->
        <div class="mt-4 flex flex-col gap-3">
          <div class="flex flex-col gap-1.5">
            <span :class="labelCls">Mata pelajaran</span>
            <div v-if="t.subjects.length" class="flex flex-wrap gap-1.5">
              <span v-for="s in t.subjects" :key="s" class="rounded-full bg-[#E5EDFF] px-2.5 py-0.5 text-[12px] font-semibold text-[#1D4ED8]">{{ s }}</span>
            </div>
            <span v-else class="text-[13px] text-outline">Belum diatur</span>
          </div>

          <div class="flex flex-col gap-1.5">
            <span :class="labelCls">Level</span>
            <div v-if="t.levels.length" class="flex flex-wrap gap-1.5">
              <span v-for="l in t.levels" :key="l" class="rounded-full bg-[#EDE9FE] px-2.5 py-0.5 text-[12px] font-semibold text-[#6D28D9]">{{ LEVEL_LABEL[l] || l }}</span>
            </div>
            <span v-else class="text-[13px] text-outline">Belum diatur</span>
          </div>

          <div class="flex flex-col gap-1.5">
            <span :class="labelCls">Sekolah dipegang</span>
            <div v-if="t.schools.length" class="flex flex-wrap gap-1.5">
              <span v-for="s in t.schools.slice(0, 3)" :key="s" class="rounded-full bg-[#EEF1F7] px-2.5 py-0.5 text-[12px] font-medium text-[#3C4657]">{{ s }}</span>
              <span v-if="t.schools.length > 3" class="rounded-full bg-[#EEF1F7] px-2.5 py-0.5 text-[12px] font-medium text-[#3C4657]">+{{ t.schools.length - 3 }} lainnya</span>
            </div>
            <span v-else class="text-[13px] text-outline">Belum ditugaskan</span>
          </div>
        </div>

        <!-- Aktivitas -->
        <div class="mt-4 grid grid-cols-2 items-end gap-3 border-t border-[#E8EDF6] pt-4">
          <div class="flex flex-col gap-1">
            <span class="text-[26px] font-extrabold leading-none text-[#0B1C30]">{{ t.monthCount }}</span>
            <span class="text-[12px] text-[#5B6577]">Sesi bulan ini</span>
          </div>
          <div class="flex flex-col items-start gap-1">
            <span :class="labelCls">Hari ini</span>
            <span class="rounded-full px-2.5 py-0.5 text-[12px] font-semibold" :class="todayBadge[t.todayState].cls">{{ todayBadge[t.todayState].label }}</span>
          </div>
        </div>

        <RouterLink
          :to="`/management/absensi/trainer/${t.id}`"
          class="mt-4 inline-flex items-center justify-between text-[14px] font-semibold text-primary hover:underline"
        >
          Lihat rekap sesi
          <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </RouterLink>
      </div>
    </div>
  </div>
</template>
