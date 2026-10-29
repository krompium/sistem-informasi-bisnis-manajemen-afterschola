<script setup>
// Daftar proyek (Modul 12, FR-12.2). Developer: proyek di team-nya, boleh kelola.
// Management: semua proyek, read-only. Tombol tulis hanya kosmetik, backend yang menjaga akses.
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateShort, unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { confirmDialog } from '@/lib/feedback'

const auth = useAuthStore()

const canWrite = computed(() => auth.isDeveloper && !auth.isManagement)
const basePath = computed(() => (auth.isManagement ? '/management/proyek' : '/developer/proyek'))

const STATUS = {
  planning: { label: 'Perencanaan', cls: 'bg-surface-container text-on-surface-variant' },
  ongoing: { label: 'Berjalan', cls: 'bg-primary-fixed text-primary' },
  done: { label: 'Selesai', cls: 'bg-[#D1FAE5] text-[#065F46]' },
}

const loading = ref(true)
const errorMessage = ref('')
const projects = ref([])
const teams = ref([])
const teamFilter = ref('')
const page = ref(1)
const lastPage = ref(1)

const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ team_id: '', name: '', description: '', status: 'planning', start_date: '', due_date: '' })

const teamName = (id) => teams.value.find((t) => t.id === id)?.name || '—'

async function loadProjects() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/projects', {
      params: { page: page.value, team_id: teamFilter.value || undefined },
    })
    projects.value = unwrap(data)
    lastPage.value = data.meta?.last_page ?? 1
  } catch (err) {
    errorMessage.value = 'Gagal memuat proyek.'
  } finally {
    loading.value = false
  }
}

async function loadTeams() {
  try {
    // TODO(nanti): /teams dipaginasi (15 per halaman) — cukup untuk jumlah team saat ini.
    const { data } = await api.get('/teams')
    teams.value = unwrap(data)
  } catch (err) {
    /* daftar proyek tetap jalan tanpa nama team */
  }
}

function onFilter() {
  page.value = 1
  loadProjects()
}

function goTo(n) {
  page.value = n
  loadProjects()
}

function openCreate() {
  editing.value = null
  Object.assign(form, {
    team_id: teams.value.length === 1 ? teams.value[0].id : '',
    name: '',
    description: '',
    status: 'planning',
    start_date: new Date().toISOString().slice(0, 10),
    due_date: '',
  })
  formError.value = ''
  showModal.value = true
}

function openEdit(p) {
  editing.value = p
  Object.assign(form, {
    team_id: p.team_id,
    name: p.name,
    description: p.description ?? '',
    status: p.status,
    start_date: p.start_date,
    due_date: p.due_date,
  })
  formError.value = ''
  showModal.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  const payload = {
    name: form.name,
    description: form.description,
    status: form.status,
    start_date: form.start_date,
    due_date: form.due_date,
  }
  try {
    if (editing.value) {
      await api.put(`/projects/${editing.value.id}`, payload)
    } else {
      await api.post('/projects', { ...payload, team_id: form.team_id })
    }
    showModal.value = false
    await loadProjects()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan proyek.')
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!(await confirmDialog(`Hapus proyek "${editing.value.name}"?`, { title: 'Hapus data?', danger: true, confirmText: 'Ya, hapus' }))) return
  saving.value = true
  formError.value = ''
  try {
    await api.delete(`/projects/${editing.value.id}`)
    showModal.value = false
    if (projects.value.length === 1 && page.value > 1) page.value -= 1
    await loadProjects()
  } catch (err) {
    // 422 bila proyek masih punya task (Q5).
    formError.value = apiErrorMessage(err, 'Gagal menghapus proyek.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadProjects()
  loadTeams()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Manajemen Proyek</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Proyek</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          {{ canWrite ? 'Kelola proyek dan task team kamu.' : 'Pantau proyek dan task semua team.' }}
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start lg:self-center">
        <select
          v-if="teams.length > 1"
          v-model="teamFilter"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
          @change="onFilter"
        >
          <option value="">Semua team</option>
          <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
        <button
          v-if="canWrite"
          class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
          @click="openCreate"
        >
          <span class="material-symbols-outlined text-[18px]">add</span>
          <span>Proyek Baru</span>
        </button>
      </div>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadProjects">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-40 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!projects.length && !errorMessage" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">view_kanban</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada proyek</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
        {{ canWrite ? 'Klik "Proyek Baru" untuk menambah.' : 'Proyek dari tiap team akan tampil di sini.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-3">
      <div v-for="p in projects" :key="p.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex items-start justify-between gap-space-sm">
          <div class="flex min-w-0 flex-col">
            <h3 class="truncate font-headline-sm text-headline-sm font-bold text-on-surface">{{ p.name }}</h3>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ teamName(p.team_id) }}</span>
          </div>
          <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold" :class="STATUS[p.status]?.cls">
            {{ STATUS[p.status]?.label || p.status }}
          </span>
        </div>
        <p v-if="p.description" class="line-clamp-2 font-body-sm text-body-sm text-on-surface-variant">{{ p.description }}</p>
        <div class="flex items-center gap-1 font-label-md text-label-md text-outline">
          <span class="material-symbols-outlined text-[16px]">event</span>
          {{ formatDateShort(p.start_date) }} – {{ formatDateShort(p.due_date) }}
        </div>
        <div class="mt-auto flex items-center justify-between border-t border-surface-container pt-space-sm">
          <router-link :to="`${basePath}/${p.id}`" class="inline-flex items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline">
            <span class="material-symbols-outlined text-[16px]">view_kanban</span> Buka board
          </router-link>
          <button v-if="canWrite" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" title="Ubah proyek" @click="openEdit(p)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="lastPage > 1" class="mt-space-lg flex items-center justify-center gap-space-md">
      <button class="rounded-xl px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container disabled:opacity-40" :disabled="page <= 1" @click="goTo(page - 1)">Sebelumnya</button>
      <span class="font-body-sm text-body-sm text-on-surface-variant">Halaman {{ page }} dari {{ lastPage }}</span>
      <button class="rounded-xl px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container disabled:opacity-40" :disabled="page >= lastPage" @click="goTo(page + 1)">Berikutnya</button>
    </div>

    <!-- Modal buat / ubah proyek -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showModal = false">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Proyek' : 'Proyek Baru' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Team</span>
            <select v-model="form.team_id" required :disabled="!!editing" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15 disabled:bg-surface-container-low">
              <option value="" disabled>Pilih team…</option>
              <option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Proyek</span>
            <input v-model="form.name" type="text" required maxlength="255" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Deskripsi <span class="font-normal text-outline">(opsional)</span></span>
            <textarea v-model="form.description" rows="2" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Status</span>
            <select v-model="form.status" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
              <option v-for="(s, key) in STATUS" :key="key" :value="key">{{ s.label }}</option>
            </select>
          </label>
          <div class="grid grid-cols-2 gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mulai</span>
              <input v-model="form.start_date" type="date" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tenggat</span>
              <input v-model="form.due_date" type="date" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>
          </div>

          <div class="flex items-center justify-between gap-space-sm border-t border-surface-container pt-space-md">
            <button v-if="editing" type="button" :disabled="saving" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-error hover:bg-error-container/50 disabled:opacity-60" @click="remove">Hapus</button>
            <span v-else></span>
            <div class="flex items-center gap-space-sm">
              <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showModal = false">Batal</button>
              <button type="submit" :disabled="saving" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
                <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                Simpan
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
