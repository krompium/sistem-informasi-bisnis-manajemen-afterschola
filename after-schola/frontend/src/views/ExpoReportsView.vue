<script setup>
// Laporan Ekspo / Free-Trial (FR-7). Management membuat & merekap semua;
// trainer membuat untuk sekolah yang dipegang & melihat miliknya.
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatDate, unwrap } from '@/lib/format'

const auth = useAuthStore()

const loading = ref(true)
const errorMessage = ref('')
const reports = ref([])
const schools = ref([])

const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const photos = ref([])
const photoPreviews = ref([])
const exporting = ref('')
const exportError = ref('')
const form = reactive({
  school_id: '',
  date: '',
  team_name: '',
  rating: 5,
  on_schedule: 'ya',
  enthusiasm: '',
  has_issue: false,
  issue_note: '',
  doc_url: '',
})

const scheduleMeta = {
  ya: { label: 'Sesuai Jadwal', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  sebagian: { label: 'Sebagian', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  tidak: { label: 'Tidak Sesuai', cls: 'bg-error-container text-error' },
}

// Management hanya melihat & merekap (tak membuat). Trainer yang membuat.
const canCreate = computed(() => auth.isTrainer && !auth.isManagement)

// Rekap ringkas untuk Management.
const summary = computed(() => {
  const list = reports.value
  const rated = list.filter((r) => r.rating)
  const avg = rated.length
    ? (rated.reduce((s, r) => s + r.rating, 0) / rated.length).toFixed(1)
    : '—'
  return {
    total: list.length,
    schools: new Set(list.map((r) => r.school_id)).size,
    avgRating: avg,
    issues: list.filter((r) => r.has_issue).length,
  }
})

const schoolName = (id) => schools.value.find((s) => s.id === id)?.name || '—'

async function loadReports() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get('/expo-reports')
    reports.value = unwrap(data)
  } catch (err) {
    errorMessage.value = 'Gagal memuat laporan ekspo.'
  } finally {
    loading.value = false
  }
}

async function loadSchools() {
  try {
    const { data } = await api.get('/schools')
    schools.value = unwrap(data)
  } catch (e) {
    /* diamkan */
  }
}

function onPhotoChange(e) {
  photos.value = [...(e.target.files || [])]
  photoPreviews.value = photos.value.map((f) => URL.createObjectURL(f))
}

