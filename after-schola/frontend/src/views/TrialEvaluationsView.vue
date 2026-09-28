<script setup>
// Evaluasi Trial (Modul 3) — trainer menilai calon siswa setelah sesi trial;
// Management melihat semua evaluasi. Hasil rekomendasi jadi bahan Keputusan (Modul 4).
// Hak akses ditegakkan backend (TrialEvaluationPolicy); halaman ini dipakai bersama.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { formatDate, unwrap } from '@/lib/format'

const loading = ref(true)
const items = ref([])
const search = ref('')
const filterRec = ref('')

const showForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')

const emptyForm = () => ({
  participant_name: '',
  origin_school: '',
  origin_class: '',
  trial_date: new Date().toISOString().slice(0, 10),
  attendance: 'hadir',
  score_enthusiasm: null,
  score_understanding: null,
  score_focus: null,
  score_collaboration: null,
  strengths: '',
  improvements: '',
  recommended_level: '',
  recommendation: '',
})
const form = reactive(emptyForm())

const aspects = [
  { key: 'score_enthusiasm', label: 'Antusiasme' },
  { key: 'score_understanding', label: 'Pemahaman materi' },
  { key: 'score_focus', label: 'Fokus' },
  { key: 'score_collaboration', label: 'Kerja sama' },
]
const recOptions = [
  { value: 'lanjut', label: 'Lanjut', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  { value: 'ragu', label: 'Ragu-ragu', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  { value: 'tidak', label: 'Tidak lanjut', cls: 'bg-error-container text-on-error-container' },
]
const recMeta = (v) => recOptions.find((r) => r.value === v)
const levelLabel = (v) => ({ beginner: 'Beginner', intermediate: 'Intermediate' })[v] || '-'

const present = computed(() => form.attendance === 'hadir')
const total = computed(() => items.value.length)

async function load() {
  loading.value = true
  try {
    const params = {}
    if (search.value.trim()) params.q = search.value.trim()
    if (filterRec.value) params.recommendation = filterRec.value
    const { data } = await api.get('/trial-evaluations', { params })
    items.value = unwrap(data)
  } finally {
    loading.value = false
  }
}

let timer
watch([search, filterRec], () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})

function openCreate() {
  editingId.value = null
  Object.assign(form, emptyForm())
  formError.value = ''
  showForm.value = true
}

function openEdit(item) {
  editingId.value = item.id
  const next = emptyForm()
  for (const key of Object.keys(next)) next[key] = item[key] ?? next[key]
  Object.assign(form, next)
  formError.value = ''
  showForm.value = true
}

async function submitForm() {
  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form }
    if (editingId.value) await api.put(`/trial-evaluations/${editingId.value}`, payload)
    else await api.post('/trial-evaluations', payload)
    showForm.value = false
    await load()
  } catch (err) {
    formError.value =
      err?.response?.status === 422
        ? Object.values(err.response.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
        : err?.response?.status === 403
          ? 'Anda tidak berhak mengubah evaluasi ini.'
          : 'Gagal menyimpan evaluasi.'
  } finally {
    saving.value = false
  }
}

async function removeItem(item) {
  if (!confirm(`Hapus evaluasi trial "${item.participant_name}"?`)) return
  try {
    await api.delete(`/trial-evaluations/${item.id}`)
    await load()
  } catch {
    alert('Gagal menghapus evaluasi.')
  }
}

