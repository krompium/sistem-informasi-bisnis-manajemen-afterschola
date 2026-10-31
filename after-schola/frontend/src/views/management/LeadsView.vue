<script setup>
// Calon & Leads (permission: manage leads) — daftar calon siswa dari form trial, catatan staf, export Excel (MGT-21, MGT-22).
// Daftar dan export memakai filter yang sama; data dibatasi cabang oleh backend.
import { onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatStamp, unwrap } from '@/lib/format'

const STATUS_LABEL = { leads: 'Belum dijadwalkan', trial_scheduled: 'Trial terjadwal', not_continued: 'Tidak lanjut' }
const STATUS_CLS = {
  leads: 'bg-surface-container text-on-surface-variant',
  trial_scheduled: 'bg-[#DBEAFE] text-[#1E40AF]',
  not_continued: 'bg-[#FEF3C7] text-[#92400E]',
}

const loading = ref(true)
const loadError = ref('')
const leads = ref([])
const meta = ref(null)
const page = ref(1)
const options = reactive({ branches: [], programs: [] })
const filters = reactive({ status: '', branch_id: '', program_id: '' })

const editing = ref(null) // lead yang catatannya sedang diubah
const note = ref('')
const saving = ref(false)
const formError = ref('')

const exporting = ref(false)
const exportError = ref('')

function filterParams() {
  return {
    status: filters.status || undefined,
    branch_id: filters.branch_id || undefined,
    program_id: filters.program_id || undefined,
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/leads', { params: { page: page.value, ...filterParams() } })
    leads.value = unwrap(data)
    meta.value = data.meta ?? null
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat daftar calon.')
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const { data } = await api.get('/trial-slots/options')
    options.branches = data.data.branches
    options.programs = data.data.programs
  } catch (err) {
    // non-fatal: daftar tetap bisa dipakai tanpa dropdown filter
  }
}

function openNote(lead) {
  editing.value = lead
  note.value = lead.note ?? ''
  formError.value = ''
}

async function saveNote() {
  saving.value = true
  formError.value = ''
  try {
    const { data } = await api.put(`/leads/${editing.value.id}`, { note: note.value.trim() || null })
    const updated = data.data ?? data
    const idx = leads.value.findIndex((l) => l.id === updated.id)
    if (idx !== -1) leads.value[idx] = updated
    editing.value = null
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan catatan.')
  } finally {
    saving.value = false
  }
}

async function exportExcel() {
  exporting.value = true
  exportError.value = ''
  try {
    const res = await api.get('/exports/leads', { params: filterParams(), responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const m = /filename="?([^"]+)"?/.exec(res.headers['content-disposition'] || '')
    link.download = m ? m[1] : 'calon-siswa.xlsx'
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    exportError.value = err?.response?.status === 403 ? 'Tidak punya izin export.' : 'Gagal mengunduh berkas.'
  } finally {
    exporting.value = false
  }
}

watch(filters, () => {
  page.value = 1
  load()
})

onMounted(() => {
  loadOptions()
  load()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Trial dan Leads • Calon</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Calon &amp; Leads</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Calon siswa dari form trial, lengkap dengan data orang tua, jadwal trial, dan catatan follow-up.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60 lg:self-center"
        :disabled="exporting"
        @click="exportExcel"
      >
        <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': exporting }">{{ exporting ? 'progress_activity' : 'download' }}</span>
        <span>Export Excel</span>
      </button>
    </div>

    <p v-if="exportError" class="mb-space-sm rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ exportError }}</p>

    <div class="mb-space-md flex flex-wrap items-center gap-space-sm">
      <select v-model="filters.status" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface">
        <option value="">Semua status</option>
        <option v-for="(label, key) in STATUS_LABEL" :key="key" :value="key">{{ label }}</option>
      </select>
      <select v-if="options.branches.length > 1" v-model="filters.branch_id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface">
        <option value="">Semua cabang</option>
        <option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <select v-model="filters.program_id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface">
        <option value="">Semua program</option>
        <option v-for="p in options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-xl bg-surface-container"></div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">
      {{ loadError }}
    </div>

    <div v-else-if="!leads.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">group_add</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada calon siswa</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div v-for="l in leads" :key="l.id" class="flex flex-col gap-space-xs border-b border-surface-container px-space-md py-space-sm last:border-0">
        <div class="flex flex-wrap items-center gap-space-xs">
          <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ l.name }}</span>
          <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm font-semibold uppercase text-on-surface-variant">{{ l.education_level }}</span>
          <span class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold" :class="STATUS_CLS[l.status]">{{ STATUS_LABEL[l.status] ?? l.status }}</span>
          <button class="ml-auto rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Catatan" @click="openNote(l)">
            <span class="material-symbols-outlined text-[18px]">edit_note</span>
          </button>
        </div>
        <span class="font-body-sm text-body-sm text-on-surface-variant">{{ l.origin_school }}</span>
        <span class="font-body-sm text-body-sm text-on-surface-variant">
          Wali: <b class="text-on-surface">{{ l.parent?.name ?? '—' }}</b>
          <template v-if="l.parent?.phone"> • {{ l.parent.phone }}</template>
          • {{ l.parent?.email ?? l.parent_email }}
        </span>
        <span v-if="l.trial_slot" class="font-body-sm text-body-sm text-on-surface-variant">
          Trial: {{ l.trial_slot.program }} • {{ l.trial_slot.branch }} • {{ formatStamp(l.trial_slot.starts_at) }}
        </span>
        <span v-if="l.note" class="font-body-sm text-body-sm italic text-on-surface">“{{ l.note }}”</span>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-space-md flex items-center justify-center gap-space-sm">
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page <= 1" @click="page--; load()">Sebelumnya</button>
      <span class="font-body-sm text-body-sm text-on-surface-variant">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page >= meta.last_page" @click="page++; load()">Berikutnya</button>
    </div>

    <!-- Modal catatan -->
    <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="editing = null">
      <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Catatan: {{ editing.name }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="editing = null">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="p-space-lg" @submit.prevent="saveNote">
          <div v-if="formError" class="mb-space-md flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>
          <textarea
            v-model="note"
            rows="5"
            maxlength="2000"
            placeholder="Catatan follow-up (mis. sudah dihubungi, minat paket, alasan tidak lanjut)"
            class="w-full rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface"
          ></textarea>
          <div class="mt-space-lg flex items-center justify-end gap-space-sm">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="editing = null">Batal</button>
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
