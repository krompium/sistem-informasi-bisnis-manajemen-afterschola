<script setup>
// Penugasan Trainer (Management) — tentukan trainer memegang sekolah apa saja.
// Sinkron many-to-many via POST /schools/{id}/trainers.
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { initials, unwrap } from '@/lib/format'

const loading = ref(true)
const schools = ref([])
const trainers = ref([])
const selectedSchoolId = ref(null)
const assigned = ref(new Set()) // trainer ids terpilih untuk sekolah aktif
const loadingDetail = ref(false)
const saving = ref(false)
const toast = ref({ show: false, message: '', ok: true })

const selectedSchool = computed(() => schools.value.find((s) => s.id === selectedSchoolId.value))

function showToast(message, ok = true) {
  toast.value = { show: true, message, ok }
  setTimeout(() => (toast.value.show = false), 3000)
}

async function loadBase() {
  loading.value = true
  try {
    const [sc, us] = await Promise.all([api.get('/schools'), api.get('/users')])
    schools.value = unwrap(sc.data)
    trainers.value = unwrap(us.data).filter((u) => (u.roles ?? []).includes('trainer'))
    if (schools.value.length) await selectSchool(schools.value[0].id)
  } finally {
    loading.value = false
  }
}

async function selectSchool(id) {
  selectedSchoolId.value = id
  loadingDetail.value = true
  try {
    const { data } = await api.get(`/schools/${id}`)
    const school = data.data ?? data
    assigned.value = new Set((school.trainers ?? []).map((t) => t.id))
  } catch (e) {
    assigned.value = new Set()
  } finally {
    loadingDetail.value = false
  }
}

function toggle(id) {
  const set = new Set(assigned.value)
  set.has(id) ? set.delete(id) : set.add(id)
  assigned.value = set
}

function trainerSchoolCount(t) {
  // Jumlah sekolah yang dipegang trainer (dari assigned_schools bila ada).
  return (t.assigned_schools ?? []).length
}

async function save() {
  if (!selectedSchoolId.value) return
  saving.value = true
  try {
    await api.post(`/schools/${selectedSchoolId.value}/trainers`, {
      trainer_ids: [...assigned.value],
    })
    showToast('Penugasan trainer tersimpan.')
  } catch (err) {
    showToast(
      err?.response?.status === 403 ? 'Tidak berwenang mengubah penugasan.' : 'Gagal menyimpan.',
      false,
    )
  } finally {
    saving.value = false
  }
}

onMounted(loadBase)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <!-- Toast -->
    <div
      class="fixed bottom-6 right-6 z-50 flex items-center gap-space-sm rounded-xl px-space-md py-space-sm text-white shadow-xl transition-all duration-300"
      :class="[toast.show ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0', toast.ok ? 'bg-[#0B2A5B]' : 'bg-error']"
    >
      <span class="material-symbols-outlined">{{ toast.ok ? 'check_circle' : 'error' }}</span>
      <span class="font-body-md text-body-md font-medium">{{ toast.message }}</span>
    </div>

    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
        >Modul Absensi • Data Master</span
      >
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
        Penugasan Trainer
      </h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Tentukan trainer memegang sekolah apa saja. Satu trainer bisa banyak sekolah, dan sebaliknya.
      </p>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md lg:grid-cols-3">
      <div class="h-96 animate-pulse rounded-2xl bg-surface-container lg:col-span-1"></div>
      <div class="h-96 animate-pulse rounded-2xl bg-surface-container lg:col-span-2"></div>
    </div>

    <div v-else-if="!schools.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">domain_disabled</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada sekolah</p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md lg:grid-cols-3">
      <!-- Daftar sekolah -->
      <div class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-md shadow-sm lg:col-span-1">
        <span class="mb-space-2xs font-label-sm text-label-sm font-semibold uppercase tracking-wider text-outline">Sekolah</span>
        <button
          v-for="s in schools"
          :key="s.id"
          class="flex items-center justify-between gap-space-sm rounded-xl px-space-sm py-space-sm text-left transition-colors"
          :class="s.id === selectedSchoolId ? 'bg-primary-fixed/50 text-primary' : 'hover:bg-surface-container-low text-on-surface'"
          @click="selectSchool(s.id)"
        >
          <span class="flex items-center gap-space-sm">
            <span class="material-symbols-outlined text-[20px]">school</span>
            <span class="font-label-lg text-label-lg font-semibold">{{ s.name }}</span>
          </span>
          <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm text-on-surface-variant">
            {{ (s.trainers?.length ?? '·') }}
          </span>
        </button>
      </div>

      <!-- Panel penugasan -->
      <div class="rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm lg:col-span-2">
        <div class="mb-space-md flex items-center justify-between">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ selectedSchool?.name }}</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Pilih trainer yang memegang sekolah ini</p>
          </div>
          <span class="rounded-full bg-primary-fixed px-3 py-1 font-label-sm text-label-sm font-semibold text-primary">
            {{ assigned.size }} trainer dipilih
          </span>
        </div>

        <div v-if="loadingDetail" class="space-y-space-sm">
          <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-surface-container"></div>
        </div>

        <div v-else-if="!trainers.length" class="py-space-xl text-center font-body-sm text-body-sm text-on-surface-variant">
          Belum ada user ber-role trainer.
        </div>

        <div v-else class="grid grid-cols-1 gap-space-xs sm:grid-cols-2">
          <button
            v-for="t in trainers"
            :key="t.id"
            class="flex items-center gap-space-sm rounded-xl border-2 p-space-sm text-left transition-all"
            :class="assigned.has(t.id) ? 'border-primary-container bg-primary-fixed/30' : 'border-surface-container hover:border-primary-container/40'"
            @click="toggle(t.id)"
          >
            <span
              class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full font-bold text-[12px]"
              :class="assigned.has(t.id) ? 'bg-primary-container text-on-primary' : 'bg-surface-container-high text-primary'"
            >{{ initials(t.name) }}</span>
            <span class="flex min-w-0 flex-col">
              <span class="truncate font-label-lg text-label-lg font-semibold text-on-surface">{{ t.name }}</span>
              <span class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ t.email }}</span>
            </span>
            <span class="material-symbols-outlined ml-auto text-[22px]" :class="assigned.has(t.id) ? 'text-primary' : 'text-outline-variant'">
              {{ assigned.has(t.id) ? 'check_circle' : 'radio_button_unchecked' }}
            </span>
          </button>
        </div>

        <div class="mt-space-lg flex justify-end border-t border-surface-container pt-space-md">
          <button
            class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            :disabled="saving || loadingDetail"
            @click="save"
          >
            <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
            <span v-else class="material-symbols-outlined text-[18px]">save</span>
            Simpan Penugasan
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
