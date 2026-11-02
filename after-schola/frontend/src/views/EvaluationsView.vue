<script setup>
// Evaluasi Siswa (Modul 9a, TRN-09/TRN-10): trainer menilai siswa per periode dengan rubrik program, menyimpan draft,
// lalu mengajukan tinjau ke koordinator. Aturan sebenarnya ditegakkan backend.
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const inputCls = 'w-full rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'
const STATUS = {
  draft: { label: 'Draft', cls: 'bg-surface-variant text-on-surface-variant' },
  ditinjau: { label: 'Ditinjau', cls: 'bg-primary-container text-on-primary-container' },
  terkirim: { label: 'Terkirim', cls: 'bg-secondary-container text-on-secondary-container' },
}

const evaluations = ref([])
const classes = ref([])
const roster = ref([])
const loading = ref(false)
const loadError = ref('')

// Evaluasi baru
const newForm = ref({ class_group_id: '', enrollment_id: '', period: new Date().toISOString().slice(0, 7) })
const creating = ref(false)
const createError = ref('')

// Editor
const current = ref(null)
const summary = ref('')
const scores = ref({}) // criterion_id -> string input
const saving = ref(false)
const editorError = ref('')
const editorNotice = ref('')

const editable = computed(() => current.value?.report?.status === 'draft')

async function loadList() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/evaluations', { params: { per_page: 50 } })
    evaluations.value = unwrap(data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat evaluasi.')
  } finally {
    loading.value = false
  }
}

