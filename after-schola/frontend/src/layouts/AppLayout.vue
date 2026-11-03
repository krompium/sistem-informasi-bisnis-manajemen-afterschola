<script setup>
// Shell aplikasi (desktop-first) mengikuti design system Stitch "Remix":
// sidebar navy #0B2A5B + topbar sticky. Menu menyesuaikan peran user.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/lib/api'
import { formatDate, isOnsite, modeLabel, unwrap } from '@/lib/format'
import { usePolling } from '@/lib/usePolling'
import HotIssueModal from '@/components/HotIssueModal.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const sidebarOpen = ref(false) // drawer untuk layar kecil

// Susunan menu Management. Untuk memindah / mengganti nama / mengubah urutan menu: cukup ubah daftar ini.
// - Item biasa  : { label, icon, to }
// - Grup        : punya `children` (menu anak tampil menjorok ke dalam, bisa dibuka-tutup).
//                 Kalau grup punya `to`, klik labelnya membuka halaman itu; tanpa `to` hanya buka-tutup.
const managementNav = [
  { label: 'Dashboard', icon: 'dashboard', to: '/management' },
  {
    label: 'Manajemen Ekstrakurikuler',
    icon: 'school',
    to: '/management/ekstrakurikuler',
    children: [
      { label: 'Sekolah & Murid', icon: 'school', to: '/management/sekolah-murid' },
      { label: 'Trainer', icon: 'assignment_ind', to: '/management/trainer' },
      { label: 'Jadwal', icon: 'calendar_month', to: '/management/jadwal' },
      { label: 'Rekap Absensi', icon: 'summarize', to: '/management/absensi' },
      { label: 'Laporan Ekspo', icon: 'query_stats', to: '/management/laporan-ekspo' },
    ],
  },
  {
    label: 'Pendaftaran',
    icon: 'person_add',
    children: [
      { label: 'Evaluasi Trial', icon: 'rate_review', to: '/management/evaluasi-trial' },
      { label: 'Keputusan & Pendaftaran', icon: 'how_to_reg', to: '/management/keputusan-pendaftaran' },
      { label: 'Program & Promosi', icon: 'inventory_2', to: '/management/program' },
    ],
  },
  { label: 'Manajemen User', icon: 'manage_accounts', to: '/management/manajemen-user' },
  { label: 'Hot Issue', icon: 'campaign', to: '/management/hot-issue' },
]

const trainerNav = [
  { label: 'Dashboard', icon: 'dashboard', to: '/trainer' },
  { label: 'Sekolah', icon: 'school', to: '/trainer/sekolah' },
  { label: 'Jadwal Saya', icon: 'calendar_month', to: '/trainer/jadwal' },
  { label: 'Absensi', icon: 'fact_check', to: '/trainer/absensi' },
  { label: 'Laporan Ekspo', icon: 'query_stats', to: '/trainer/laporan-ekspo' },
  { label: 'Evaluasi Trial', icon: 'rate_review', to: '/trainer/evaluasi-trial' },
]

const navItems = computed(() => (auth.isManagement ? managementNav : trainerNav))

const roleBadge = computed(() =>
  auth.isManagement
    ? { text: 'Management', cls: 'bg-secondary-container text-on-secondary-container' }
    : { text: 'Trainer', cls: 'bg-tertiary-fixed text-on-tertiary-fixed' },
)

const userName = computed(() => auth.user?.name || 'Pengguna')

const initials = computed(() => {
  const n = auth.user?.name
  if (!n) return 'AS'
  return n
    .split(' ')
    .slice(0, 2)
    .map((w) => w.charAt(0).toUpperCase())
    .join('')
})

function isActive(to) {
  // Dashboard cocok persis; menu lain menyorot juga pada sub-rute (mis. /trainer/absensi/5).
  if (to === '/trainer' || to === '/management' || to === '/management/ekstrakurikuler') return route.path === to
  return route.path === to || route.path.startsWith(to + '/')
}

// ---- Grup menu (buka-tutup) ----
const expanded = reactive({}) // label grup → true/false (pilihan manual user)

