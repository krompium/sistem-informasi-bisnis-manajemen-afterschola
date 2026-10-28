<script setup>
// Kelola team dev (Modul 12, FR-12.1). Hanya Management yang membuat/mengubah/menghapus team
// dan anggotanya (keputusan Q1); backend yang menjaga akses.
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const loading = ref(true)
const errorMessage = ref('')
const teams = ref([])
const page = ref(1)
const lastPage = ref(1)
const developers = ref([])

const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ name: '', description: '', members: [] }) // members: [{ user_id, role_in_team }]

// Pilihan anggota = semua developer aktif + anggota team ini yang mungkin sudah tidak masuk daftar,
// supaya tidak terhapus diam-diam saat disimpan.
const choices = computed(() => {
  const list = developers.value.filter((u) => u.is_active !== false).map((u) => ({ id: u.id, name: u.name }))
  for (const m of editing.value?.members ?? []) {
    if (!list.some((u) => u.id === m.id)) list.push({ id: m.id, name: m.name })
  }
  return list
})

const memberOf = (userId) => form.members.find((m) => m.user_id === userId)

function toggleMember(userId, checked) {
  if (checked) {
    if (!memberOf(userId)) form.members.push({ user_id: userId, role_in_team: 'member' })
  } else {
    form.members = form.members.filter((m) => m.user_id !== userId)
  }
}

async function loadTeams() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/teams', { params: { page: page.value } })
    teams.value = unwrap(data)
    lastPage.value = data.meta?.last_page ?? 1
  } catch (err) {
    errorMessage.value = 'Gagal memuat team.'
  } finally {
    loading.value = false
  }
}

async function loadDevelopers() {
  try {
    const { data } = await api.get('/users', { params: { role: 'developer' } })
    developers.value = unwrap(data)
  } catch (err) {
    /* form tetap bisa dipakai; daftar anggota kosong */
  }
}

function goTo(n) {
  page.value = n
  loadTeams()
}

function openCreate() {
  editing.value = null
  Object.assign(form, { name: '', description: '', members: [] })
  formError.value = ''
  showModal.value = true
}

function openEdit(t) {
  editing.value = t
  Object.assign(form, {
    name: t.name,
    description: t.description ?? '',
    members: (t.members ?? []).map((m) => ({ user_id: m.id, role_in_team: m.role_in_team })),
  })
  formError.value = ''
  showModal.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  const payload = { name: form.name, description: form.description, members: form.members }
  try {
    if (editing.value) {
      await api.put(`/teams/${editing.value.id}`, payload)
    } else {
      await api.post('/teams', payload)
    }
    showModal.value = false
    await loadTeams()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan team.')
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!window.confirm(`Hapus team "${editing.value.name}"?`)) return
  saving.value = true
  formError.value = ''
  try {
    await api.delete(`/teams/${editing.value.id}`)
    showModal.value = false
    if (teams.value.length === 1 && page.value > 1) page.value -= 1
    await loadTeams()
  } catch (err) {
    // 422 bila team masih punya proyek (Q5).
    formError.value = apiErrorMessage(err, 'Gagal menghapus team.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadTeams()
  loadDevelopers()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Manajemen Proyek</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Team</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Buat team dev dan atur anggotanya.</p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openCreate"
      >
        <span class="material-symbols-outlined text-[18px]">group_add</span>
        <span>Team Baru</span>
      </button>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadTeams">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-40 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!teams.length && !errorMessage" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">groups</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada team</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Klik "Team Baru" untuk menambah.</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-3">
      <div v-for="t in teams" :key="t.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex items-start justify-between gap-space-sm">
          <div class="flex min-w-0 flex-col">
            <h3 class="truncate font-headline-sm text-headline-sm font-bold text-on-surface">{{ t.name }}</h3>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ t.projects_count ?? 0 }} proyek</span>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" title="Ubah team" @click="openEdit(t)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
        </div>
        <p v-if="t.description" class="line-clamp-2 font-body-sm text-body-sm text-on-surface-variant">{{ t.description }}</p>
        <ul class="mt-auto flex flex-col gap-1 border-t border-surface-container pt-space-sm">
          <li v-for="m in t.members" :key="m.id" class="flex items-center justify-between font-body-sm text-body-sm text-on-surface">
            <span>{{ m.name }}</span>
            <span class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold" :class="m.role_in_team === 'lead' ? 'bg-primary-fixed text-primary' : 'bg-surface-container text-on-surface-variant'">
              {{ m.role_in_team === 'lead' ? 'Lead' : 'Member' }}
            </span>
          </li>
          <li v-if="!t.members?.length" class="font-body-sm text-body-sm text-outline">Belum ada anggota</li>
        </ul>
      </div>
    </div>

    <div v-if="lastPage > 1" class="mt-space-lg flex items-center justify-center gap-space-md">
      <button class="rounded-xl px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container disabled:opacity-40" :disabled="page <= 1" @click="goTo(page - 1)">Sebelumnya</button>
      <span class="font-body-sm text-body-sm text-on-surface-variant">Halaman {{ page }} dari {{ lastPage }}</span>
      <button class="rounded-xl px-space-md py-2 font-label-lg text-label-lg text-on-surface hover:bg-surface-container disabled:opacity-40" :disabled="page >= lastPage" @click="goTo(page + 1)">Berikutnya</button>
    </div>

    <!-- Modal buat / ubah team -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showModal = false">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Team' : 'Team Baru' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Team</span>
            <input v-model="form.name" type="text" required maxlength="255" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Deskripsi <span class="font-normal text-outline">(opsional)</span></span>
            <textarea v-model="form.description" rows="2" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
          </label>

          <div class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Anggota <span class="font-normal text-outline">(user dengan role Developer)</span></span>
            <p v-if="!choices.length" class="rounded-lg bg-surface-container-low px-space-sm py-space-sm font-body-sm text-body-sm text-on-surface-variant">
              Belum ada user Developer. Buat dulu di Manajemen User.
            </p>
            <ul v-else class="flex max-h-56 flex-col divide-y divide-surface-container overflow-y-auto rounded-lg border border-outline-variant">
              <li v-for="u in choices" :key="u.id" class="flex items-center justify-between gap-space-sm px-space-sm py-2">
                <label class="flex min-w-0 items-center gap-space-sm">
                  <input type="checkbox" :checked="!!memberOf(u.id)" class="h-4 w-4 rounded border-outline text-primary-container focus:ring-primary/20" @change="toggleMember(u.id, $event.target.checked)" />
                  <span class="truncate font-body-md text-body-md text-on-surface">{{ u.name }}</span>
                </label>
                <select v-if="memberOf(u.id)" v-model="memberOf(u.id).role_in_team" class="rounded-lg border border-outline-variant bg-white px-space-xs py-1 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none">
                  <option value="member">Member</option>
                  <option value="lead">Lead</option>
                </select>
              </li>
            </ul>
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
