<script setup>
// Sekolah & Murid (Management) — daftar sekolah beserta mata pelajaran & murid,
// plus tambah sekolah, kelola mata pelajaran (katalog bersama antar sekolah), dan tambah murid.
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { initials, unwrap } from '@/lib/format'

const loading = ref(true)
const schools = ref([])
const selectedSchoolId = ref(null)
const students = ref([])
const loadingStudents = ref(false)

const showCreate = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive({ name: '', address: '', pic_name: '', pic_phone: '', pic_email: '', pic_password: '' })

// ---- Kelola Mata Pelajaran ----
const showManageSubjects = ref(false)
const subjectCatalog = ref([]) // semua nama unik yang pernah dipakai di sekolah manapun
const loadingCatalog = ref(false)
const manageError = ref('')
const newSubjectName = ref('')
const checkedCatalog = ref([]) // nama-nama dari katalog yang dicentang buat ditambah
const savingSubjects = ref(false)
const editingId = ref(null) // classroom.id yang lagi diedit namanya
const editingName = ref('')
const rowBusyId = ref(null) // classroom.id yang lagi diproses (toggle/rename)

// ---- Tambah Murid ----
const showAddStudent = ref(false)
const savingStudent = ref(false)
const studentFormError = ref('')
const studentForm = reactive({ classroom_id: '', name: '', origin_class: '' })

const selectedSchool = computed(() => schools.value.find((s) => s.id === selectedSchoolId.value))

// Mata pelajaran katalog yang BELUM ada di sekolah yang lagi dipilih.
const availableCatalog = computed(() => {
  const existing = new Set((selectedSchool.value?.classrooms ?? []).map((c) => c.name.toLowerCase()))
  return subjectCatalog.value.filter((name) => !existing.has(name.toLowerCase()))
})

// Mata pelajaran aktif saja yang boleh dipilih saat menambah murid.
const activeClassrooms = computed(() =>
  (selectedSchool.value?.classrooms ?? []).filter((c) => c.is_active !== false),
)

// Murid dikelompokkan per mata pelajaran (classroom).
const studentsByLevel = computed(() => {
  const groups = {}
  for (const st of students.value) {
    const key = st.classroom?.name || 'Tanpa Mata Pelajaran'
    ;(groups[key] ||= []).push(st)
  }
  return Object.entries(groups).map(([level, list]) => ({ level, list }))
})

async function loadSchools() {
  loading.value = true
  try {
    const { data } = await api.get('/schools')
    schools.value = unwrap(data)
    if (schools.value.length && !selectedSchoolId.value) {
      await selectSchool(schools.value[0].id)
    }
  } finally {
    loading.value = false
  }
}

async function selectSchool(id) {
  selectedSchoolId.value = id
  loadingStudents.value = true
  try {
    const { data } = await api.get('/students', { params: { school_id: id } })
    students.value = unwrap(data)
  } catch (e) {
    students.value = []
  } finally {
    loadingStudents.value = false
  }
}

function openCreate() {
  Object.assign(form, { name: '', address: '', pic_name: '', pic_phone: '', pic_email: '', pic_password: '' })
  formError.value = ''
  showCreate.value = true
}