async function downloadExport(type) {
  exporting.value = type
  exportError.value = ''
  try {
    const url = type === 'excel' ? '/exports/expo/excel' : '/exports/expo/pdf'
    const res = await api.get(url, { responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const cd = res.headers['content-disposition'] || ''
    const m = /filename="?([^"]+)"?/.exec(cd)
    link.download = m ? m[1] : `laporan-ekspo.${type === 'excel' ? 'xlsx' : 'pdf'}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    exportError.value = 'Gagal mengunduh berkas.'
  } finally {
    exporting.value = ''
  }
}

function openCreate() {
  photos.value = []
  photoPreviews.value = []
  Object.assign(form, {
    school_id: '',
    date: new Date().toISOString().slice(0, 10),
    team_name: '',
    rating: 5,
    on_schedule: 'ya',
    enthusiasm: '',
    has_issue: false,
    issue_note: '',
    doc_url: '',
  })
  formError.value = ''
  showModal.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    const fd = new FormData()
    fd.append('school_id', form.school_id)
    fd.append('date', form.date)
    if (form.team_name) fd.append('team_name', form.team_name)
    if (form.rating) fd.append('rating', form.rating)
    fd.append('on_schedule', form.on_schedule)
    if (form.enthusiasm) fd.append('enthusiasm', form.enthusiasm)
    fd.append('has_issue', form.has_issue ? '1' : '0')
    if (form.has_issue && form.issue_note) fd.append('issue_note', form.issue_note)
    if (form.doc_url) fd.append('doc_url', form.doc_url)
    photos.value.forEach((f) => fd.append('photos[]', f))

    await api.post('/expo-reports', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    showModal.value = false
    await loadReports()
  } catch (err) {
    const res = err?.response
    formError.value =
      res?.status === 422
        ? Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : res?.status === 403
          ? 'Anda tidak berwenang membuat laporan.'
          : 'Gagal menyimpan laporan.'
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadReports()
  loadSchools()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Modul Absensi • Ekspo</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
          Laporan Ekspo &amp; Free-Trial
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          {{ auth.isManagement ? 'Rekap semua laporan ekspo dari trainer di seluruh sekolah.' : 'Buat & lihat laporan ekspo untuk sekolah yang kamu pegang.' }}
        </p>
      </div>
      <div class="flex items-center gap-space-sm self-start lg:self-center">
        <button
          class="inline-flex items-center gap-space-xs rounded-xl bg-[#065F46] px-space-md py-2.5 font-label-lg text-label-lg text-white shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
          :disabled="exporting === 'excel' || !reports.length"
          @click="downloadExport('excel')"
        >
          <span class="material-symbols-outlined text-[18px]" :class="exporting === 'excel' ? 'animate-spin' : ''">{{ exporting === 'excel' ? 'progress_activity' : 'table_view' }}</span>
          Excel
        </button>
        <button
          class="inline-flex items-center gap-space-xs rounded-xl bg-error px-space-md py-2.5 font-label-lg text-label-lg text-on-error shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
          :disabled="exporting === 'pdf' || !reports.length"
          @click="downloadExport('pdf')"
        >
          <span class="material-symbols-outlined text-[18px]" :class="exporting === 'pdf' ? 'animate-spin' : ''">{{ exporting === 'pdf' ? 'progress_activity' : 'picture_as_pdf' }}</span>
          PDF
        </button>
        <button
          v-if="canCreate"
          class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
          @click="openCreate"
        >
          <span class="material-symbols-outlined text-[18px]">post_add</span>
          <span>Buat Laporan</span>
        </button>
      </div>
    </div>
    <p v-if="exportError" class="mb-space-sm font-body-sm text-body-sm text-error">{{ exportError }}</p>

    <!-- Rekap ringkas (Management) -->
    <div v-if="auth.isManagement && reports.length" class="mb-space-lg grid grid-cols-2 gap-space-md lg:grid-cols-4">
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Total Laporan</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-on-surface">{{ summary.total }}</p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Sekolah</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold text-primary">{{ summary.schools }}</p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Rata-rata Rating</span>
        <p class="mt-1 flex items-center gap-1 font-headline-lg text-headline-lg font-extrabold text-on-surface">
          {{ summary.avgRating }}<span class="material-symbols-outlined fill text-[20px] text-amber-500">star</span>
        </p>
      </div>
      <div class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm">
        <span class="font-label-sm text-label-sm uppercase tracking-wider text-outline">Ada Kendala</span>
        <p class="mt-1 font-headline-lg text-headline-lg font-extrabold" :class="summary.issues ? 'text-error' : 'text-[#065F46]'">{{ summary.issues }}</p>
      </div>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="loadReports">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-44 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!reports.length" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">summarize</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada laporan ekspo</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
        {{ canCreate ? 'Klik "Buat Laporan" untuk menambah.' : 'Laporan dari trainer akan tampil di sini.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md md:grid-cols-2 xl:grid-cols-3">
      <div v-for="r in reports" :key="r.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ r.school?.name || schoolName(r.school_id) }}</h3>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ formatDate(r.date) }}</span>
          </div>
          <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold" :class="scheduleMeta[r.on_schedule]?.cls || 'bg-surface-container text-on-surface-variant'">
            {{ scheduleMeta[r.on_schedule]?.label || '—' }}
          </span>
        </div>

        <div class="flex items-center gap-space-md">
          <div class="flex items-center gap-0.5">
            <span v-for="n in 5" :key="n" class="material-symbols-outlined text-[18px]" :class="n <= (r.rating || 0) ? 'fill text-amber-500' : 'text-outline-variant'">star</span>
          </div>
          <span v-if="r.team_name" class="font-body-sm text-body-sm text-on-surface-variant">Tim: {{ r.team_name }}</span>
        </div>

        <p v-if="r.enthusiasm" class="font-body-sm text-body-sm text-on-surface-variant">
          <span class="font-semibold text-on-surface">Antusiasme:</span> {{ r.enthusiasm }}
        </p>

        <div v-if="r.has_issue" class="flex items-start gap-space-xs rounded-lg bg-error-container/60 px-space-sm py-space-xs">
          <span class="material-symbols-outlined text-[18px] text-error">report</span>
          <span class="font-body-sm text-body-sm text-on-error-container">{{ r.issue_note || 'Ada kendala dilaporkan.' }}</span>
        </div>

        <div v-if="r.photo_urls?.length" class="flex gap-space-xs overflow-x-auto pb-1">
          <a v-for="(u, i) in r.photo_urls" :key="i" :href="u" target="_blank" rel="noopener noreferrer" class="shrink-0">
            <img :src="u" alt="dokumentasi" class="h-16 w-16 rounded-lg object-cover ring-1 ring-surface-container" />
          </a>
        </div>

        <div class="mt-auto flex items-center justify-between border-t border-surface-container pt-space-sm">
          <span class="font-label-sm text-label-sm text-outline">{{ r.trainer?.name ? 'Oleh ' + r.trainer.name : '' }}</span>
          <a v-if="r.doc_url" :href="r.doc_url" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline">
            <span class="material-symbols-outlined text-[16px]">open_in_new</span> Dokumentasi
          </a>
        </div>
      </div>
    </div>

    <!-- Modal buat laporan -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showModal = false">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Buat Laporan Ekspo</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <div class="grid grid-cols-2 gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sekolah</span>
              <select v-model="form.school_id" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option value="" disabled>Pilih sekolah…</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tanggal</span>
              <input v-model="form.date" type="date" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
            </label>
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama Tim / Pengisi</span>
            <input v-model="form.team_name" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>

          <div class="grid grid-cols-2 gap-space-md">
            <div class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Rating Pelaksanaan</span>
              <div class="flex items-center gap-1">
                <button v-for="n in 5" :key="n" type="button" @click="form.rating = n">
                  <span class="material-symbols-outlined text-[26px]" :class="n <= form.rating ? 'fill text-amber-500' : 'text-outline-variant'">star</span>
                </button>
              </div>
            </div>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Sesuai Jadwal?</span>
              <select v-model="form.on_schedule" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option value="ya">Ya</option>
                <option value="sebagian">Sebagian</option>
                <option value="tidak">Tidak</option>
              </select>
            </label>
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Antusiasme Siswa</span>
            <textarea v-model="form.enthusiasm" rows="2" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
          </label>

          <label class="flex items-center gap-space-sm">
            <input v-model="form.has_issue" type="checkbox" class="h-4 w-4 rounded border-outline text-primary-container focus:ring-primary/20" />
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Ada kendala?</span>
          </label>
          <label v-if="form.has_issue" class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Penjelasan Kendala</span>
            <textarea v-model="form.issue_note" rows="2" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
          </label>

          <div class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Foto Dokumentasi <span class="font-normal text-outline">(boleh lebih dari satu)</span></span>
            <label class="flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-low px-space-md py-space-md text-center transition-colors hover:border-primary-container">
              <span class="material-symbols-outlined text-[26px] text-outline">add_a_photo</span>
              <span class="font-body-sm text-body-sm text-on-surface-variant">Ketuk untuk pilih foto</span>
              <input type="file" accept="image/*" multiple class="hidden" @change="onPhotoChange" />
            </label>
            <div v-if="photoPreviews.length" class="mt-space-xs flex flex-wrap gap-space-xs">
              <img v-for="(p, i) in photoPreviews" :key="i" :src="p" alt="preview" class="h-16 w-16 rounded-lg object-cover ring-1 ring-surface-container" />
            </div>
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Link Dokumentasi <span class="font-normal text-outline">(opsional)</span></span>
            <input v-model="form.doc_url" type="url" placeholder="https://…" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>

          <div class="flex items-center justify-end gap-space-sm border-t border-surface-container pt-space-md">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showModal = false">Batal</button>
            <button type="submit" :disabled="saving" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Simpan Laporan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
