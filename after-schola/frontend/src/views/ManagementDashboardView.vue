<script setup>
// Dashboard Management — mengikuti design system Stitch "Remix" (Layar 6).
// Data dari GET /api/dashboard (respons peran management).
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { usePolling } from '@/lib/usePolling'

const loading = ref(true)
const errorMessage = ref('')
const dashboard = ref({
  totals: { schools: 0, students: 0, trainers: 0 },
  today: { sessions: 0, trainers_not_checked_in: [] },
})

const toast = ref({ show: false, message: '' })

const totals = computed(() => dashboard.value.totals ?? { schools: 0, students: 0, trainers: 0 })
const today = computed(
  () => dashboard.value.today ?? { sessions: 0, trainers_not_checked_in: [] },
)
const needAttention = computed(() => today.value.trainers_not_checked_in ?? [])

const todayText = computed(() =>
  new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  }),
)

function initials(name) {
  if (!name) return 'AS'
  return name
    .split(' ')
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('')
}

function remindTrainer(name) {
  // Pengiriman WhatsApp nyata belum tersedia; tampilkan konfirmasi lokal.
  toast.value = { show: true, message: `Pengingat untuk ${name} disiapkan (WhatsApp segera aktif).` }
  setTimeout(() => (toast.value.show = false), 4000)
}

async function loadDashboard(silent = false) {
  if (!silent) loading.value = true
  try {
    const { data } = await api.get('/dashboard')
    dashboard.value = {
      totals: data.totals ?? { schools: 0, students: 0, trainers: 0 },
      today: data.today ?? { sessions: 0, trainers_not_checked_in: [] },
    }
    errorMessage.value = ''
  } catch (err) {
    if (!silent) errorMessage.value = 'Gagal memuat dashboard. Coba muat ulang.'
  } finally {
    if (!silent) loading.value = false
  }
}