async function submitCreate() {
  saving.value = true
  formError.value = ''
  try {
  const { data } = await api.post('/schools', {
  name: form.name,
  address: form.address || null,
  pic_name: form.pic_name || null,
  pic_phone: form.pic_phone || null,
  pic_email: form.pic_email || null,
  pic_password: form.pic_password || null,
})
    showCreate.value = false
    await loadSchools()
    const created = data.data ?? data
    if (created?.id) selectSchool(created.id)
  } catch (err) {
    formError.value =
      err?.response?.status === 422
        ? Object.values(err.response.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : err?.response?.status === 403
          ? 'Anda tidak berwenang menambah sekolah.'
          : 'Gagal menyimpan sekolah.'
  } finally {
    saving.value = false
  }
}

// ---- Kelola Mata Pelajaran ----
async function openManageSubjects() {
  manageError.value = ''
  newSubjectName.value = ''
  checkedCatalog.value = []
  editingId.value = null
  showManageSubjects.value = true
  loadingCatalog.value = true
  try {
    const { data } = await api.get('/classrooms/catalog')
    subjectCatalog.value = data.data ?? data
  } catch (e) {
    subjectCatalog.value = []
  } finally {
    loadingCatalog.value = false
  }
}

async function submitAddSubjects() {
  const namesToAdd = [...checkedCatalog.value]
  if (newSubjectName.value.trim()) namesToAdd.push(newSubjectName.value.trim())
  if (!namesToAdd.length) {
    manageError.value = 'Pilih minimal satu mata pelajaran atau ketik nama baru.'
    return
  }

  savingSubjects.value = true
  manageError.value = ''
  try {
    for (const name of namesToAdd) {
      await api.post('/classrooms', { school_id: selectedSchoolId.value, name })
    }
    checkedCatalog.value = []
    newSubjectName.value = ''
    await loadSchools()
    // muat ulang katalog juga supaya nama baru langsung kelihatan sebagai opsi di sekolah lain
    const { data } = await api.get('/classrooms/catalog')
    subjectCatalog.value = data.data ?? data
  } catch (err) {
    const res = err?.response
    manageError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : res?.status === 403
          ? 'Anda tidak berwenang menambah mata pelajaran.'
          : 'Gagal menyimpan mata pelajaran.'
  } finally {
    savingSubjects.value = false
  }
}

function startEdit(classroom) {
  editingId.value = classroom.id
  editingName.value = classroom.name
}

function cancelEdit() {
  editingId.value = null
  editingName.value = ''
}

async function saveEdit(classroom) {
  if (!editingName.value.trim()) return
  rowBusyId.value = classroom.id
  manageError.value = ''
  try {
    await api.put(`/classrooms/${classroom.id}`, { name: editingName.value.trim() })
    editingId.value = null
    await loadSchools()
  } catch (err) {
    const res = err?.response
    manageError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Nama tidak valid.'
        : 'Gagal mengubah nama.'
  } finally {
    rowBusyId.value = null
  }
}

async function toggleActive(classroom) {
  rowBusyId.value = classroom.id
  manageError.value = ''
  try {
    await api.put(`/classrooms/${classroom.id}`, { is_active: !(classroom.is_active !== false) })
    await loadSchools()
  } catch (err) {
    manageError.value = 'Gagal mengubah status.'
  } finally {
    rowBusyId.value = null
  }
}

async function deleteSubject(classroom) {
  if (!window.confirm(`Hapus permanen "${classroom.name}"? Cuma bisa kalau belum ada murid/pertemuan yang pakai.`)) return
  rowBusyId.value = classroom.id
  manageError.value = ''
  try {
    await api.delete(`/classrooms/${classroom.id}`)
    await loadSchools()
    const { data } = await api.get('/classrooms/catalog')
    subjectCatalog.value = data.data ?? data
  } catch (err) {
    const res = err?.response
    manageError.value =
      res?.status === 422
        ? res.data?.message || 'Tidak bisa dihapus karena sudah dipakai.'
        : 'Gagal menghapus mata pelajaran.'
  } finally {
    rowBusyId.value = null
  }
}

// ---- Tambah Murid ----
function openAddStudent() {
  const firstClassroom = activeClassrooms.value[0]
  Object.assign(studentForm, {
    classroom_id: firstClassroom?.id ?? '',
    name: '',
    origin_class: '',
  })
  studentFormError.value = ''
  showAddStudent.value = true
}

async function submitAddStudent() {
  savingStudent.value = true
  studentFormError.value = ''
  try {
    await api.post('/students', {
      school_id: selectedSchoolId.value,
      classroom_id: studentForm.classroom_id,
      name: studentForm.name,
      origin_class: studentForm.origin_class || null,
    })
    showAddStudent.value = false
    await selectSchool(selectedSchoolId.value) // refresh daftar murid
  } catch (err) {
    const res = err?.response
    studentFormError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : res?.status === 403
          ? 'Anda tidak berwenang menambah murid di sekolah ini.'
          : 'Gagal menyimpan murid.'
  } finally {
    savingStudent.value = false
  }
}

onMounted(loadSchools)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Absensi • Data Master</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          Sekolah &amp; Murid
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Daftar sekolah mitra beserta mata pelajaran dan murid yang terdaftar.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openCreate"
      >
        <span class="material-symbols-outlined text-[18px]">add_business</span>
        <span>Tambah Sekolah</span>
      </button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md lg:grid-cols-3">
      <div class="h-96 animate-pulse rounded-2xl bg-surface-container"></div>
      <div class="h-96 animate-pulse rounded-2xl bg-surface-container lg:col-span-2"></div>
    </div>

    <div v-else-if="!schools.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">domain_disabled</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada sekolah</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Klik "Tambah Sekolah" untuk mulai.</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md lg:grid-cols-3">
      <!-- Daftar sekolah -->
      <div class="flex flex-col gap-space-xs lg:col-span-1">
        <button
          v-for="s in schools"
          :key="s.id"
          class="flex flex-col gap-space-2xs rounded-2xl border-2 p-space-md text-left shadow-sm transition-all"
          :class="s.id === selectedSchoolId ? 'border-primary-container bg-primary-fixed/30' : 'border-transparent bg-surface-container-lowest hover:bg-surface-container-low'"
          @click="selectSchool(s.id)"
        >
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-space-sm">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-fixed text-primary">
                <span class="material-symbols-outlined text-[20px]">school</span>
              </span>
              <span class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ s.name }}</span>
            </span>
            <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">
              {{ s.students_count ?? 0 }} murid
            </span>
          </div>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ s.address || 'Alamat belum diisi' }}</span>
          <div v-if="s.classrooms?.length" class="mt-space-2xs flex flex-wrap gap-1">
            <span
              v-for="c in s.classrooms"
              :key="c.id"
              class="rounded-md px-2 py-0.5 font-label-sm text-label-sm font-semibold"
              :class="c.is_active === false ? 'bg-surface-container text-outline line-through' : 'bg-surface-container-high text-primary'"
            >{{ c.name }}</span>
          </div>
        </button>
      </div>

      <!-- Detail sekolah + murid -->
      <div class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm lg:col-span-2">
        <template v-if="selectedSchool">
          <div class="mb-space-md flex flex-col gap-space-sm border-b border-surface-container pb-space-md sm:flex-row sm:items-start sm:justify-between">
            <div class="flex flex-col gap-space-2xs">
              <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ selectedSchool.name }}</h2>
              <div class="flex flex-wrap items-center gap-x-space-md gap-y-1 font-body-sm text-body-sm text-on-surface-variant">
                <span v-if="selectedSchool.address" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span>{{ selectedSchool.address }}</span>
                <span v-if="selectedSchool.pic_name" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">person</span>PIC: {{ selectedSchool.pic_name }}</span>
                <span v-if="selectedSchool.pic_phone" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">call</span>{{ selectedSchool.pic_phone }}</span>
              </div>
              <div v-if="selectedSchool.classrooms?.length" class="mt-space-2xs flex flex-wrap gap-1">
                <span
                  v-for="c in selectedSchool.classrooms"
                  :key="c.id"
                  class="rounded-md px-2 py-0.5 font-label-sm text-label-sm font-semibold"
                  :class="c.is_active === false ? 'bg-surface-container text-outline line-through' : 'bg-surface-container-high text-primary'"
                >{{ c.name }}</span>
              </div>
            </div>
            <div class="flex shrink-0 items-center gap-space-xs self-start">
              <button
                class="inline-flex items-center gap-space-xs rounded-xl bg-surface-container px-space-md py-2 font-label-md text-label-md text-primary shadow-sm transition-all hover:bg-surface-container-high"
                @click="openManageSubjects"
              >
                <span class="material-symbols-outlined text-[18px]">tune</span>
                <span>Kelola Mata Pelajaran</span>
              </button>
              <button
                class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-md py-2 font-label-md text-label-md text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-50"
                :disabled="!activeClassrooms.length"
                :title="!activeClassrooms.length ? 'Tambahkan/aktifkan mata pelajaran dulu' : ''"
                @click="openAddStudent"
              >
                <span class="material-symbols-outlined text-[18px]">person_add</span>
                <span>Tambah Murid</span>
              </button>
            </div>
          </div>

          <div v-if="loadingStudents" class="space-y-space-sm">
            <div v-for="i in 4" :key="i" class="h-10 animate-pulse rounded-lg bg-surface-container"></div>
          </div>
          <div v-else-if="!students.length" class="py-space-xl text-center font-body-sm text-body-sm text-on-surface-variant">
            Belum ada murid terdaftar di sekolah ini.
          </div>
          <div v-else class="flex flex-col gap-space-lg">
            <div v-for="g in studentsByLevel" :key="g.level">
              <div class="mb-space-xs flex items-center gap-space-xs">
                <span class="rounded-md bg-primary-fixed px-2 py-0.5 font-label-sm text-label-sm font-semibold text-primary">{{ g.level }}</span>
                <span class="font-label-sm text-label-sm text-on-surface-variant">{{ g.list.length }} murid</span>
              </div>
              <div class="overflow-hidden rounded-xl border border-surface-container">
                <div
                  v-for="(st, i) in g.list"
                  :key="st.id"
                  class="flex items-center gap-space-sm border-b border-surface-container px-space-sm py-space-xs last:border-0 hover:bg-surface-container-low"
                >
                  <span class="w-6 text-center font-label-sm text-label-sm text-outline">{{ i + 1 }}</span>
                  <span class="flex h-7 w-7 items-center justify-center rounded-full bg-surface-container-high text-[10px] font-bold text-primary">{{ initials(st.name) }}</span>
                  <span class="font-body-md text-body-md text-on-surface">{{ st.name }}</span>
                  <span v-if="st.origin_class" class="ml-auto rounded bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">{{ st.origin_class }}</span>
                </div>
              </div>
            </div>
          </div>
        </template>
        <div v-else class="py-space-2xl text-center font-body-sm text-body-sm text-on-surface-variant">
          Pilih sekolah untuk melihat detail & murid.
        </div>
      </div>
    </div>

    <!-- Modal tambah sekolah -->
    <div
      v-if="showCreate"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showCreate = false"
    >
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Tambah Sekolah</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showCreate = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md p-space-lg" @submit.prevent="submitCreate">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Sekolah</span>
            <input v-model="form.name" required placeholder="mis. SMA Negeri 1 Jakarta" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Alamat <span class="font-normal text-outline">(opsional)</span></span>
            <input v-model="form.address" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <div class="grid grid-cols-2 gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama PIC</span>
              <input v-model="form.pic_name" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">No. HP PIC</span>
              <input v-model="form.pic_phone" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>
          </div>
          <div class="grid grid-cols-2 gap-space-md">
  <label class="flex flex-col gap-1">
    <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Email Akun PIC <span class="font-normal text-outline">(opsional)</span></span>
    <input v-model="form.pic_email" type="email" placeholder="pic@sekolah.sch.id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
  </label>
  <label class="flex flex-col gap-1">
    <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Password Akun <span class="font-normal text-outline">(opsional)</span></span>
    <input v-model="form.pic_password" type="password" placeholder="Min. 8 karakter" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
  </label>
