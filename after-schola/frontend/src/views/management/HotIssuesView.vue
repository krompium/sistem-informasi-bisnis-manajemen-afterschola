<script setup>
// Kelola Hot Issue (Management) — pengumuman/isu mendesak yang muncul sebagai
// popup setelah login di semua role dashboard. CRUD sederhana: buat, ubah,
// aktif/nonaktifkan, hapus.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { formatStamp, unwrap } from '@/lib/format'

const loading = ref(true)
const issues = ref([])

const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ title: '', message: '', severity: 'info' })

const severityOptions = [
  { value: 'info', label: 'Info' },
  { value: 'warning', label: 'Perlu Perhatian' },
  { value: 'critical', label: 'Mendesak' },
]
const severityCls = {
  info: 'bg-secondary-container text-on-secondary-container',
  warning: 'bg-[#FEF3C7] text-[#92400E]',
  critical: 'bg-error-container text-on-error-container',
}

async function loadIssues() {
  loading.value = true
  try {
    const { data } = await api.get('/hot-issues')
    issues.value = unwrap(data)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingId.value = null
  Object.assign(form, { title: '', message: '', severity: 'info' })
  formError.value = ''
  showForm.value = true
}

function openEdit(issue) {
  editingId.value = issue.id
  Object.assign(form, { title: issue.title, message: issue.message, severity: issue.severity })
  formError.value = ''
  showForm.value = true
}

async function submitForm() {
  saving.value = true
  formError.value = ''
  try {
    if (editingId.value) {
      await api.put(`/hot-issues/${editingId.value}`, { ...form })
    } else {
      await api.post('/hot-issues', { ...form })
    }
    showForm.value = false
    await loadIssues()
  } catch (err) {
    formError.value =
      err?.response?.status === 422
        ? Object.values(err.response.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : 'Gagal menyimpan hot issue.'
  } finally {
    saving.value = false
  }
}

async function toggleActive(issue) {
  await api.put(`/hot-issues/${issue.id}`, { is_active: !issue.is_active })
  await loadIssues()
}

async function removeIssue(issue) {
  if (!confirm(`Hapus hot issue "${issue.title}"?`)) return
  await api.delete(`/hot-issues/${issue.id}`)
  await loadIssues()
}

onMounted(loadIssues)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Fondasi Platform</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Hot Issue</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Pengumuman/isu mendesak yang muncul sebagai popup setelah login, di semua role dashboard.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openCreate"
      >
        <span class="material-symbols-outlined text-[18px]">campaign</span>
        <span>Buat Hot Issue</span>
      </button>
    </div>

    <div v-if="loading" class="flex flex-col gap-space-sm">
      <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!issues.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">campaign</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada hot issue</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Klik "Buat Hot Issue" untuk mengumumkan sesuatu ke semua user.</p>
    </div>

    <div v-else class="flex flex-col gap-space-sm">
      <div
        v-for="issue in issues"
        :key="issue.id"
        class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm"
        :class="{ 'opacity-60': !issue.is_active }"
      >
        <div class="flex flex-wrap items-center justify-between gap-space-sm">
          <div class="flex items-center gap-space-sm">
            <span class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="severityCls[issue.severity]">
              {{ severityOptions.find((s) => s.value === issue.severity)?.label }}
            </span>
            <span
              class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold"
              :class="issue.is_active ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-on-surface-variant'"
            >
              {{ issue.is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
          </div>
          <div class="flex items-center gap-space-2xs">
            <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" title="Ubah" @click="openEdit(issue)">
              <span class="material-symbols-outlined text-[18px]">edit</span>
            </button>
            <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" title="Aktif/nonaktifkan" @click="toggleActive(issue)">
              <span class="material-symbols-outlined text-[18px]">{{ issue.is_active ? 'visibility_off' : 'visibility' }}</span>
            </button>
            <button class="rounded-lg p-2 text-error hover:bg-error-container" title="Hapus" @click="removeIssue(issue)">
              <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>
        </div>
        <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ issue.title }}</h3>
        <p class="whitespace-pre-line font-body-sm text-body-sm text-on-surface-variant">{{ issue.message }}</p>
        <span class="font-label-sm text-label-sm text-outline">
          {{ issue.created_by ? `Oleh ${issue.created_by} • ` : '' }}{{ formatStamp(issue.created_at) }}
        </span>
      </div>
    </div>

    <!-- Modal buat/ubah hot issue -->
    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showForm = false"
    >
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">
            {{ editingId ? 'Ubah Hot Issue' : 'Buat Hot Issue' }}
          </h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md p-space-lg" @submit.prevent="submitForm">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Judul</span>
            <input
              v-model="form.title"
              required
              placeholder="mis. Gangguan jaringan sekolah X hari ini"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Pesan</span>
            <textarea
              v-model="form.message"
              required
              rows="4"
              placeholder="Jelaskan detail isu dan apa yang perlu dilakukan tim"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            ></textarea>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tingkat</span>
            <select
              v-model="form.severity"
              class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
            >
              <option v-for="s in severityOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
          </label>
          <button
            type="submit"
            :disabled="saving"
            class="mt-space-xs inline-flex items-center justify-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
          >
            {{ saving ? 'Menyimpan...' : editingId ? 'Simpan Perubahan' : 'Buat & Aktifkan' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