async function loadClasses() {
  try {
    const { data } = await api.get('/class-groups', { params: { per_page: 100, is_active: 1 } })
    classes.value = unwrap(data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat daftar kelas.')
  }
}

watch(
  () => newForm.value.class_group_id,
  async (id) => {
    roster.value = []
    newForm.value.enrollment_id = ''
    if (!id) return
    try {
      const { data } = await api.get(`/class-groups/${id}/enrollments`)
      roster.value = unwrap(data)
    } catch (err) {
      createError.value = apiErrorMessage(err, 'Gagal memuat siswa kelas.')
    }
  },
)

function open(evaluation) {
  current.value = evaluation
  summary.value = evaluation.summary ?? ''
  scores.value = Object.fromEntries((evaluation.criteria ?? []).map((c) => [c.id, c.score ?? '']))
  editorError.value = ''
  editorNotice.value = ''
}

async function select(id) {
  try {
    const { data } = await api.get(`/evaluations/${id}`)
    open(unwrap(data))
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal membuka evaluasi.')
  }
}

async function create() {
  creating.value = true
  createError.value = ''
  try {
    const { data } = await api.post('/evaluations', { enrollment_id: newForm.value.enrollment_id, period: newForm.value.period })
    open(unwrap(data))
    await loadList()
  } catch (err) {
    createError.value = apiErrorMessage(err, 'Gagal membuat evaluasi.')
  } finally {
    creating.value = false
  }
}

async function saveDraft() {
  const payload = {
    summary: summary.value,
    // Hanya kriteria yang sudah diisi; sisanya boleh menyusul selama masih draft.
    scores: Object.entries(scores.value)
      .filter(([, v]) => v !== '' && v !== null)
      .map(([criterion_id, score]) => ({ criterion_id: Number(criterion_id), score: Number(score) })),
  }
  const { data } = await api.put(`/evaluations/${current.value.id}`, payload)
  open(unwrap(data))
}

async function onSave() {
  saving.value = true
  editorError.value = ''
  try {
    await saveDraft()
    editorNotice.value = 'Draft tersimpan.'
    await loadList()
  } catch (err) {
    editorError.value = apiErrorMessage(err, 'Gagal menyimpan draft.')
  } finally {
    saving.value = false
  }
}

async function onSubmit() {
  saving.value = true
  editorError.value = ''
  try {
    await saveDraft()
    const { data } = await api.post(`/progress-reports/${current.value.report.id}/submit`)
    open(unwrap(data))
    editorNotice.value = 'Diajukan ke koordinator untuk ditinjau.'
    await loadList()
  } catch (err) {
    editorError.value = apiErrorMessage(err, 'Gagal mengajukan evaluasi.')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadClasses()
  loadList()
})
</script>

<template>
  <div class="space-y-space-md">
    <div>
      <h1 class="font-headline-md text-headline-md text-on-surface">Evaluasi Siswa</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Nilai perkembangan siswa per periode dengan rubrik program, lalu ajukan ke koordinator.</p>
    </div>

    <p v-if="loadError" class="rounded-lg bg-error-container px-space-sm py-2 text-on-error-container">{{ loadError }}</p>

    <form class="space-y-space-sm rounded-xl border border-outline-variant bg-white p-space-md" @submit.prevent="create">
      <h2 class="font-title-md text-title-md text-on-surface">Evaluasi baru</h2>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Kelas</span>
        <select v-model="newForm.class_group_id" required :class="inputCls">
          <option value="" disabled>Pilih kelas</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Siswa</span>
        <select v-model="newForm.enrollment_id" required :disabled="!newForm.class_group_id" :class="inputCls">
          <option value="" disabled>Pilih siswa</option>
          <option v-for="e in roster" :key="e.id" :value="e.id">{{ e.student.name }}</option>
        </select>
      </label>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Periode</span>
        <input v-model="newForm.period" required maxlength="30" placeholder="mis. 2026-11" :class="inputCls" />
      </label>
      <p v-if="createError" class="text-error">{{ createError }}</p>
      <button type="submit" :disabled="creating" class="w-full rounded-lg bg-primary px-space-md py-2.5 font-label-md text-label-md text-on-primary disabled:opacity-60 sm:w-auto">
        {{ creating ? 'Membuat…' : 'Buat evaluasi' }}
      </button>
    </form>

    <section v-if="current" class="space-y-space-sm rounded-xl border border-primary bg-white p-space-md">
      <div class="flex flex-wrap items-baseline justify-between gap-x-space-sm">
        <h2 class="font-title-md text-title-md text-on-surface">{{ current.enrollment?.student.name }} · {{ current.period }}</h2>
        <span class="rounded-full px-space-sm py-0.5 font-label-md text-label-md" :class="STATUS[current.report.status].cls">{{ STATUS[current.report.status].label }}</span>
      </div>
      <p v-if="current.report.review_note" class="rounded-lg bg-error-container px-space-sm py-2 text-on-error-container">
        Catatan koordinator: {{ current.report.review_note }}
      </p>
      <p v-if="!current.criteria.length" class="text-on-surface-variant">Program ini belum punya kriteria rubrik. Minta Management menambahkannya.</p>

      <label v-for="c in current.criteria" :key="c.id" class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">{{ c.name }} (maks. {{ c.max_score }})</span>
        <input v-model="scores[c.id]" type="number" min="0" :max="c.max_score" step="1" inputmode="numeric" :disabled="!editable" :class="inputCls" />
      </label>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Ringkasan perkembangan</span>
        <textarea v-model="summary" rows="4" maxlength="5000" :disabled="!editable" :class="inputCls" />
      </label>

      <p v-if="editorError" class="text-error">{{ editorError }}</p>
      <p v-if="editorNotice" class="text-primary">{{ editorNotice }}</p>
      <p v-if="!editable" class="text-on-surface-variant">Evaluasi ini tidak bisa diubah pada status {{ STATUS[current.report.status].label.toLowerCase() }}.</p>
      <div v-else class="flex flex-col gap-space-sm sm:flex-row">
        <button :disabled="saving" class="rounded-lg border border-primary px-space-md py-2.5 font-label-md text-label-md text-primary disabled:opacity-60" @click="onSave">Simpan draft</button>
        <button :disabled="saving" class="rounded-lg bg-primary px-space-md py-2.5 font-label-md text-label-md text-on-primary disabled:opacity-60" @click="onSubmit">Ajukan tinjau</button>
      </div>
    </section>

    <p v-if="loading" class="text-on-surface-variant">Memuat…</p>
    <p v-else-if="!evaluations.length" class="rounded-xl border border-dashed border-outline-variant p-space-md text-center text-on-surface-variant">Belum ada evaluasi.</p>
    <ul v-else class="space-y-space-sm">
      <li v-for="e in evaluations" :key="e.id">
        <button class="w-full rounded-xl border border-outline-variant bg-white p-space-md text-left" @click="select(e.id)">
          <div class="flex flex-wrap items-baseline justify-between gap-x-space-sm">
            <span class="font-title-md text-title-md text-on-surface">{{ e.enrollment.student.name }}</span>
            <span class="rounded-full px-space-sm py-0.5 font-label-md text-label-md" :class="STATUS[e.report.status].cls">{{ STATUS[e.report.status].label }}</span>
          </div>
          <div class="font-label-md text-label-md text-on-surface-variant">{{ e.enrollment.class_group.name }} · {{ e.period }}</div>
          <div v-if="e.report.review_note" class="mt-space-xs text-error">Dikembalikan: {{ e.report.review_note }}</div>
        </button>
      </li>
    </ul>
  </div>
</template>