onMounted(load)

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Trial & Pendaftaran</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Evaluasi Trial</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Penilaian calon siswa setelah sesi trial. Rekomendasi trainer menjadi bahan keputusan lanjut/tidak.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openCreate"
      >
        <span class="material-symbols-outlined text-[18px]">add</span>
        <span>Buat Evaluasi</span>
      </button>
    </div>

    <!-- Filter -->
    <div class="mb-space-md flex flex-col gap-space-sm sm:flex-row">
      <input v-model="search" placeholder="Cari nama calon siswa..." :class="[inputCls, 'flex-1']" />
      <select v-model="filterRec" :class="[inputCls, 'sm:w-52']">
        <option value="">Semua rekomendasi</option>
        <option v-for="r in recOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>
    </div>

    <div v-if="loading" class="flex flex-col gap-space-sm">
      <div v-for="i in 3" :key="i" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!total" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">rate_review</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada evaluasi trial</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Klik "Buat Evaluasi" setelah sesi trial selesai.</p>
    </div>

    <div v-else class="flex flex-col gap-space-sm">
      <div v-for="item in items" :key="item.id" class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ item.participant_name }}</h3>
            <span class="font-label-sm text-label-sm text-on-surface-variant">
              {{ [item.origin_school, item.origin_class].filter(Boolean).join(' • ') || 'Asal sekolah belum diisi' }}
            </span>
          </div>
          <div class="flex items-center gap-space-2xs">
            <span v-if="item.attendance === 'tidak_hadir'" class="rounded-full bg-surface-container px-2.5 py-1 font-label-sm text-label-sm font-semibold text-on-surface-variant">Tidak hadir</span>
            <span v-else-if="recMeta(item.recommendation)" class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="recMeta(item.recommendation).cls">
              {{ recMeta(item.recommendation).label }}
            </span>
            <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" title="Ubah" @click="openEdit(item)">
              <span class="material-symbols-outlined text-[18px]">edit</span>
            </button>
            <button class="rounded-lg p-2 text-error hover:bg-error-container" title="Hapus" @click="removeItem(item)">
              <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>
        </div>

        <div v-if="item.attendance === 'hadir'" class="flex flex-wrap gap-x-space-lg gap-y-space-2xs font-body-sm text-body-sm text-on-surface-variant">
          <span>Rata-rata skor: <b class="text-on-surface">{{ item.average_score ?? '-' }}</b> / 5</span>
          <span>Level disarankan: <b class="text-on-surface">{{ levelLabel(item.recommended_level) }}</b></span>
        </div>
        <p v-if="item.strengths" class="whitespace-pre-line font-body-sm text-body-sm text-on-surface-variant"><b class="text-on-surface">Kekuatan:</b> {{ item.strengths }}</p>
        <p v-if="item.improvements" class="whitespace-pre-line font-body-sm text-body-sm text-on-surface-variant"><b class="text-on-surface">Perlu ditingkatkan:</b> {{ item.improvements }}</p>
        <span class="font-label-sm text-label-sm text-outline">
          Trial {{ formatDate(item.trial_date) }}{{ item.trainer_name ? ` • Dinilai oleh ${item.trainer_name}` : '' }}
        </span>
      </div>
    </div>

    <!-- Modal form -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showForm = false">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editingId ? 'Ubah Evaluasi Trial' : 'Buat Evaluasi Trial' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="flex flex-col gap-space-md overflow-y-auto p-space-lg" @submit.prevent="submitForm">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama calon siswa</span>
            <input v-model="form.participant_name" required :class="inputCls" />
          </label>
          <div class="grid grid-cols-2 gap-space-sm">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Asal sekolah</span>
              <input v-model="form.origin_school" :class="inputCls" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kelas</span>
              <input v-model="form.origin_class" placeholder="mis. 8A" :class="inputCls" />
            </label>
          </div>
          <div class="grid grid-cols-2 gap-space-sm">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tanggal trial</span>
              <input v-model="form.trial_date" type="date" required :class="inputCls" />
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kehadiran</span>
              <select v-model="form.attendance" :class="inputCls">
                <option value="hadir">Hadir</option>
                <option value="tidak_hadir">Tidak hadir</option>
              </select>
            </label>
          </div>

          <template v-if="present">
            <div class="flex flex-col gap-space-sm rounded-xl bg-surface-container-low p-space-md">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Skor (1 = kurang, 5 = sangat baik)</span>
              <div v-for="a in aspects" :key="a.key" class="flex items-center justify-between gap-space-sm">
                <span class="font-body-sm text-body-sm text-on-surface">{{ a.label }}</span>
                <div class="flex gap-1">
                  <button
                    v-for="n in 5"
                    :key="n"
                    type="button"
                    class="h-8 w-8 rounded-lg font-label-md text-label-md font-semibold transition-all"
                    :class="form[a.key] === n ? 'bg-primary-container text-on-primary' : 'bg-white text-on-surface-variant hover:bg-surface-container'"
                    @click="form[a.key] = n"
                  >
                    {{ n }}
                  </button>
                </div>
              </div>
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kekuatan</span>
              <textarea v-model="form.strengths" rows="2" :class="inputCls"></textarea>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Perlu ditingkatkan</span>
              <textarea v-model="form.improvements" rows="2" :class="inputCls"></textarea>
            </label>
            <div class="grid grid-cols-2 gap-space-sm">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Level disarankan</span>
                <select v-model="form.recommended_level" :class="inputCls">
                  <option value="">-</option>
                  <option value="beginner">Beginner</option>
                  <option value="intermediate">Intermediate</option>
                </select>
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Rekomendasi trainer</span>
                <select v-model="form.recommendation" required :class="inputCls">
                  <option value="" disabled>Pilih...</option>
                  <option v-for="r in recOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
              </label>
            </div>
          </template>

          <button
            type="submit"
            :disabled="saving"
            class="mt-space-xs inline-flex items-center justify-center rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
          >
            {{ saving ? 'Menyimpan...' : editingId ? 'Simpan Perubahan' : 'Simpan Evaluasi' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