onMounted(() => loadDashboard())
// Live monitoring: perbarui otomatis tiap detik.
usePolling(() => loadDashboard(true), 1000)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <!-- Toast -->
    <div
      class="fixed bottom-6 right-6 z-50 flex items-center gap-space-sm rounded-xl bg-[#0B2A5B] px-space-md py-space-sm text-on-primary shadow-xl transition-all duration-300"
      :class="toast.show ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0'"
    >
      <span class="material-symbols-outlined text-emerald-400">check_circle</span>
      <span class="font-body-md text-body-md font-medium">{{ toast.message }}</span>
    </div>

    <!-- Header + aksi cepat -->
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex max-w-2xl flex-col gap-space-2xs">
        <div class="flex items-center gap-space-xs">
          <span
            class="inline-flex items-center rounded-full bg-secondary-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-secondary-container"
            >Live Monitoring</span
          >
          <span class="font-label-sm text-label-sm text-outline">{{ todayText }}</span>
        </div>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          Ringkasan Operasional &amp; Monitoring Harian
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Pantau kehadiran siswa, kepatuhan jadwal trainer, dan penugasan sesi kelas After Schola
          hari ini.
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start lg:self-center">
        <RouterLink
          to="/management/jadwal"
          class="flex items-center gap-space-xs rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          <span>Buat Pertemuan</span>
        </RouterLink>
        <RouterLink
          to="/management/manajemen-user"
          class="flex items-center gap-space-xs rounded-xl bg-surface-container px-space-md py-2.5 font-label-lg text-label-lg text-primary shadow-sm transition-all hover:bg-surface-container-high"
        >
          <span class="material-symbols-outlined text-[18px]">person_add</span>
          <span>Tambah User</span>
        </RouterLink>
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

    <!-- Skeleton -->
    <div v-if="loading" class="grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-32 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else>
      <!-- 4 stat cards -->
      <div class="mb-space-xl grid grid-cols-1 gap-space-md sm:grid-cols-2 lg:grid-cols-4">
        <div
          class="flex flex-col justify-between rounded-2xl bg-surface-container-lowest p-space-md shadow-sm transition-shadow hover:shadow-md"
        >
          <div class="flex items-start justify-between">
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-outline"
                >Total Sekolah Mitra</span
              >
              <span class="mt-1 font-headline-lg text-headline-lg font-extrabold text-on-surface"
                >{{ totals.schools }} Sekolah</span
              >
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container-low text-primary">
              <span class="material-symbols-outlined text-[24px]">school</span>
            </div>
          </div>
          <div class="mt-space-md flex items-center gap-1.5 pt-space-xs font-label-sm text-label-sm text-secondary">
            <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
            <span class="font-medium">Mitra aktif terdaftar</span>
          </div>
        </div>

        <div
          class="flex flex-col justify-between rounded-2xl bg-surface-container-lowest p-space-md shadow-sm transition-shadow hover:shadow-md"
        >
          <div class="flex items-start justify-between">
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-outline"
                >Total Murid Aktif</span
              >
              <span class="mt-1 font-headline-lg text-headline-lg font-extrabold text-on-surface"
                >{{ totals.students.toLocaleString('id-ID') }} Siswa</span
              >
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container-low text-primary">
              <span class="material-symbols-outlined text-[24px]">groups</span>
            </div>
          </div>
          <div class="mt-space-md flex items-center gap-1.5 pt-space-xs font-label-sm text-label-sm font-semibold text-emerald-600">
            <span class="material-symbols-outlined text-[16px]">trending_up</span>
            <span>Seluruh sekolah binaan</span>
          </div>
        </div>

        <div
          class="flex flex-col justify-between rounded-2xl bg-surface-container-lowest p-space-md shadow-sm transition-shadow hover:shadow-md"
        >
          <div class="flex items-start justify-between">
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-outline"
                >Total Trainer Aktif</span
              >
              <span class="mt-1 font-headline-lg text-headline-lg font-extrabold text-on-surface"
                >{{ totals.trainers }} Trainer</span
              >
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-container-low text-primary">
              <span class="material-symbols-outlined text-[24px]">badge</span>
            </div>
          </div>
          <div class="mt-space-md flex items-center gap-1.5 pt-space-xs font-label-sm text-label-sm text-on-surface-variant">
            <span class="font-semibold text-primary">{{ Math.max(0, totals.trainers - needAttention.length) }}</span>
            <span>patuh</span>
            <span class="text-outline">•</span>
            <span class="font-semibold text-secondary">{{ needAttention.length }}</span>
            <span>perlu tindakan</span>
          </div>
        </div>

        <div
          class="flex flex-col justify-between rounded-2xl bg-surface-container-lowest p-space-md shadow-sm transition-shadow hover:shadow-md"
        >
          <div class="flex items-start justify-between">
            <div class="flex flex-col">
              <span class="font-label-sm text-label-sm font-semibold uppercase tracking-wider text-outline"
                >Pertemuan Hari Ini</span
              >
              <span class="mt-1 font-headline-lg text-headline-lg font-extrabold text-primary"
                >{{ today.sessions }} Sesi</span
              >
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-fixed text-primary">
              <span class="material-symbols-outlined text-[24px]">fact_check</span>
            </div>
          </div>
          <div class="mt-space-md flex items-center justify-between pt-space-xs">
            <span class="rounded-full bg-emerald-50 px-2 py-0.5 font-label-sm text-label-sm font-semibold text-emerald-700"
              >Terjadwal hari ini</span
            >
            <span class="font-label-sm text-label-sm text-outline">Live</span>
          </div>
        </div>
      </div>

      <!-- Trainer belum absen -->
      <div class="mb-space-xl rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <div class="flex flex-col justify-between gap-space-xs pb-space-md sm:flex-row sm:items-center">
          <div class="flex items-center gap-space-xs">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-error-container text-error">
              <span class="material-symbols-outlined text-[20px]">warning</span>
            </div>
            <div>
              <h2 class="font-headline-md text-headline-md font-bold text-on-surface">
                Trainer Perlu Perhatian Hari Ini
              </h2>
              <p class="font-body-sm text-body-sm text-on-surface-variant">
                Prioritas follow-up kepatuhan check-in operasional lapangan
              </p>
            </div>
          </div>
          <span
            class="self-start rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold sm:self-center"
            :class="needAttention.length ? 'bg-error-container text-error' : 'bg-emerald-50 text-emerald-700'"
          >
            {{ needAttention.length }} Kebutuhan Tindakan
          </span>
        </div>

        <!-- Kosong -->
        <div
          v-if="!needAttention.length"
          class="flex flex-col items-center justify-center rounded-xl bg-surface-container-low px-space-lg py-space-xl text-center"
        >
          <span class="material-symbols-outlined text-[32px] text-emerald-500">task_alt</span>
          <p class="mt-space-xs font-headline-sm text-headline-sm text-on-surface">Semua trainer patuh</p>
          <p class="font-body-sm text-body-sm text-on-surface-variant">
            Tidak ada trainer yang tertunda check-in untuk sesi hari ini.
          </p>
        </div>

        <!-- Tabel -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr
                class="bg-surface-container-low font-label-sm text-label-sm uppercase tracking-wider text-on-surface"
              >
                <th class="rounded-l-xl p-space-sm">Trainer</th>
                <th class="p-space-sm">Status Kepatuhan</th>
                <th class="rounded-r-xl p-space-sm text-right">Aksi Cepat</th>
              </tr>
            </thead>
            <tbody class="font-body-md text-body-md">
              <tr
                v-for="t in needAttention"
                :key="t.id"
                class="transition-colors hover:bg-surface-container-low"
              >
                <td class="p-space-sm font-semibold text-on-surface">
                  <div class="flex items-center gap-space-xs">
                    <div
                      class="flex h-8 w-8 items-center justify-center rounded-full bg-[#ABC3FE] text-[12px] font-bold text-[#001A42]"
                    >
                      {{ initials(t.name) }}
                    </div>
                    <span>{{ t.name }}</span>
                  </div>
                </td>
                <td class="p-space-sm">
                  <span
                    class="inline-flex items-center gap-1 rounded-full bg-error-container px-2.5 py-0.5 font-label-sm text-label-sm font-semibold text-error"
                  >
                    <span class="h-1.5 w-1.5 rounded-full bg-error"></span>
                    Belum Check-in
                  </span>
                </td>
                <td class="p-space-sm text-right">
                  <button
                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-50 px-3 py-1.5 font-label-sm text-label-sm font-semibold text-emerald-700 shadow-xs transition-colors hover:bg-emerald-100"
                    @click="remindTrainer(t.name)"
                  >
                    <span class="material-symbols-outlined text-[16px]">chat</span>
                    <span>Ingatkan</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Grid: tren + log -->
      <div class="mb-space-2xl grid grid-cols-1 gap-space-md lg:grid-cols-12">
        <!-- Tren kehadiran (visualisasi) -->
        <div
          class="flex flex-col justify-between rounded-2xl bg-surface-container-lowest p-space-md shadow-sm lg:col-span-8"
        >
          <div>
            <div class="mb-space-md flex flex-col justify-between gap-space-xs sm:flex-row sm:items-center">
              <div>
                <h2 class="font-headline-md text-headline-md font-bold text-on-surface">
                  Tren Kehadiran 30 Hari
                </h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                  Perbandingan rasio kepatuhan sesi pengajaran dan partisipasi siswa
                </p>
              </div>
              <div class="flex items-center gap-space-sm self-start sm:self-auto">
                <div class="flex items-center gap-1.5">
                  <span class="h-3 w-3 rounded-full bg-[#0037B0]"></span>
                  <span class="font-label-sm text-label-sm font-medium text-on-surface">Siswa</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <span class="h-3 w-3 rounded-full bg-[#1D4ED8]"></span>
                  <span class="font-label-sm text-label-sm font-medium text-on-surface">Trainer</span>
                </div>
              </div>
            </div>
            <div class="relative mt-space-sm h-64 w-full sm:h-72">
              <svg class="h-full w-full overflow-visible" preserveAspectRatio="none" viewBox="0 0 700 240">
                <line stroke="#E5EEFF" stroke-dasharray="4,4" stroke-width="1" x1="0" x2="700" y1="40" y2="40" />
                <line stroke="#E5EEFF" stroke-dasharray="4,4" stroke-width="1" x1="0" x2="700" y1="100" y2="100" />
                <line stroke="#E5EEFF" stroke-dasharray="4,4" stroke-width="1" x1="0" x2="700" y1="160" y2="160" />
                <line stroke="#E5EEFF" stroke-width="1" x1="0" x2="700" y1="220" y2="220" />
                <defs>
                  <linearGradient id="trainerGrad" x1="0%" x2="0%" y1="0%" y2="100%">
                    <stop offset="0%" stop-color="#1D4ED8" stop-opacity="0.18" />
                    <stop offset="100%" stop-color="#1D4ED8" stop-opacity="0" />
                  </linearGradient>
                  <linearGradient id="studentGrad" x1="0%" x2="0%" y1="0%" y2="100%">
                    <stop offset="0%" stop-color="#0037B0" stop-opacity="0.12" />
                    <stop offset="100%" stop-color="#0037B0" stop-opacity="0" />
                  </linearGradient>
                </defs>
                <path d="M 0 110 C 80 80, 160 50, 240 60 C 320 70, 400 30, 480 35 C 560 40, 630 25, 700 30 L 700 220 L 0 220 Z" fill="url(#trainerGrad)" />
                <path d="M 0 110 C 80 80, 160 50, 240 60 C 320 70, 400 30, 480 35 C 560 40, 630 25, 700 30" fill="none" stroke="#1D4ED8" stroke-linecap="round" stroke-width="3" />
                <path d="M 0 140 C 90 120, 170 130, 250 90 C 330 50, 410 80, 490 60 C 570 40, 640 55, 700 45 L 700 220 L 0 220 Z" fill="url(#studentGrad)" />
                <path d="M 0 140 C 90 120, 170 130, 250 90 C 330 50, 410 80, 490 60 C 570 40, 640 55, 700 45" fill="none" stroke="#0037B0" stroke-dasharray="6 3" stroke-linecap="round" stroke-width="3" />
              </svg>
            </div>
          </div>
          <div
            class="flex justify-between border-t border-surface-container-low pt-space-xs font-label-sm text-label-sm text-outline"
          >
            <span>Hari 1</span><span>Hari 7</span><span>Hari 14</span><span>Hari 21</span
            ><span>Hari 28</span><span>Hari Ini</span>
          </div>
          <p class="pt-space-xs font-body-sm text-body-sm text-outline">
            * Visualisasi tren; data historis agregat menyusul.
          </p>
        </div>

        <!-- Log aktivitas -->
        <div
          class="flex flex-col rounded-2xl bg-surface-container-lowest p-space-md shadow-sm lg:col-span-4"
        >
          <div class="mb-space-sm flex items-center justify-between pb-space-sm">
            <div class="flex items-center gap-space-xs">
              <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>
              <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Log Aktivitas</h2>
            </div>
          </div>
          <div v-if="needAttention.length" class="flex flex-col gap-space-md">
            <div v-for="t in needAttention" :key="t.id" class="flex items-start gap-space-sm">
              <div
                class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-error-container text-error"
              >
                <span class="material-symbols-outlined text-[16px]">schedule</span>
              </div>
              <div class="flex min-w-0 flex-col">
                <span class="truncate font-label-lg text-label-lg font-semibold text-on-surface"
                  >{{ t.name }} belum check-in</span
                >
                <span class="font-body-sm text-body-sm text-on-surface-variant"
                  >Sesi hari ini menunggu kehadiran trainer</span
                >
              </div>
            </div>
          </div>
          <div
            v-else
            class="flex flex-1 flex-col items-center justify-center py-space-xl text-center"
          >
            <span class="material-symbols-outlined text-[28px] text-outline">history</span>
            <p class="mt-space-xs font-body-sm text-body-sm text-on-surface-variant">
              Belum ada aktivitas yang perlu ditampilkan.
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
