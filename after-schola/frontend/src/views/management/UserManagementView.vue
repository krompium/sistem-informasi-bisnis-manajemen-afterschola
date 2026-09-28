<script setup>
// Manajemen User (Management) — daftar user & bikin akun baru (termasuk Trainer
// dengan kemampuan mengajar: mata pelajaran & level yang dikuasai).
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { initials, unwrap } from '@/lib/format'

const ROLES = [
  { value: 'management', label: 'Management' },
  { value: 'trainer', label: 'Trainer' },
  { value: 'finance', label: 'Finance' },
  { value: 'hr', label: 'HR' },
  { value: 'developer', label: 'Developer' },
]
const LEVELS = [
  { value: 'beginner', label: 'Beginner' },
  { value: 'intermediate', label: 'Intermediate' },
]

const loading = ref(true)
const users = ref([])
const schools = ref([])
const subjectCatalog = ref([])

const showCreate = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'trainer',
  school_ids: [],
  subjects_taught: [],
  levels_taught: [],
})

// ---- Edit User ----
const showEdit = ref(false)
const savingEdit = ref(false)
const editError = ref('')
const editTarget = ref(null)
const editForm = reactive({
  name: '',
  email: '',
  password: '',
  role: 'trainer',
  school_ids: [],
  subjects_taught: [],
  levels_taught: [],
})

const roleLabel = (role) => ROLES.find((r) => r.value === role)?.label || role

async function loadUsers() {
  loading.value = true
  try {
    const { data } = await api.get('/users')
    users.value = unwrap(data)
  } finally {
    loading.value = false
  }
}

async function loadRefData() {
  try {
    const [sc, cat] = await Promise.all([api.get('/schools'), api.get('/classrooms/catalog')])
    schools.value = unwrap(sc.data)
    subjectCatalog.value = cat.data.data ?? cat.data
  } catch (e) {
    // non-fatal, form tetap bisa dipakai tanpa opsi ini
  }
}

function openCreate() {
  Object.assign(form, {
    name: '',
    email: '',
    password: '',
    role: 'trainer',
    school_ids: [],
    subjects_taught: [],
    levels_taught: [],
  })
  formError.value = ''
  showCreate.value = true
}

async function submitCreate() {
  saving.value = true
  formError.value = ''
  try {
    const payload = {
      name: form.name,
      email: form.email,
      password: form.password,
      role: form.role,
    }
    if (form.role === 'trainer') {
      payload.school_ids = form.school_ids
      payload.subjects_taught = form.subjects_taught
      payload.levels_taught = form.levels_taught
    }
    await api.post('/users', payload)
    showCreate.value = false
    await loadUsers()
  } catch (err) {
    const res = err?.response
    formError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : res?.status === 403
          ? 'Anda tidak berwenang menambah user.'
          : 'Gagal menyimpan user.'
  } finally {
    saving.value = false
  }
}

// ---- Edit User ----
function openEdit(user) {
  editTarget.value = user
  Object.assign(editForm, {
    name: user.name,
    email: user.email,
    password: '',
    role: (user.roles ?? [])[0] ?? 'trainer',
    school_ids: (user.assignedSchools ?? user.assigned_schools ?? user.schools ?? []).map((s) => s.id),
    subjects_taught: user.subjects_taught ?? [],
    levels_taught: user.levels_taught ?? [],
  })
  editError.value = ''
  showEdit.value = true
}

async function submitEdit() {
  if (!editTarget.value) return
  savingEdit.value = true
  editError.value = ''
  try {
    const payload = {
      name: editForm.name,
      email: editForm.email,
      role: editForm.role,
    }
    if (editForm.password) {
      payload.password = editForm.password
    }
    if (editForm.role === 'trainer') {
      payload.school_ids = editForm.school_ids
      payload.subjects_taught = editForm.subjects_taught
      payload.levels_taught = editForm.levels_taught
    }
    await api.put(`/users/${editTarget.value.id}`, payload)
    showEdit.value = false
    await loadUsers()
  } catch (err) {
    const res = err?.response
    editError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : 'Gagal menyimpan perubahan.'
  } finally {
    savingEdit.value = false
  }
}

