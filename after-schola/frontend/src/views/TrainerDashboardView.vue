<script setup>
// Dashboard Trainer — mengikuti design system Stitch "Remix" (Layar 2).
// Data dari GET /api/dashboard (respons peran trainer).
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { sessionTime } from '@/lib/format'
import { usePolling } from '@/lib/usePolling'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const loading = ref(true)
const errorMessage = ref('')
const dashboard = ref({
  schools_count: 0,
  students_count: 0,
  schools: [],
  today_sessions: [],
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 11) return 'Selamat Pagi'
  if (h < 15) return 'Selamat Siang'
  if (h < 19) return 'Selamat Sore'
  return 'Selamat Malam'
})

const todayText = computed(() =>
  new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }),
)

const sessions = computed(() => dashboard.value.today_sessions ?? [])
const nextSession = computed(() => sessions.value[0] ?? null)

const onsiteCount = computed(
  () => sessions.value.filter((s) => isOnsite(s.mode)).length,
)
const onlineCount = computed(() => sessions.value.length - onsiteCount.value)

const schoolChips = computed(() => {
  const list = dashboard.value.schools ?? []
  return {
    head: list.slice(0, 2),
    more: Math.max(0, list.length - 2),
  }
})

function isOnsite(mode) {
  return String(mode || '').toLowerCase() !== 'online'
}

function modeLabel(mode) {
  return isOnsite(mode) ? 'Onsite' : 'Online'
}

function moduleName(session) {
  return session.classroom?.name || session.classroom?.level || 'Modul Pembelajaran'
}

function initials(name) {
  if (!name) return 'AS'
  return name
    .split(' ')
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('')
}

function goAbsensi(session) {
  const id = session && session.id
  router.push(id ? `/trainer/absensi/${id}` : '/trainer/absensi')
}

async function loadDashboard(silent = false) {
  if (!silent) loading.value = true
  try {
    const { data } = await api.get('/dashboard')
    dashboard.value = {
      schools_count: data.schools_count ?? 0,
      students_count: data.students_count ?? 0,
      schools: data.schools ?? [],
      today_sessions: data.today_sessions ?? [],
    }
    errorMessage.value = ''
  } catch (err) {
    if (!silent) errorMessage.value = 'Gagal memuat dashboard. Coba muat ulang.'
  } finally {
    if (!silent) loading.value = false
  }
}