// Grup "aktif" bila salah satu anaknya (atau halaman grup itu sendiri) sedang dibuka.
function groupActive(item) {
  return (item.to ? isActive(item.to) : false) || (item.children ?? []).some((c) => isActive(c.to))
}
function isOpen(item) {
  return expanded[item.label] ?? groupActive(item)
}
function toggleGroup(item) {
  expanded[item.label] = !isOpen(item)
}
// Klik label grup yang punya halaman sendiri: buka halamannya + pastikan grup terbuka.
function onGroupLink(item) {
  expanded[item.label] = true
  sidebarOpen.value = false
}
// Pindah halaman (mis. dari dashboard kartu) → grup yang berisi halaman itu otomatis terbuka.
watch(
  () => route.path,
  () => {
    navItems.value.forEach((item) => {
      if (item.children && groupActive(item)) expanded[item.label] = true
    })
  },
  { immediate: true },
)

// Gaya menu
const itemBase = 'flex items-center gap-space-sm rounded-xl px-space-md py-space-sm font-label-lg text-label-lg transition-all'
const itemActive = 'bg-primary-container text-on-primary shadow-sm'
const itemIdle = 'text-[#E2E8F0] hover:bg-white/10 hover:text-on-primary'
const childBase = 'flex items-center gap-space-sm rounded-lg px-space-sm py-2 font-label-md text-label-md transition-all'

// Baris grup: biru penuh bila halaman grup itu sendiri yang aktif; redup bila salah satu anak yang aktif.
function groupRowClass(item) {
  if (item.to && isActive(item.to)) return itemActive
  if (groupActive(item)) return 'bg-white/10 text-on-primary'
  return itemIdle
}

// ---- Notifikasi: sesi baru yang dijadwalkan Management (deteksi via sesi belum dilihat) ----
const notifOpen = ref(false)
const notifItems = ref([])

function seenKey() {
  return `as_seen_sessions_${auth.user?.id || 'x'}`
}
function loadSeen() {
  try {
    return new Set(JSON.parse(localStorage.getItem(seenKey()) || '[]'))
  } catch {
    return new Set()
  }
}
function persistSeen(ids) {
  try {
    localStorage.setItem(seenKey(), JSON.stringify([...ids]))
  } catch {
    /* localStorage tak tersedia — abaikan */
  }
}

async function loadNotifications() {
  if (!auth.isTrainer) return
  try {
    const { data } = await api.get('/sessions')
    const list = unwrap(data)
    const seen = loadSeen()
    notifItems.value = list
      .filter((s) => !seen.has(s.id))
      .sort((a, b) => b.id - a.id)
      .slice(0, 12)
  } catch {
    /* diamkan; notifikasi non-kritis */
  }
}

function notifText(s) {
  return `${s.school?.name || 'Sekolah'} — ${s.classroom?.name || s.classroom?.level || 'Level'} • Pertemuan #${s.meeting_no}`
}

function toggleNotif() {
  notifOpen.value = !notifOpen.value
}

function openNotif(s) {
  notifOpen.value = false
  markAllSeen()
  router.push(`/trainer/absensi/${s.id}`)
}

function markAllSeen() {
  const seen = loadSeen()
  notifItems.value.forEach((s) => seen.add(s.id))
  persistSeen(seen)
  notifItems.value = []
}