async function toggleActive(user) {
  try {
    await api.put(`/users/${user.id}`, { is_active: !user.is_active })
    await loadUsers()
  } catch (e) {
    window.alert('Gagal mengubah status user.')
  }
}

async function removeUser(user) {
  if (!window.confirm(`Hapus akun "${user.name}"? Tindakan ini tidak bisa dibatalkan.`)) return
  try {
    await api.delete(`/users/${user.id}`)
    await loadUsers()
  } catch (err) {
    window.alert(err?.response?.data?.message || 'Gagal menghapus user.')
  }
}

onMounted(() => {
  loadUsers()
  loadRefData()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Absensi • Pengguna</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          Manajemen User
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Kelola akun Management, Trainer, dan peran lainnya.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openCreate"
      >
        <span class="material-symbols-outlined text-[18px]">person_add</span>
        <span>Tambah User</span>
      </button>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-surface-container"></div>
    </div>

    <div v-else-if="!users.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">group_off</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada user</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div
        v-for="u in users"
        :key="u.id"
        class="flex flex-col gap-space-sm border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center"
      >
        <div class="flex items-center gap-space-sm">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-secondary-container text-[12px] font-bold text-on-secondary-container">{{ initials(u.name) }}</span>
          <div class="flex flex-col">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ u.name }}</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ u.email }}</span>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-1 sm:ml-space-md">
          <span v-for="r in u.roles ?? []" :key="r" class="rounded-full bg-primary-fixed px-2 py-0.5 font-label-sm text-label-sm font-semibold capitalize text-primary">{{ roleLabel(r) }}</span>
          <span v-for="sub in u.subjects_taught ?? []" :key="sub" class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ sub }}</span>
          <span v-for="lvl in u.levels_taught ?? []" :key="lvl" class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm capitalize text-on-surface-variant">{{ lvl }}</span>
        </div>

        <div class="flex items-center gap-space-xs sm:ml-auto">
          <span
            class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
            :class="u.is_active ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-outline'"
          >{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          <button class="rounded-lg p-1.5 text-primary hover:bg-surface-container" :title="u.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleActive(u)">
            <span class="material-symbols-outlined text-[18px]">{{ u.is_active ? 'toggle_on' : 'toggle_off' }}</span>
          </button>
          <button class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah user" @click="openEdit(u)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button class="rounded-lg p-1.5 text-error hover:bg-error-container" title="Hapus" @click="removeUser(u)">
            <span class="material-symbols-outlined text-[18px]">delete</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal tambah user -->
    <div
      v-if="showCreate"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showCreate = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Tambah User</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showCreate = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submitCreate">
          <div class="flex flex-col gap-space-md">
            <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
              <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama</span>
              <input v-model="form.name" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Email</span>
              <input v-model="form.email" type="email" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Password</span>
              <input v-model="form.password" type="password" required minlength="8" placeholder="Minimal 8 karakter" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Role</span>
              <select v-model="form.role" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </label>

            <!-- Khusus Trainer -->
            <template v-if="form.role === 'trainer'">
              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sekolah Binaan <span class="font-normal text-outline">(opsional)</span></span>
                <div class="flex flex-col gap-1 rounded-lg border border-outline-variant bg-white p-space-sm">
                  <label v-for="s in schools" :key="s.id" class="flex items-center gap-space-sm py-1">
                    <input type="checkbox" :value="s.id" v-model="form.school_ids" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
                    <span class="font-body-md text-body-md text-on-surface">{{ s.name }}</span>
                  </label>
                  <p v-if="!schools.length" class="font-body-sm text-body-sm text-outline">Belum ada sekolah.</p>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mata Pelajaran yang Bisa Diajar</span>
                <div class="flex flex-wrap gap-x-space-md gap-y-1 rounded-lg border border-outline-variant bg-white p-space-sm">
                  <label v-for="name in subjectCatalog" :key="name" class="flex items-center gap-space-xs py-1">
                    <input type="checkbox" :value="name" v-model="form.subjects_taught" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
                    <span class="font-body-md text-body-md text-on-surface">{{ name }}</span>
                  </label>
                  <p v-if="!subjectCatalog.length" class="font-body-sm text-body-sm text-outline">Belum ada mata pelajaran di katalog.</p>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Level yang Bisa Diajar</span>
                <div class="inline-flex rounded-xl bg-surface-container-low p-1">
                  <button
                    v-for="lvl in LEVELS"
                    :key="lvl.value"
                    type="button"
                    class="flex flex-1 items-center justify-center rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
                    :class="form.levels_taught.includes(lvl.value) ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'"
                    @click="
                      form.levels_taught.includes(lvl.value)
                        ? (form.levels_taught = form.levels_taught.filter((v) => v !== lvl.value))
                        : form.levels_taught.push(lvl.value)
                    "
                  >
                    {{ lvl.label }}
                  </button>
                </div>
              </div>
            </template>
          </div>

          <div class="mt-space-lg flex items-center justify-end gap-space-sm">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showCreate = false">Batal</button>
            <button type="submit" :disabled="saving" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal edit user -->
    <div
      v-if="showEdit"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showEdit = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Ubah User</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showEdit = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submitEdit">
          <div class="flex flex-col gap-space-md">
            <div v-if="editError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
              <span class="material-symbols-outlined text-[18px]">error</span>{{ editError }}
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama</span>
              <input v-model="editForm.name" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Email</span>
              <input v-model="editForm.email" type="email" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Password Baru <span class="font-normal text-outline">(opsional, kosongkan kalau tidak diganti)</span></span>
              <input v-model="editForm.password" type="password" minlength="8" placeholder="Kosongkan kalau tidak diganti" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Role</span>
              <select v-model="editForm.role" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option v-for="r in ROLES" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </label>

            <!-- Khusus Trainer -->
            <template v-if="editForm.role === 'trainer'">
              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sekolah Binaan <span class="font-normal text-outline">(opsional)</span></span>
                <div class="flex flex-col gap-1 rounded-lg border border-outline-variant bg-white p-space-sm">
                  <label v-for="s in schools" :key="s.id" class="flex items-center gap-space-sm py-1">
                    <input type="checkbox" :value="s.id" v-model="editForm.school_ids" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
                    <span class="font-body-md text-body-md text-on-surface">{{ s.name }}</span>
                  </label>
                  <p v-if="!schools.length" class="font-body-sm text-body-sm text-outline">Belum ada sekolah.</p>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mata Pelajaran yang Bisa Diajar</span>
                <div class="flex flex-wrap gap-x-space-md gap-y-1 rounded-lg border border-outline-variant bg-white p-space-sm">
                  <label v-for="name in subjectCatalog" :key="name" class="flex items-center gap-space-xs py-1">
                    <input type="checkbox" :value="name" v-model="editForm.subjects_taught" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
                    <span class="font-body-md text-body-md text-on-surface">{{ name }}</span>
                  </label>
                  <p v-if="!subjectCatalog.length" class="font-body-sm text-body-sm text-outline">Belum ada mata pelajaran di katalog.</p>
                </div>
              </div>

              <div class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Level yang Bisa Diajar</span>
                <div class="inline-flex rounded-xl bg-surface-container-low p-1">
                  <button
                    v-for="lvl in LEVELS"
                    :key="lvl.value"
                    type="button"
                    class="flex flex-1 items-center justify-center rounded-lg px-space-md py-2 font-label-md text-label-md font-semibold transition-all"
                    :class="editForm.levels_taught.includes(lvl.value) ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant'"
                    @click="
                      editForm.levels_taught.includes(lvl.value)
                        ? (editForm.levels_taught = editForm.levels_taught.filter((v) => v !== lvl.value))
                        : editForm.levels_taught.push(lvl.value)
                    "
                  >
                    {{ lvl.label }}
                  </button>
                </div>
              </div>
            </template>
          </div>

          <div class="mt-space-lg flex items-center justify-end gap-space-sm">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showEdit = false">Batal</button>
            <button type="submit" :disabled="savingEdit" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="savingEdit" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Simpan Perubahan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>