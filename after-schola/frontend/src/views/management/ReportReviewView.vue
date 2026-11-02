<script setup>
// Tinjau Raport (Modul 9b, MGT-50/51): koordinator memeriksa evaluasi yang diajukan trainer, lalu menyetujui
// (raport terkirim ke ortu, tidak bisa diubah lagi) atau mengembalikan dengan catatan. Aturan sebenarnya di backend.
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateShort, unwrap } from '@/lib/format'

const inputCls = 'w-full rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'
const STATUSES = [
  { value: 'ditinjau', label: 'Menunggu tinjauan' },
  { value: 'terkirim', label: 'Terkirim' },
  { value: 'draft', label: 'Draft / dikembalikan' },
]

const status = ref('ditinjau')
const reports = ref([])
const loading = ref(false)
const loadError = ref('')
const notice = ref('')

const selected = ref(null) // report dari antrian
const detail = ref(null) // evaluasi lengkap (skor, ringkasan)
const notes = ref([])
const returning = ref(false)
const returnNote = ref('')
const acting = ref(false)
const actionError = ref('')

async function loadQueue() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/progress-reports', { params: { status: status.value, per_page: 50 } })
    reports.value = unwrap(data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat antrian raport.')
  } finally {
    loading.value = false
  }
}

async function open(report) {
  selected.value = report
  detail.value = null
  notes.value = []
  returning.value = false
  returnNote.value = ''
  actionError.value = ''
  notice.value = ''
  try {
    const ev = report.evaluation
    const [d, n] = await Promise.all([
      api.get(`/evaluations/${ev.id}`),
      api.get('/progress-notes', { params: { student_id: ev.student.id, class_group_id: ev.class_group.id, per_page: 10 } }),
    ])
    detail.value = unwrap(d.data)
    notes.value = unwrap(n.data)
  } catch (err) {
    actionError.value = apiErrorMessage(err, 'Gagal membuka evaluasi.')
  }
}

async function act(path, body, doneMessage) {
  acting.value = true
  actionError.value = ''
  try {
    await api.post(`/progress-reports/${selected.value.id}/${path}`, body)
    selected.value = null
    detail.value = null
    notice.value = doneMessage
    await loadQueue()
  } catch (err) {
    actionError.value = apiErrorMessage(err, 'Aksi gagal.')
  } finally {
    acting.value = false
  }
}

function approve() {
  if (!window.confirm('Raport akan dikirim ke orang tua dan tidak bisa diubah lagi. Lanjutkan?')) return
  act('approve', {}, 'Raport diterbitkan dan orang tua diberi tahu.')
}

const sendBack = () => act('return', { note: returnNote.value }, 'Raport dikembalikan ke trainer.')

watch(status, () => {
  selected.value = null
  detail.value = null
  loadQueue()
})
onMounted(loadQueue)
</script>