</div>
<p class="-mt-space-xs font-body-sm text-body-sm text-outline">Isi kalau ingin PIC sekolah bisa login melihat dashboard. Bisa ditambahkan nanti lewat Manajemen User.</p>
          <div class="flex items-center justify-end gap-space-sm pt-space-xs">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showCreate = false">Batal</button>
            <button type="submit" :disabled="saving" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal kelola mata pelajaran -->
    <div
      v-if="showManageSubjects"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showManageSubjects = false"
    >
      <div class="flex max-h-[88vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Kelola Mata Pelajaran</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ selectedSchool?.name }}</p>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showManageSubjects = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto p-space-lg">
          <div v-if="manageError" class="mb-space-md flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ manageError }}
          </div>

          <!-- Mata pelajaran yang sudah ada di sekolah ini -->
          <div class="mb-space-lg">
            <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">Sudah aktif di sekolah ini</h3>
            <div v-if="!selectedSchool?.classrooms?.length" class="rounded-xl bg-surface-container px-space-sm py-space-sm text-center font-body-sm text-body-sm text-on-surface-variant">
              Belum ada mata pelajaran ditambahkan.
            </div>
            <div v-else class="flex flex-col gap-space-2xs">
              <div
                v-for="c in selectedSchool.classrooms"
                :key="c.id"
                class="flex items-center gap-space-sm rounded-xl border border-surface-container px-space-sm py-space-xs"
              >
                <template v-if="editingId === c.id">
                  <input
                    v-model="editingName"
                    class="flex-1 rounded-lg border border-outline-variant bg-white px-space-sm py-1.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
                    @keyup.enter="saveEdit(c)"
                  />
                  <button class="rounded-lg p-1.5 text-primary hover:bg-surface-container" :disabled="rowBusyId === c.id" @click="saveEdit(c)">
                    <span class="material-symbols-outlined text-[18px]">check</span>
                  </button>
                  <button class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container" @click="cancelEdit">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                  </button>
                </template>
                <template v-else>
                  <span class="flex-1 font-body-md text-body-md" :class="c.is_active === false ? 'text-outline line-through' : 'text-on-surface'">{{ c.name }}</span>
                  <span v-if="c.is_active === false" class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-outline">Nonaktif</span>
                  <button class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah nama" @click="startEdit(c)">
                    <span class="material-symbols-outlined text-[18px]">edit</span>
                  </button>
                  <button
                    class="rounded-lg p-1.5 hover:bg-surface-container"
                    :class="c.is_active === false ? 'text-primary' : 'text-error'"
                    :title="c.is_active === false ? 'Aktifkan' : 'Nonaktifkan'"
                    :disabled="rowBusyId === c.id"
                    @click="toggleActive(c)"
                  >
                    <span class="material-symbols-outlined text-[18px]">{{ c.is_active === false ? 'toggle_off' : 'toggle_on' }}</span>
                  </button>
                  <button
                    class="rounded-lg p-1.5 text-error hover:bg-error-container"
                    title="Hapus permanen"
                    :disabled="rowBusyId === c.id"
                    @click="deleteSubject(c)"
                  >
                    <span class="material-symbols-outlined text-[18px]">delete</span>
                  </button>
                </template>
              </div>
            </div>
          </div>

          <!-- Tambah dari katalog / baru -->
          <div>
            <h3 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">Tambahkan ke sekolah ini</h3>
            <div v-if="loadingCatalog" class="space-y-space-2xs">
              <div v-for="i in 3" :key="i" class="h-8 animate-pulse rounded-lg bg-surface-container"></div>
            </div>
            <div v-else-if="!availableCatalog.length" class="mb-space-sm font-body-sm text-body-sm text-on-surface-variant">
              Semua mata pelajaran yang tersedia di katalog sudah ditambahkan di sekolah ini.
            </div>
            <div v-else class="mb-space-sm flex flex-col gap-space-2xs">
              <label
                v-for="name in availableCatalog"
                :key="name"
                class="flex items-center gap-space-sm rounded-lg px-space-sm py-1.5 hover:bg-surface-container-low"
              >
                <input type="checkbox" :value="name" v-model="checkedCatalog" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
                <span class="font-body-md text-body-md text-on-surface">{{ name }}</span>
              </label>
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Atau mata pelajaran baru <span class="font-normal text-outline">(opsional)</span></span>
              <input
                v-model="newSubjectName"
                placeholder="mis. Web Development"
                class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
              />
              <span class="font-body-sm text-body-sm text-outline">Nama baru otomatis jadi pilihan katalog buat sekolah lain juga.</span>
            </label>
          </div>
        </div>

        <div class="flex items-center justify-end gap-space-sm border-t border-surface-container p-space-lg">
          <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showManageSubjects = false">Tutup</button>
          <button
            type="button"
            :disabled="savingSubjects"
            class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            @click="submitAddSubjects"
          >
            <span v-if="savingSubjects" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
            Tambahkan
          </button>
        </div>
      </div>
    </div>

    <!-- Modal tambah murid -->
    <div
      v-if="showAddStudent"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showAddStudent = false"
    >
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Tambah Murid</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ selectedSchool?.name }}</p>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showAddStudent = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md p-space-lg" @submit.prevent="submitAddStudent">
          <div v-if="studentFormError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ studentFormError }}
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Murid</span>
            <input
              v-model="studentForm.name"
              required
              placeholder="mis. Andi Pratama"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mata Pelajaran</span>
            <select
              v-model="studentForm.classroom_id"
              required
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
              <option value="" disabled>Pilih mata pelajaran…</option>
              <option v-for="c in activeClassrooms" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant"
              >Kelas <span class="font-normal text-outline">(opsional, mis. "8A")</span></span
            >
            <input
              v-model="studentForm.origin_class"
              placeholder="mis. 8A"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            />
          </label>
          <div class="flex items-center justify-end gap-space-sm pt-space-xs">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showAddStudent = false">Batal</button>
            <button type="submit" :disabled="savingStudent" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="savingStudent" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>