onMounted(loadNotifications)
// Polling per detik: notifikasi pertemuan baru muncul hampir real-time.
usePolling(loadNotifications, 1000)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-background text-on-surface">
    <!-- Backdrop drawer (mobile) -->
    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-[#0B2A5B]/40 backdrop-blur-sm lg:hidden"
      @click="sidebarOpen = false"
    ></div>

    <!-- ================= SIDEBAR ================= -->
    <aside
      class="fixed left-0 top-0 z-50 flex h-full w-sidebar-width flex-col justify-between bg-[#0B2A5B] py-space-md shadow-[0_1px_8px_rgba(0,0,0,0.04)] transition-transform duration-300 lg:translate-x-0 no-scrollbar overflow-y-auto"
      :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex flex-col">
        <!-- Brand: untuk Management, klik logo balik ke Dashboard Manajemen Bisnis -->
        <component
          :is="auth.isManagement ? 'RouterLink' : 'div'"
          :to="auth.isManagement ? '/management' : undefined"
          class="flex items-center gap-space-sm px-space-md pb-space-lg"
        >
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-container">
            <span class="material-symbols-outlined fill text-[20px] text-white">auto_stories</span>
          </div>
          <div class="flex flex-col">
            <span class="font-headline-sm text-headline-sm font-bold tracking-tight text-on-primary"
              >After Schola</span
            >
            <span class="font-label-sm text-label-sm uppercase tracking-wider text-[#ABC3FE]"
              >Operasional</span
            >
          </div>
        </component>

        <div class="mb-space-xs px-space-md">
          <span class="font-label-sm text-label-sm uppercase tracking-wider text-[#ABC3FE]/70"
            >Menu Utama</span
          >
        </div>

        <!-- Navigasi (item biasa + grup yang bisa dibuka-tutup) -->
        <nav class="flex flex-col gap-space-2xs px-space-sm">
          <template v-for="item in navItems" :key="item.label">
            <!-- Item biasa -->
            <RouterLink
              v-if="!item.children"
              :to="item.to"
              :class="[itemBase, isActive(item.to) ? itemActive : itemIdle]"
              @click="sidebarOpen = false"
            >
              <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
              <span>{{ item.label }}</span>
            </RouterLink>

            <!-- Grup menu -->
            <div v-else class="flex flex-col">
              <div class="flex items-center rounded-xl transition-all" :class="groupRowClass(item)">
                <RouterLink
                  v-if="item.to"
                  :to="item.to"
                  class="flex flex-1 items-center gap-space-sm px-space-md py-space-sm font-label-lg text-label-lg"
                  @click="onGroupLink(item)"
                >
                  <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
                  <span class="text-left">{{ item.label }}</span>
                </RouterLink>
                <button
                  v-else
                  type="button"
                  class="flex flex-1 items-center gap-space-sm px-space-md py-space-sm text-left font-label-lg text-label-lg"
                  @click="toggleGroup(item)"
                >
                  <span class="material-symbols-outlined text-[20px]">{{ item.icon }}</span>
                  <span>{{ item.label }}</span>
                </button>
                <button
                  type="button"
                  class="px-space-sm py-space-sm opacity-80 hover:opacity-100"
                  :aria-label="isOpen(item) ? 'Tutup grup ' + item.label : 'Buka grup ' + item.label"
                  :aria-expanded="isOpen(item)"
                  @click="toggleGroup(item)"
                >
                  <span
                    class="material-symbols-outlined text-[20px] transition-transform duration-200"
                    :class="isOpen(item) ? 'rotate-180' : ''"
                    >expand_more</span
                  >
                </button>
              </div>

              <!-- Menu anak: menjorok ke dalam + garis pemandu di kiri -->
              <div v-show="isOpen(item)" class="ml-[30px] mt-0.5 flex flex-col gap-0.5 border-l border-white/15 pl-2">
                <RouterLink
                  v-for="child in item.children"
                  :key="child.to"
                  :to="child.to"
                  :class="[childBase, isActive(child.to) ? itemActive : itemIdle]"
                  @click="sidebarOpen = false"
                >
                  <span class="material-symbols-outlined text-[18px]">{{ child.icon }}</span>
                  <span>{{ child.label }}</span>
                </RouterLink>
              </div>
            </div>
          </template>
        </nav>
      </div>

      <!-- Keluar -->
      <div class="border-t border-white/10 px-space-sm pt-space-md">
        <button
          class="flex w-full items-center gap-space-sm rounded-xl px-space-md py-space-sm font-label-lg text-label-lg text-[#FEE2E2] transition-all hover:bg-error/20 hover:text-white"
          @click="handleLogout"
        >
          <span class="material-symbols-outlined text-[20px]">logout</span>
          <span>Keluar</span>
        </button>
      </div>
    </aside>

    <!-- ================= AREA KONTEN ================= -->
    <div class="lg:pl-sidebar-width">
      <!-- Topbar -->
      <header
        class="fixed left-0 right-0 top-0 z-40 flex h-topbar-height items-center justify-between bg-surface-container-lowest/90 px-space-md backdrop-blur-xl shadow-[0_1px_8px_rgba(0,0,0,0.04)] sm:px-space-xl lg:left-sidebar-width"
      >
        <div class="flex items-center gap-space-sm">
          <!-- Hamburger (mobile) -->
          <button
            class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low lg:hidden"
            aria-label="Buka menu"
            @click="sidebarOpen = true"
          >
            <span class="material-symbols-outlined text-[22px]">menu</span>
          </button>
          <!-- Pencarian -->
          <div class="relative hidden w-72 sm:block">
            <span
              class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline"
              >search</span
            >
            <input
              type="text"
              placeholder="Cari data sekolah, trainer..."
              class="w-full rounded-lg bg-surface-container-low py-1.5 pl-9 pr-space-md font-body-sm text-body-sm text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
        </div>

        <div class="flex items-center gap-space-lg">
          <div class="relative">
            <button
              class="relative rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-on-surface"
              aria-label="Notifikasi"
              @click="toggleNotif"
            >
              <span class="material-symbols-outlined text-[22px]">notifications</span>
              <span
                v-if="notifItems.length"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-on-error ring-2 ring-surface-container-lowest"
                >{{ notifItems.length > 9 ? '9+' : notifItems.length }}</span
              >
            </button>

            <!-- Dropdown notifikasi -->
            <div v-if="notifOpen" class="fixed inset-0 z-40" @click="notifOpen = false"></div>
            <div
              v-if="notifOpen"
              class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl ring-1 ring-surface-container animate-fade-in"
            >
              <div class="flex items-center justify-between border-b border-surface-container px-space-md py-space-sm">
                <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Notifikasi</span>
                <button
                  v-if="notifItems.length"
                  class="font-label-sm text-label-sm font-semibold text-primary hover:underline"
                  @click="markAllSeen"
                >
                  Tandai dibaca
                </button>
              </div>
              <div v-if="!notifItems.length" class="flex flex-col items-center gap-1 px-space-md py-space-lg text-center">
                <span class="material-symbols-outlined text-[28px] text-outline">notifications_off</span>
                <span class="font-body-sm text-body-sm text-on-surface-variant">Belum ada notifikasi baru</span>
              </div>
              <div v-else class="max-h-80 overflow-y-auto">
                <button
                  v-for="s in notifItems"
                  :key="s.id"
                  class="flex w-full items-start gap-space-sm border-b border-surface-container px-space-md py-space-sm text-left transition-colors last:border-0 hover:bg-surface-container-low"
                  @click="openNotif(s)"
                >
                  <span
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="isOnsite(s.mode) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-secondary-fixed text-on-secondary-container'"
                  >
                    <span class="material-symbols-outlined text-[16px]">event_available</span>
                  </span>
                  <span class="flex min-w-0 flex-col">
                    <span class="font-label-lg text-label-lg font-semibold text-on-surface">Pertemuan baru dijadwalkan</span>
                    <span class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ notifText(s) }}</span>
                    <span class="font-label-sm text-label-sm text-outline">{{ formatDate(s.date, { weekday: 'short', day: 'numeric', month: 'short' }) }} • {{ modeLabel(s.mode) }}</span>
                  </span>
                </button>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-space-sm pl-space-sm">
            <div class="hidden flex-col items-end sm:flex">
              <span class="font-label-lg text-label-lg leading-tight text-on-surface">{{
                userName
              }}</span>
              <span
                class="mt-0.5 inline-flex items-center rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold tracking-wide"
                :class="roleBadge.cls"
                >{{ roleBadge.text }}</span
              >
            </div>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary-container font-bold text-[13px] text-on-secondary-container ring-2 ring-secondary-container"
            >
              {{ initials }}
            </div>
          </div>
        </div>
      </header>

      <!-- Halaman -->
      <main class="min-h-screen w-full bg-background px-gutter-mobile pt-topbar-height sm:px-gutter-desktop">
        <RouterView />
      </main>
    </div>

    <!-- Popup Hot Issue: dicek sekali saat dashboard dimuat (baru login/refresh),
         muncul untuk semua role bila ada isu aktif yang belum ditutup. -->
    <HotIssueModal />
  </div>
</template>