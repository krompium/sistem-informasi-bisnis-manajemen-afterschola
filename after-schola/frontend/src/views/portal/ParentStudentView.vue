<script setup>
// Portal Orang Tua, detail anak (ORT-04..07): hasil trial + penawaran, keputusan lanjut/tidak,
// formulir pendaftaran (auto-fill dari trial, perbaikan saat revisi), dan status sesudahnya.
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDate, formatRupiah, formatStamp, unwrap } from '@/lib/format'
import { LEVELS, STAGES } from './stages'

const route = useRoute()
const loading = ref(true)
const error = ref('')
const child = ref(null)
const programs = ref([])

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'
const cardCls = 'flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm'
const btnCls = 'rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm active:scale-95 disabled:opacity-60'
const btnGhostCls = 'rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface disabled:opacity-60'

const scoreLabels = { enthusiasm: 'Antusiasme', understanding: 'Pemahaman', focus: 'Fokus', collaboration: 'Kolaborasi' }
const editable = computed(() => ['isi_pendaftaran', 'perlu_revisi'].includes(child.value?.stage))

const form = reactive({
  student_name: '', birth_date: '', origin_school: '', origin_class: '', program_id: '',
  parent_name: '', parent_phone: '', parent_email: '', parent_address: '',
})

// Pendaftaran yang sudah disimpan dipakai apa adanya; bila belum ada, auto-fill dari data trial (BR-01).
function fillForm() {
  const src = child.value.registration ?? child.value.prefill ?? {}
  for (const key of Object.keys(form)) form[key] = src[key] ?? ''
  if (!form.program_id) form.program_id = child.value.offer?.program_id ?? ''
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/parent/students/${route.params.id}`)
    child.value = data.data
    if (editable.value) {
      fillForm()
      if (!programs.value.length) programs.value = unwrap((await api.get('/public/programs')).data)
    }
  } catch (err) {
    error.value = apiErrorMessage(err, 'Data anak gagal dimuat.')
  } finally {
    loading.value = false
  }
}

// Semua aksi: kirim ke API, lalu pakai detail terbaru dari respons.
const busy = ref(false)
const actionError = ref('')
async function act(request) {
  busy.value = true
  actionError.value = ''
  try {
    const { data } = await request()
    child.value = data.data
    if (editable.value) fillForm()
  } catch (err) {
    actionError.value = apiErrorMessage(err, 'Aksi gagal. Coba lagi.')
  } finally {
    busy.value = false
  }
}

const base = () => `/parent/students/${route.params.id}`
const reason = ref('')
const askReason = ref(false)
const decide = (decision) => act(() => api.post(`${base()}/decision`, { decision, reason: decision === 'tidak' ? reason.value : undefined }))
const saveDraft = () => act(() => api.put(`${base()}/registration`, { ...form }))
const submitRegistration = () =>
  act(async () => {
    await api.put(`${base()}/registration`, { ...form })
    return api.post(`${base()}/registration/submit`)
  })

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col gap-space-md pb-space-3xl">
    <div class="pt-space-xl">
      <RouterLink to="/portal" class="font-label-md text-label-md font-semibold text-primary">← Kembali</RouterLink>
    </div>

    <p v-if="error" class="font-body-md text-body-md text-error">{{ error }}</p>
    <div v-if="loading" class="h-32 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else-if="child">
      <div class="flex flex-wrap items-start justify-between gap-space-sm">
        <div class="flex flex-col">
          <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">{{ child.name }}</h1>
          <span class="font-body-md text-body-md text-on-surface-variant">{{ LEVELS[child.education_level] }} • {{ child.origin_school }}</span>
        </div>
        <span class="rounded-full px-3 py-1.5 font-label-md text-label-md font-semibold" :class="STAGES[child.stage]?.cls">{{ STAGES[child.stage]?.label }}</span>
      </div>
      <p v-if="actionError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-sm text-body-sm text-on-error-container">{{ actionError }}</p>

      <!-- Jadwal trial -->
      <div v-if="child.stage === 'trial_terjadwal'" :class="cardCls">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Jadwal Trial</h2>
        <p class="font-body-md text-body-md text-on-surface">{{ child.program_name }} • {{ child.branch_name }}</p>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ formatStamp(child.trial_at) }}. Hasil trial akan muncul di sini setelah dievaluasi pengajar.</p>
      </div>
      <div v-if="child.stage === 'tidak_hadir'" :class="cardCls">
        <p class="font-body-md text-body-md text-on-surface-variant">Anak tidak hadir pada jadwal trial. Silakan hubungi lembaga untuk menjadwalkan ulang.</p>
      </div>

      <!-- Hasil trial + penawaran -->
      <div v-if="child.trial_result" :class="cardCls">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Hasil Trial</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ formatDate(child.trial_result.trial_date) }} • Pengajar: {{ child.trial_result.trainer_name }}</p>
        <div class="grid grid-cols-2 gap-space-xs sm:grid-cols-4">
          <div v-for="(label, key) in scoreLabels" :key="key" class="flex flex-col rounded-xl bg-surface-container-low p-space-sm">
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ label }}</span>
            <span class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ child.trial_result.scores[key] ?? '-' }}<span class="font-body-sm text-body-sm text-on-surface-variant"> / 5</span></span>
          </div>
        </div>
        <p v-if="child.trial_result.strengths" class="font-body-md text-body-md text-on-surface"><b>Kelebihan:</b> {{ child.trial_result.strengths }}</p>
        <p v-if="child.trial_result.improvements" class="font-body-md text-body-md text-on-surface"><b>Perlu ditingkatkan:</b> {{ child.trial_result.improvements }}</p>
        <p v-if="child.trial_result.recommended_level" class="font-body-md text-body-md text-on-surface"><b>Level yang disarankan:</b> {{ child.trial_result.recommended_level }}</p>
        <p v-if="child.offer" class="rounded-xl bg-primary-fixed px-space-md py-space-sm font-body-md text-body-md text-on-surface">
          Penawaran: <b>{{ child.offer.name }}</b> • {{ formatRupiah(child.offer.price) }}
        </p>
      </div>

      <!-- Keputusan -->
      <div v-if="['hasil_trial', 'tidak_lanjut'].includes(child.stage)" :class="cardCls">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ child.stage === 'tidak_lanjut' ? 'Keputusan Anda: tidak melanjutkan' : 'Lanjutkan ke program?' }}</h2>
        <p v-if="child.stage === 'tidak_lanjut' && child.decision?.reason" class="font-body-md text-body-md text-on-surface-variant">Alasan: {{ child.decision.reason }}</p>
        <div v-if="askReason" class="flex flex-col gap-1">
          <span :class="labelCls">Alasan tidak melanjutkan</span>
          <textarea v-model="reason" rows="2" :class="inputCls"></textarea>
        </div>
        <div class="flex flex-wrap gap-space-xs">
          <button :class="btnCls" :disabled="busy" @click="decide('lanjut')">{{ child.stage === 'tidak_lanjut' ? 'Ubah: Lanjutkan' : 'Lanjutkan Pendaftaran' }}</button>
          <template v-if="child.stage === 'hasil_trial'">
            <button v-if="!askReason" :class="btnGhostCls" @click="askReason = true">Tidak Lanjut</button>
            <button v-else :class="btnGhostCls" :disabled="busy || !reason.trim()" @click="decide('tidak')">Kirim: Tidak Lanjut</button>
          </template>
        </div>
      </div>

      <!-- Formulir pendaftaran -->
      <div v-if="editable" :class="cardCls">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Formulir Pendaftaran</h2>
        <p v-if="child.stage === 'perlu_revisi'" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-body-md text-on-error-container">
          Perlu revisi: {{ child.registration?.revision_note }}
        </p>
        <p v-else class="font-body-sm text-body-sm text-on-surface-variant">Data sudah terisi dari pendaftaran trial. Lengkapi dan periksa kembali.</p>
        <div class="grid gap-space-sm sm:grid-cols-2">
          <label class="flex flex-col gap-1"><span :class="labelCls">Nama anak</span><input v-model="form.student_name" :class="inputCls" /></label>
          <label class="flex flex-col gap-1"><span :class="labelCls">Tanggal lahir</span><input v-model="form.birth_date" type="date" :class="inputCls" /></label>
          <label class="flex flex-col gap-1"><span :class="labelCls">Asal sekolah</span><input v-model="form.origin_school" :class="inputCls" /></label>
          <label class="flex flex-col gap-1"><span :class="labelCls">Kelas</span><input v-model="form.origin_class" :class="inputCls" /></label>
          <label class="flex flex-col gap-1 sm:col-span-2">
            <span :class="labelCls">Paket program</span>
            <select v-model="form.program_id" :class="inputCls">
              <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }} • {{ formatRupiah(p.price) }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1"><span :class="labelCls">Nama orang tua</span><input v-model="form.parent_name" :class="inputCls" /></label>
          <label class="flex flex-col gap-1"><span :class="labelCls">Telepon</span><input v-model="form.parent_phone" :class="inputCls" /></label>
          <label class="flex flex-col gap-1 sm:col-span-2"><span :class="labelCls">Email</span><input v-model="form.parent_email" type="email" :class="inputCls" /></label>
          <label class="flex flex-col gap-1 sm:col-span-2"><span :class="labelCls">Alamat</span><textarea v-model="form.parent_address" rows="2" :class="inputCls"></textarea></label>
        </div>
        <div class="flex flex-wrap gap-space-xs">
          <button :class="btnGhostCls" :disabled="busy" @click="saveDraft">Simpan Draft</button>
          <button :class="btnCls" :disabled="busy" @click="submitRegistration">Kirim untuk Verifikasi</button>
        </div>
      </div>

      <!-- Sesudah dikirim -->
      <div v-if="child.stage === 'menunggu_verifikasi'" :class="cardCls">
        <p class="font-body-md text-body-md text-on-surface-variant">Pendaftaran sedang diperiksa admin. Anda akan diberi tahu bila ada data yang perlu diperbaiki atau saat invoice terbit.</p>
      </div>
      <div v-if="child.invoice" :class="cardCls">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Invoice</h2>
        <p class="font-body-md text-body-md text-on-surface">{{ child.invoice.number }} • <b>{{ formatRupiah(child.invoice.amount) }}</b></p>
        <p class="font-body-sm text-body-sm" :class="child.invoice.is_overdue ? 'text-error' : 'text-on-surface-variant'">Jatuh tempo {{ formatDate(child.invoice.due_date) }}</p>
        <p v-if="child.stage === 'lunas'" class="font-body-md text-body-md text-on-surface">Pembayaran sudah dikonfirmasi. Jadwal kelas akan diinformasikan setelah penempatan.</p>
        <p v-else-if="child.stage === 'menunggu_konfirmasi'" class="font-body-md text-body-md text-on-surface-variant">Bukti pembayaran sedang diperiksa Keuangan.</p>
      </div>
    </template>
  </div>
</template>
