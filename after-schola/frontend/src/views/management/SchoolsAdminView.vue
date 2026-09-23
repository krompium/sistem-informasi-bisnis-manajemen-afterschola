<script setup>
// Sekolah & Murid (Management) — daftar sekolah beserta level & murid,
// plus tambah sekolah. Agar management tahu ada sekolah apa saja.
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
const form = reactive({ name: '', address: '', pic_name: '', pic_phone: '' })

const selectedSchool = computed(() => schools.value.find((s) => s.id === selectedSchoolId.value))

// Murid dikelompokkan per level (classroom).
const studentsByLevel = computed(() => {
  const groups = {}
  for (const st of students.value) {
    const key = st.classroom?.name || 'Tanpa Level'
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
  Object.assign(form, { name: '', address: '', pic_name: '', pic_phone: '' })
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
          Daftar sekolah mitra beserta level dan murid yang terdaftar.
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
            <span v-for="c in s.classrooms" :key="c.id" class="rounded-md bg-surface-container-high px-2 py-0.5 font-label-sm text-label-sm font-semibold text-primary">{{ c.name }}</span>
          </div>
        </button>
      </div>

      <!-- Detail sekolah + murid -->
      <div class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm lg:col-span-2">
        <template v-if="selectedSchool">
          <div class="mb-space-md flex flex-col gap-space-2xs border-b border-surface-container pb-space-md">
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ selectedSchool.name }}</h2>
            <div class="flex flex-wrap items-center gap-x-space-md gap-y-1 font-body-sm text-body-sm text-on-surface-variant">
              <span v-if="selectedSchool.address" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">location_on</span>{{ selectedSchool.address }}</span>
              <span v-if="selectedSchool.pic_name" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">person</span>PIC: {{ selectedSchool.pic_name }}</span>
              <span v-if="selectedSchool.pic_phone" class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px] text-primary">call</span>{{ selectedSchool.pic_phone }}</span>
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
  </div>
</template>