<template>
  <div class="space-y-space-md">
    <div>
      <h1 class="font-headline-md text-headline-md text-on-surface">Tinjau Raport</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Periksa evaluasi yang diajukan trainer sebelum raport dikirim ke orang tua.</p>
    </div>

    <p v-if="loadError" class="rounded-lg bg-error-container px-space-sm py-2 text-on-error-container">{{ loadError }}</p>
    <p v-if="notice" class="rounded-lg bg-secondary-container px-space-sm py-2 text-on-secondary-container">{{ notice }}</p>

    <label class="block sm:max-w-xs">
      <span class="font-label-md text-label-md text-on-surface-variant">Status</span>
      <select v-model="status" :class="inputCls">
        <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
    </label>

    <section v-if="selected" class="space-y-space-sm rounded-xl border border-primary bg-white p-space-md">
      <div>
        <h2 class="font-title-md text-title-md text-on-surface">{{ selected.evaluation.student.name }} · {{ selected.evaluation.period }}</h2>
        <p class="font-label-md text-label-md text-on-surface-variant">
          {{ selected.evaluation.class_group.name }} · dinilai oleh {{ selected.evaluation.evaluator.name }}
        </p>
      </div>

      <p v-if="!detail && !actionError" class="text-on-surface-variant">Memuat…</p>
      <template v-if="detail">
        <ul class="divide-y divide-outline-variant rounded-lg border border-outline-variant">
          <li v-for="c in detail.criteria" :key="c.id" class="flex justify-between px-space-sm py-2">
            <span>{{ c.name }}</span>
            <span class="font-title-md text-title-md">{{ c.score ?? '–' }} / {{ c.max_score }}</span>
          </li>
        </ul>
        <div>
          <h3 class="font-label-md text-label-md text-on-surface-variant">Ringkasan trainer</h3>
          <p class="whitespace-pre-line font-body-md text-body-md text-on-surface">{{ detail.summary }}</p>
        </div>
        <div v-if="notes.length">
          <h3 class="font-label-md text-label-md text-on-surface-variant">Catatan progres terbaru</h3>
          <ul class="space-y-space-xs">
            <li v-for="n in notes" :key="n.id" class="rounded-lg bg-surface-variant px-space-sm py-2">
              <span class="font-label-md text-label-md text-on-surface-variant">{{ formatDateShort(n.class_session.date) }} · Pertemuan {{ n.class_session.meeting_no }}</span>
              <p class="whitespace-pre-line">{{ n.note }}</p>
            </li>
          </ul>
        </div>
      </template>

      <p v-if="actionError" class="text-error">{{ actionError }}</p>

      <template v-if="selected.status === 'ditinjau' && detail">
        <div v-if="returning" class="space-y-space-xs">
          <label class="block">
            <span class="font-label-md text-label-md text-on-surface-variant">Catatan untuk trainer (wajib)</span>
            <textarea v-model="returnNote" rows="3" maxlength="2000" :class="inputCls" />
          </label>
          <div class="flex gap-space-sm">
            <button :disabled="acting || !returnNote.trim()" class="rounded-lg bg-primary px-space-md py-2.5 text-on-primary disabled:opacity-60" @click="sendBack">Kirim catatan</button>
            <button class="rounded-lg border border-outline-variant px-space-md py-2.5" @click="returning = false">Batal</button>
          </div>
        </div>
        <div v-else class="flex flex-col gap-space-sm sm:flex-row">
          <button :disabled="acting" class="rounded-lg bg-primary px-space-md py-2.5 font-label-md text-label-md text-on-primary disabled:opacity-60" @click="approve">Setujui &amp; terbitkan</button>
          <button :disabled="acting" class="rounded-lg border border-primary px-space-md py-2.5 font-label-md text-label-md text-primary disabled:opacity-60" @click="returning = true">Kembalikan ke trainer</button>
        </div>
      </template>
      <p v-else-if="selected.status === 'terkirim'" class="text-on-surface-variant">Raport sudah terkirim ke orang tua dan tidak bisa diubah.</p>
    </section>

    <p v-if="loading" class="text-on-surface-variant">Memuat…</p>
    <p v-else-if="!reports.length" class="rounded-xl border border-dashed border-outline-variant p-space-md text-center text-on-surface-variant">Tidak ada raport pada status ini.</p>
    <ul v-else class="space-y-space-sm">
      <li v-for="r in reports" :key="r.id">
        <button class="w-full rounded-xl border border-outline-variant bg-white p-space-md text-left" @click="open(r)">
          <div class="flex flex-wrap items-baseline justify-between gap-x-space-sm">
            <span class="font-title-md text-title-md text-on-surface">{{ r.evaluation.student.name }}</span>
            <span class="font-label-md text-label-md text-on-surface-variant">{{ r.evaluation.period }}</span>
          </div>
          <div class="font-label-md text-label-md text-on-surface-variant">{{ r.evaluation.class_group.name }} · {{ r.evaluation.evaluator.name }}</div>
          <div v-if="r.review_note" class="mt-space-xs text-error">Dikembalikan: {{ r.review_note }}</div>
        </button>
      </li>
    </ul>
  </div>
</template>