onMounted(() => loadDashboard())
// Refresh berkala (per detik) agar jadwal & data terbaru muncul otomatis.
usePolling(() => loadDashboard(true), 1000)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <!-- Header greeting -->
    <div
      class="mb-space-md flex flex-col justify-between gap-space-sm pt-space-lg md:flex-row md:items-end"
    >
      <div>
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Portal Pelatih • Semester Genap</span
        >
        <h1 class="font-headline-xl text-headline-xl tracking-tight text-on-surface">
          {{ greeting }}, {{ auth.firstName || 'Trainer' }} 👋
        </h1>
      </div>
      <div class="flex items-center gap-space-xs font-body-sm text-body-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[18px] text-primary">calendar_today</span>
        <span>{{ todayText }}</span>
      </div>
    </div>

    <!-- Error -->
    <div
      v-if="errorMessage"
      class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container"
    >
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadDashboard">
        Muat ulang
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 gap-space-lg md:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-40 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else>
      <!-- Alert check-in -->
      <div
        v-if="nextSession"
        class="mb-space-xl flex flex-col justify-between gap-space-md rounded-2xl bg-tertiary-fixed p-space-md text-on-tertiary-fixed shadow-sm md:flex-row md:items-center md:p-space-lg"
      >
        <div class="flex min-w-0 items-start gap-space-md md:items-center">
          <div
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-tertiary-container/15 text-tertiary"
          >
            <span class="material-symbols-outlined text-[24px]">notifications_active</span>
          </div>
          <div class="flex min-w-0 flex-col">
            <span class="font-label-md text-label-md font-bold uppercase tracking-wider text-tertiary"
              >Peringatan Check-in Hari Ini</span
            >
            <p class="mt-0.5 font-body-md text-body-md leading-snug text-on-tertiary-fixed">
              Kamu belum check-in untuk pertemuan hari ini di
              <strong class="font-semibold">{{ nextSession.school?.name || 'sekolah binaan' }}</strong
              >.
            </p>
          </div>
        </div>
        <button
          class="inline-flex w-full shrink-0 items-center justify-center gap-space-xs rounded-xl bg-primary px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary shadow-md transition-all hover:bg-primary-container active:scale-95 md:w-auto"
          @click="goAbsensi(nextSession)"
        >
          <span>Buka &amp; Check-in</span>
          <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </button>
      </div>

      <!-- Stat cards -->
      <div class="mb-space-2xl grid grid-cols-1 gap-space-md md:grid-cols-3 md:gap-space-lg">
        <!-- Sekolah Aktif -->
        <div
          class="group relative flex flex-col justify-between overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-shadow hover:shadow-md"
        >
          <div
            class="pointer-events-none absolute -bottom-6 -right-6 h-28 w-28 rounded-full bg-primary-fixed/30 blur-2xl transition-transform group-hover:scale-125"
          ></div>
          <div>
            <div class="mb-space-sm flex items-center justify-between">
              <span
                class="font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant"
                >Sekolah Aktif</span
              >
              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                <span class="material-symbols-outlined text-[20px]">domain</span>
              </div>
            </div>
            <div class="flex items-baseline gap-space-xs">
              <span class="font-display-lg text-display-lg font-bold tracking-tight text-on-surface">{{
                dashboard.schools_count
              }}</span>
              <span class="font-headline-sm text-headline-sm text-on-surface-variant">Sekolah</span>
            </div>
          </div>
          <div class="mt-space-md flex flex-wrap gap-1.5 border-t border-surface-container pt-space-sm">
            <span
              v-for="s in schoolChips.head"
              :key="s.id"
              class="inline-flex items-center rounded-md bg-surface-container px-2 py-0.5 font-body-sm text-body-sm text-on-surface"
              >{{ s.name }}</span
            >
            <span
              v-if="schoolChips.more"
              class="inline-flex items-center rounded-md bg-surface-container-high px-2 py-0.5 font-body-sm text-body-sm font-medium text-on-secondary"
              >+{{ schoolChips.more }} Lainnya</span
            >
            <span
              v-if="!dashboard.schools.length"
              class="font-body-sm text-body-sm text-on-surface-variant"
              >Belum ada sekolah ditugaskan</span
            >
          </div>
        </div>

        <!-- Pertemuan Hari Ini -->
        <div
          class="group relative flex flex-col justify-between overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-shadow hover:shadow-md"
        >
          <div
            class="pointer-events-none absolute -bottom-6 -right-6 h-28 w-28 rounded-full bg-secondary-fixed/40 blur-2xl transition-transform group-hover:scale-125"
          ></div>
          <div>
            <div class="mb-space-sm flex items-center justify-between">
              <span
                class="font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant"
                >Pertemuan Hari Ini</span
              >
              <div
                class="flex h-9 w-9 items-center justify-center rounded-xl bg-secondary-fixed text-secondary"
              >
                <span class="material-symbols-outlined text-[20px]">calendar_today</span>
              </div>
            </div>
            <div class="flex items-baseline gap-space-xs">
              <span class="font-display-lg text-display-lg font-bold tracking-tight text-on-surface">{{
                sessions.length
              }}</span>
              <span class="font-headline-sm text-headline-sm text-on-surface-variant">Sesi Belajar</span>
            </div>
          </div>
          <div
            class="mt-space-md flex items-center justify-between border-t border-surface-container pt-space-sm font-body-sm text-body-sm"
          >
            <div class="flex items-center gap-1 font-medium text-on-surface">
              <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
              <span>{{ onsiteCount }} Onsite</span>
            </div>
            <div class="flex items-center gap-1 font-medium text-on-surface">
              <span class="h-2 w-2 rounded-full bg-primary-container"></span>
              <span>{{ onlineCount }} Online</span>
            </div>
          </div>
        </div>

        <!-- Total Murid Binaan -->
        <div
          class="group relative flex flex-col justify-between overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-shadow hover:shadow-md"
        >
          <div
            class="pointer-events-none absolute -bottom-6 -right-6 h-28 w-28 rounded-full bg-primary-fixed/20 blur-2xl transition-transform group-hover:scale-125"
          ></div>
          <div>
            <div class="mb-space-sm flex items-center justify-between">
              <span
                class="font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant"
                >Total Murid Binaan</span
              >
              <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-surface-container text-primary">
                <span class="material-symbols-outlined text-[20px]">groups</span>
              </div>
            </div>
            <div class="flex items-baseline gap-space-xs">
              <span class="font-display-lg text-display-lg font-bold tracking-tight text-on-surface">{{
                dashboard.students_count
              }}</span>
              <span class="font-headline-sm text-headline-sm text-on-surface-variant">Murid</span>
            </div>
          </div>
          <div class="mt-space-md border-t border-surface-container pt-space-sm">
            <span class="font-body-sm text-body-sm text-on-surface-variant"
              >Tersebar di {{ dashboard.schools_count }} sekolah binaan</span
            >
          </div>
        </div>
      </div>

      <!-- Jadwal Hari Ini -->
      <div class="mb-space-2xl flex flex-col gap-space-lg">
        <div class="flex flex-col justify-between gap-space-xs sm:flex-row sm:items-center">
          <div>
            <span
              class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary"
              >Timeline Operasional</span
            >
            <h2 class="font-headline-lg text-headline-lg tracking-tight text-on-surface">
              Jadwal Hari Ini
            </h2>
          </div>
          <span class="font-body-sm text-body-sm text-on-surface-variant"
            >{{ sessions.length }} Sesi dijadwalkan</span
          >
        </div>

        <!-- Empty state -->
        <div
          v-if="!sessions.length"
          class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center"
        >
          <span class="material-symbols-outlined text-[36px] text-outline">event_available</span>
          <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">
            Tidak ada pertemuan hari ini
          </p>
          <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
            Jadwal berikutnya akan tampil di sini.
          </p>
        </div>

        <div v-else class="grid grid-cols-1 gap-space-lg lg:grid-cols-2">
          <div
            v-for="(s, idx) in sessions"
            :key="s.id"
            class="relative flex flex-col justify-between overflow-hidden rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-all hover:shadow-md"
          >
            <div
              class="absolute left-0 top-0 h-full w-1.5"
              :class="isOnsite(s.mode) ? 'bg-emerald-500' : 'bg-primary-container'"
            ></div>
            <div>
              <div class="mb-space-md flex items-start justify-between gap-space-sm">
                <div class="flex flex-col">
                  <span class="font-label-sm text-label-sm font-semibold text-on-surface-variant"
                    >SESI {{ s.meeting_no || idx + 1 }} • {{ sessionTime(s) ? sessionTime(s) + ' WIB' : 'Pertemuan' }}</span
                  >
                  <h3 class="mt-0.5 font-headline-md text-headline-md font-bold text-on-surface">
                    {{ s.school?.name || 'Sekolah' }}
                  </h3>
                </div>
                <span
                  class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1 font-label-sm text-label-sm font-semibold"
                  :class="
                    isOnsite(s.mode)
                      ? 'bg-[#D1FAE5] text-[#065F46]'
                      : 'bg-secondary-fixed text-on-secondary-container'
                  "
                >
                  <span
                    class="h-2 w-2 rounded-full"
                    :class="isOnsite(s.mode) ? 'bg-[#10B981]' : 'bg-primary-container'"
                  ></span>
                  {{ modeLabel(s.mode) }}
                </span>
              </div>
              <div class="mb-space-md flex flex-col gap-space-xs rounded-xl bg-surface-container-low p-space-md">
                <div class="flex items-center gap-space-sm text-on-surface">
                  <span class="material-symbols-outlined text-[20px] text-primary">menu_book</span>
                  <div class="flex flex-col">
                    <span class="font-label-sm text-label-sm text-on-surface-variant">Modul Pembelajaran</span>
                    <span class="font-headline-sm text-headline-sm font-semibold">{{ moduleName(s) }}</span>
                  </div>
                </div>
                <div v-if="s.note" class="flex items-center gap-space-sm pt-space-2xs text-on-surface">
                  <span class="material-symbols-outlined text-[20px] text-on-surface-variant">sticky_note_2</span>
                  <span class="font-body-md text-body-md text-on-surface-variant">{{ s.note }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between pt-space-sm">
              <span
                class="inline-flex items-center gap-1 font-label-sm text-label-sm font-semibold"
                :class="s.is_locked ? 'text-emerald-600' : 'text-tertiary'"
              >
                <span class="material-symbols-outlined text-[16px]">{{
                  s.is_locked ? 'lock' : 'schedule'
                }}</span>
                {{ s.is_locked ? 'Terkunci' : 'Belum dikunci' }}
              </span>
              <button
                class="inline-flex items-center justify-center gap-space-xs rounded-xl bg-primary px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:bg-primary-container active:scale-95"
                @click="goAbsensi(s)"
              >
                <span class="material-symbols-outlined text-[20px]">folder_open</span>
                <span>Buka</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Sekolah Saya -->
      <div v-if="dashboard.schools.length" class="flex flex-col gap-space-lg">
        <div class="flex flex-col justify-between gap-space-xs sm:flex-row sm:items-center">
          <div>
            <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-secondary"
              >Alokasi Wilayah &amp; Binaan</span
            >
            <h2 class="font-headline-lg text-headline-lg tracking-tight text-on-surface">Sekolah Saya</h2>
          </div>
          <span class="font-body-sm text-body-sm text-on-surface-variant"
            >Total {{ dashboard.schools_count }} Mitra Pendidikan Aktif</span
          >
        </div>
        <div class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-4">
          <div
            v-for="s in dashboard.schools"
            :key="s.id"
            class="flex flex-col justify-between rounded-2xl bg-surface-container-lowest p-space-md shadow-sm transition-all hover:shadow-md"
          >
            <div>
              <div
                class="mb-space-md flex h-24 items-center justify-center rounded-xl bg-gradient-to-br from-primary-fixed to-surface-container"
              >
                <span class="material-symbols-outlined text-[40px] text-primary">school</span>
              </div>
              <div class="mb-space-sm flex flex-col">
                <h3 class="font-headline-sm text-headline-sm font-bold leading-tight text-on-surface">
                  {{ s.name }}
                </h3>
                <span class="font-body-sm text-body-sm text-on-surface-variant">Sekolah Binaan</span>
              </div>
            </div>
            <div class="flex items-center justify-between border-t border-surface-container pt-space-sm">
              <div class="flex min-w-0 items-center gap-space-xs">
                <div
                  class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-secondary-fixed text-[11px] font-bold text-on-secondary-container"
                >
                  {{ initials(s.name) }}
                </div>
                <span class="truncate font-label-sm text-label-sm text-on-surface">Detail Sekolah</span>
              </div>
              <RouterLink
                to="/trainer/jadwal"
                class="rounded-lg p-1.5 text-secondary transition-colors hover:bg-secondary-fixed/50"
                title="Lihat jadwal"
              >
                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
