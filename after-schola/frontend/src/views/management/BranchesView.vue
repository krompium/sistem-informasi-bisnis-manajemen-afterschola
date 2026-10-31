<script setup>
// Cabang (permission: manage branches) — daftar, tambah (hanya pusat/scope 'all'),
// ubah, dan nonaktifkan. Cabang tidak dihapus: dinonaktifkan lewat is_active.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { unwrap } from '@/lib/format'
import { toast } from '@/lib/feedback'

const auth = useAuthStore()

const loading = ref(true)
const branches = ref([])
const loadError = ref('')

const showForm = ref(false)
const editing = ref(null) // null = tambah baru
const saving = ref(false)
const formError = ref('')
const form = reactive({ name: '', code: '', address: '' })

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/branches')
    branches.value = unwrap(data)
  } catch (err) {
    loadError.value = err?.response?.status === 403 ? 'Anda tidak berwenang melihat cabang.' : 'Gagal memuat cabang.'
  } finally {
    loading.value = false
  }
}

function openForm(branch = null) {
  editing.value = branch
  Object.assign(form, {
    name: branch?.name ?? '',
    code: branch?.code ?? '',
    address: branch?.address ?? '',
  })
  formError.value = ''
  showForm.value = true
}

function errorMessage(err, fallback) {
  const res = err?.response
  if (res?.status === 422) return Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
  if (res?.status === 403) return 'Anda tidak berwenang melakukan ini.'
  if (res?.status === 404) return 'Cabang tidak ditemukan atau di luar cakupan Anda.'
  return fallback
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    const payload = { name: form.name, code: form.code.toUpperCase(), address: form.address || null }
    if (editing.value) {
      await api.put(`/branches/${editing.value.id}`, payload)
    } else {
      await api.post('/branches', payload)
    }
    showForm.value = false
    await load()
  } catch (err) {
    formError.value = errorMessage(err, 'Gagal menyimpan cabang.')
  } finally {
    saving.value = false
  }
}

async function toggleActive(branch) {
  try {
    await api.put(`/branches/${branch.id}`, {
      name: branch.name,
      code: branch.code,
      address: branch.address,
      is_active: !branch.is_active,
    })
    await load()
  } catch (err) {
    toast.error(errorMessage(err, 'Gagal mengubah status cabang.'))
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Fondasi • Cabang</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Cabang</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Lokasi operasional After Schola. Cabang menjadi batas data dan akses.
        </p>
      </div>
      <button
        v-if="auth.hasAllBranches"
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openForm()"
      >
        <span class="material-symbols-outlined text-[18px]">add_business</span>
        <span>Tambah Cabang</span>
      </button>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 3" :key="i" class="h-14 animate-pulse rounded-xl bg-surface-container"></div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">
      {{ loadError }}
    </div>

    <div v-else-if="!branches.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">store</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada cabang</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div
        v-for="b in branches"
        :key="b.id"
        class="flex flex-col gap-space-sm border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center"
      >
        <div class="flex items-center gap-space-sm">
          <span class="flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg bg-primary-fixed px-2 text-[12px] font-bold text-primary">{{ b.code }}</span>
          <div class="flex flex-col">
            <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ b.name }}</span>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ b.address || 'Alamat belum diisi' }}</span>
          </div>
        </div>

        <div class="flex items-center gap-space-xs sm:ml-auto">
          <span
            class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
            :class="b.is_active ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-outline'"
          >{{ b.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          <button class="rounded-lg p-1.5 text-primary hover:bg-surface-container" :title="b.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleActive(b)">
            <span class="material-symbols-outlined text-[18px]">{{ b.is_active ? 'toggle_on' : 'toggle_off' }}</span>
          </button>
          <button class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah cabang" @click="openForm(b)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal tambah / ubah cabang -->
    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showForm = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Cabang' : 'Tambah Cabang' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div class="flex flex-col gap-space-md">
            <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
              <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama</span>
              <input v-model="form.name" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kode <span class="font-normal text-outline">(2-10 huruf kapital/angka, dipakai di nomor invoice)</span></span>
              <input v-model="form.code" required maxlength="10" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md uppercase text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Alamat <span class="font-normal text-outline">(opsional)</span></span>
              <textarea v-model="form.address" rows="2" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
            </label>
          </div>

          <div class="mt-space-lg flex items-center justify-end gap-space-sm">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showForm = false">Batal</button>
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
