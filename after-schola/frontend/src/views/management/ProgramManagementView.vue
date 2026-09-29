<script setup>
// Manajemen Program & Promosi — CRUD program + jadwal trial per program.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { unwrap } from '@/lib/format'

const TIPE_OPTIONS = [
  { value: 'online', label: 'Online' },
  { value: 'offline', label: 'Offline' },
]
const HARI_OPTIONS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']

const loading = ref(true)
const programs = ref([])

function emptyForm() {
  return {
    nama: '',
    tipe: 'online',
    deskripsi: '',
    biaya: '',
    status_aktif: true,
    jadwal: [], // { hari, jam_mulai, jam_selesai }
  }
}

const showCreate = ref(false)
const saving = ref(false)
const formError = ref('')
const form = reactive(emptyForm())

const showEdit = ref(false)
const savingEdit = ref(false)
const editError = ref('')
const editTarget = ref(null)
const editForm = reactive(emptyForm())

async function loadPrograms() {
  loading.value = true
  try {
    const { data } = await api.get('/programs')
    programs.value = unwrap(data)
  } finally {
    loading.value = false
  }
}

function addJadwalRow(target) {
  target.jadwal.push({ hari: 'senin', jam_mulai: '', jam_selesai: '' })
}
function removeJadwalRow(target, idx) {
  target.jadwal.splice(idx, 1)
}

function openCreate() {
  Object.assign(form, emptyForm())
  formError.value = ''
  showCreate.value = true
}

async function submitCreate() {
  saving.value = true
  formError.value = ''
  try {
    const { data } = await api.post('/programs', {
      nama: form.nama,
      tipe: form.tipe,
      deskripsi: form.deskripsi,
      biaya: form.biaya,
      status_aktif: form.status_aktif,
    })
    const newProgram = data?.data ?? data
    for (const j of form.jadwal) {
      await api.post(`/programs/${newProgram.id}/jadwal-trial`, j)
    }
    showCreate.value = false
    await loadPrograms()
  } catch (err) {
    const res = err?.response
    formError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : res?.status === 403
          ? 'Anda tidak berwenang menambah program.'
          : 'Gagal menyimpan program.'
  } finally {
    saving.value = false
  }
}

function openEdit(program) {
  editTarget.value = program
  Object.assign(editForm, {
    nama: program.nama,
    tipe: program.tipe,
    deskripsi: program.deskripsi ?? '',
    biaya: program.biaya,
    status_aktif: program.status_aktif,
    jadwal: (program.jadwal_trial ?? []).map((j) => ({ ...j })),
  })
  editError.value = ''
  showEdit.value = true
}

async function submitEdit() {
  if (!editTarget.value) return
  savingEdit.value = true
  editError.value = ''
  try {
    await api.put(`/programs/${editTarget.value.id}`, {
      nama: editForm.nama,
      tipe: editForm.tipe,
      deskripsi: editForm.deskripsi,
      biaya: editForm.biaya,
      status_aktif: editForm.status_aktif,
    })
    showEdit.value = false
    await loadPrograms()
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

async function toggleActive(program) {
  try {
    await api.put(`/programs/${program.id}`, { status_aktif: !program.status_aktif })
    await loadPrograms()
  } catch (e) {
    window.alert('Gagal mengubah status program.')
  }
}

async function removeProgram(program) {
  if (!window.confirm(`Hapus program "${program.nama}"? Tindakan ini tidak bisa dibatalkan.`)) return
  try {
    await api.delete(`/programs/${program.id}`)
    await loadPrograms()
  } catch (err) {
    window.alert(err?.response?.data?.message || 'Gagal menghapus program.')
  }
}

function formatBiaya(v) {
  const n = Number(v || 0)
  return n.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 })
}

onMounted(() => {
  loadPrograms()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Program & Promosi</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          Program & Trial
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Kelola daftar program, biaya, dan jadwal trial.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openCreate"
      >
        <span class="material-symbols-outlined text-[18px]">add_circle</span>
        <span>Tambah Program</span>
      </button>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-surface-container"></div>
    </div>

    <div v-else-if="!programs.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">inventory_2</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada program</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div
        v-for="p in programs"
        :key="p.id"
        class="flex flex-col gap-space-sm border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center"
      >
        <div class="flex flex-col">
          <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ p.nama }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ formatBiaya(p.biaya) }} • {{ p.tipe }}</span>
        </div>

        <div class="flex flex-wrap items-center gap-1 sm:ml-space-md">
          <span
            v-for="j in p.jadwal_trial ?? []"
            :key="j.id"
            class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm capitalize text-on-surface-variant"
          >{{ j.hari }} {{ j.jam_mulai }}–{{ j.jam_selesai }}</span>
        </div>

        <div class="flex items-center gap-space-xs sm:ml-auto">
          <span
            class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
            :class="p.status_aktif ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-outline'"
          >{{ p.status_aktif ? 'Aktif' : 'Nonaktif' }}</span>
          <button class="rounded-lg p-1.5 text-primary hover:bg-surface-container" :title="p.status_aktif ? 'Nonaktifkan' : 'Aktifkan'" @click="toggleActive(p)">
            <span class="material-symbols-outlined text-[18px]">{{ p.status_aktif ? 'toggle_on' : 'toggle_off' }}</span>
          </button>
          <button class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah program" @click="openEdit(p)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button class="rounded-lg p-1.5 text-error hover:bg-error-container" title="Hapus" @click="removeProgram(p)">
            <span class="material-symbols-outlined text-[18px]">delete</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modal tambah program -->
    <div
      v-if="showCreate"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showCreate = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Tambah Program</h2>
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
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Program</span>
              <input v-model="form.nama" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tipe</span>
              <select v-model="form.tipe" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option v-for="t in TIPE_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Deskripsi</span>
              <textarea v-model="form.deskripsi" rows="3" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Biaya (Rp)</span>
              <input v-model="form.biaya" type="number" min="0" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex items-center gap-space-xs">
              <input type="checkbox" v-model="form.status_aktif" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Aktifkan program ini</span>
            </label>

            <div class="flex flex-col gap-1">
              <div class="flex items-center justify-between">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Jadwal Trial</span>
                <button type="button" class="font-label-sm text-label-sm font-semibold text-primary" @click="addJadwalRow(form)">+ Tambah slot</button>
              </div>
              <div v-for="(j, idx) in form.jadwal" :key="idx" class="flex items-center gap-space-xs rounded-lg border border-outline-variant bg-white p-space-sm">
                <select v-model="j.hari" class="rounded-lg border border-outline-variant px-2 py-1.5 font-body-sm text-body-sm capitalize">
                  <option v-for="h in HARI_OPTIONS" :key="h" :value="h">{{ h }}</option>
                </select>
                <input v-model="j.jam_mulai" type="time" class="rounded-lg border border-outline-variant px-2 py-1.5 font-body-sm text-body-sm" />
                <span class="text-outline">–</span>
                <input v-model="j.jam_selesai" type="time" class="rounded-lg border border-outline-variant px-2 py-1.5 font-body-sm text-body-sm" />
                <button type="button" class="ml-auto rounded-lg p-1 text-error hover:bg-error-container" @click="removeJadwalRow(form, idx)">
                  <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
              </div>
              <p v-if="!form.jadwal.length" class="font-body-sm text-body-sm text-outline">Belum ada slot jadwal.</p>
            </div>
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

    <!-- Modal edit program -->
    <div
      v-if="showEdit"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showEdit = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Ubah Program</h2>
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
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Program</span>
              <input v-model="editForm.nama" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tipe</span>
              <select v-model="editForm.tipe" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option v-for="t in TIPE_OPTIONS" :key="t.value" :value="t.value">{{ t.label }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Deskripsi</span>
              <textarea v-model="editForm.deskripsi" rows="3" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Biaya (Rp)</span>
              <input v-model="editForm.biaya" type="number" min="0" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>

            <label class="flex items-center gap-space-xs">
              <input type="checkbox" v-model="editForm.status_aktif" class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary/30" />
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Aktifkan program ini</span>
            </label>

            <p class="font-body-sm text-body-sm text-outline">Jadwal trial belum bisa diubah dari form ini — nanti kita tambahkan di langkah berikutnya.</p>
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