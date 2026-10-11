<script setup>
// Keputusan & Pendaftaran Resmi (Modul 4) — Management mencatat keputusan orang tua
// (lanjut / tidak / pikir-pikir) dari hasil evaluasi trial, lalu mengisi form pendaftaran
// resmi bila lanjut. Pendaftaran yang diajukan berstatus "menunggu verifikasi" (serah ke Modul 5).
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { formatDate, unwrap } from '@/lib/format'

const loading = ref(true)
const rows = ref([])
const search = ref('')
const filterStatus = ref('')

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

const recMeta = {
  lanjut: { label: 'Lanjut', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  ragu: { label: 'Ragu-ragu', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  tidak: { label: 'Tidak lanjut', cls: 'bg-error-container text-on-error-container' },
}
const decisionOptions = [
  { value: 'lanjut', label: 'Lanjut', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  { value: 'pikir_pikir', label: 'Pikir-pikir', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  { value: 'tidak', label: 'Tidak lanjut', cls: 'bg-error-container text-on-error-container' },
]
const decisionMeta = (v) => decisionOptions.find((d) => d.value === v)
const levelLabel = (v) => ({ beginner: 'Beginner', intermediate: 'Intermediate' })[v] || '-'

async function load() {
  loading.value = true
  try {
    const params = {}
    if (search.value.trim()) params.q = search.value.trim()
    if (filterStatus.value) params.status = filterStatus.value
    const { data } = await api.get('/enrollments', { params })
    rows.value = unwrap(data)
  } finally {
    loading.value = false
  }
}
let timer
watch([search, filterStatus], () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})
onMounted(load)

function errMsg(err, fallback) {
  return err?.response?.status === 422
    ? Object.values(err.response.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
    : fallback
}

// ---------- Keputusan ----------
const showDecision = ref(false)
const activeRow = ref(null)
const saving = ref(false)
const formError = ref('')
const dForm = reactive({ decision: '', reason: '', decided_at: '', follow_up_date: '' })

function openDecision(row) {
  activeRow.value = row
  const d = row.decision
  Object.assign(dForm, {
    decision: d?.decision ?? '',
    reason: d?.reason ?? '',
    decided_at: d?.decided_at ?? new Date().toISOString().slice(0, 10),
    follow_up_date: d?.follow_up_date ?? '',
  })
  formError.value = ''
  showDecision.value = true
}

async function submitDecision() {
  if (!dForm.decision) {
    formError.value = 'Pilih keputusan terlebih dahulu.'
    return
  }
  saving.value = true
  formError.value = ''
  try {
    await api.put(`/trial-evaluations/${activeRow.value.evaluation_id}/decision`, { ...dForm })
    showDecision.value = false
    await load()
  } catch (err) {
    formError.value = errMsg(err, 'Gagal menyimpan keputusan.')
  } finally {
    saving.value = false
  }
}

// ---------- Pendaftaran resmi ----------
const showReg = ref(false)
const rForm = reactive({
  student_name: '', birth_date: '', origin_school: '', origin_class: '', level: '',
  parent_name: '', parent_phone: '', parent_email: '', parent_address: '',
})

function openRegistration(row) {
  activeRow.value = row
  const r = row.registration
  Object.assign(rForm, {
    student_name: r?.student_name ?? row.participant_name ?? '',
    birth_date: r?.birth_date ?? '',
    origin_school: r?.origin_school ?? row.origin_school ?? '',
    origin_class: r?.origin_class ?? row.origin_class ?? '',
    level: r?.level ?? row.recommended_level ?? '',
    parent_name: r?.parent_name ?? '',
    parent_phone: r?.parent_phone ?? '',
    parent_email: r?.parent_email ?? '',
    parent_address: r?.parent_address ?? '',
  })
  formError.value = ''
  showReg.value = true
}

async function submitRegistration() {
  saving.value = true
  formError.value = ''
  try {
    await api.put(`/trial-evaluations/${activeRow.value.evaluation_id}/registration`, { ...rForm })
    showReg.value = false
    await load()
  } catch (err) {
    formError.value = errMsg(err, 'Gagal menyimpan pendaftaran.')
  } finally {
    saving.value = false
  }
}

async function submitForVerification(row) {
  if (!confirm(`Ajukan pendaftaran "${row.participant_name}" untuk verifikasi? Data tidak bisa diubah lagi setelahnya.`)) return
  try {
    await api.post(`/trial-evaluations/${row.evaluation_id}/registration/submit`)
    await load()
  } catch (err) {
    alert(errMsg(err, 'Gagal mengajukan pendaftaran.'))
  }
}

const isDraft = (row) => row.registration?.status === 'draft'
const isSubmitted = (row) => row.registration?.status === 'menunggu_verifikasi'
const total = computed(() => rows.value.length)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Trial & Pendaftaran</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Keputusan & Pendaftaran</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Catat keputusan orang tua setelah trial, lalu lengkapi pendaftaran resmi bila lanjut.
      </p>
    </div>

    <div class="mb-space-md flex flex-col gap-space-sm sm:flex-row">
      <input v-model="search" placeholder="Cari nama calon siswa..." :class="[inputCls, 'flex-1']" />
      <select v-model="filterStatus" :class="[inputCls, 'sm:w-52']">
        <option value="">Semua status</option>
        <option value="belum">Belum diputuskan</option>
        <option value="lanjut">Lanjut</option>
        <option value="pikir_pikir">Pikir-pikir</option>
        <option value="tidak">Tidak lanjut</option>
      </select>
    </div>

    <div v-if="loading" class="flex flex-col gap-space-sm">
      <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!total" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">how_to_reg</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada evaluasi untuk diputuskan</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Evaluasi trial yang pesertanya hadir akan muncul di sini.</p>
    </div>

    <div v-else class="flex flex-col gap-space-sm">
      <div v-for="row in rows" :key="row.evaluation_id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ row.participant_name }}</h3>
            <span class="font-label-sm text-label-sm text-on-surface-variant">
              {{ [row.origin_school, row.origin_class].filter(Boolean).join(' • ') || 'Asal sekolah belum diisi' }}
              • Trial {{ formatDate(row.trial_date) }}
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-space-2xs">
            <span v-if="recMeta[row.recommendation]" class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="recMeta[row.recommendation].cls">
              Trainer: {{ recMeta[row.recommendation].label }}
            </span>
            <span v-if="decisionMeta(row.decision?.decision)" class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="decisionMeta(row.decision.decision).cls">
              Keputusan: {{ decisionMeta(row.decision.decision).label }}
            </span>
            <span v-else class="rounded-full bg-surface-container px-2.5 py-1 font-label-sm text-label-sm font-semibold text-on-surface-variant">Belum diputuskan</span>
          </div>
        </div>

        <p v-if="row.decision?.reason" class="whitespace-pre-line font-body-sm text-body-sm text-on-surface-variant"><b class="text-on-surface">Alasan:</b> {{ row.decision.reason }}</p>
        <p v-if="row.decision?.follow_up_date" class="font-body-sm text-body-sm text-on-surface-variant"><b class="text-on-surface">Follow-up:</b> {{ formatDate(row.decision.follow_up_date) }}</p>

        <div v-if="row.decision?.decision === 'lanjut'" class="rounded-xl bg-surface-container-low p-space-md font-body-sm text-body-sm text-on-surface-variant">
          <template v-if="row.registration">
            <div class="flex flex-wrap items-center gap-space-sm">
              <span class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="isSubmitted(row) ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-[#FEF3C7] text-[#92400E]'">
                {{ isSubmitted(row) ? 'Menunggu verifikasi' : 'Draft pendaftaran' }}
              </span>
              <span>Level {{ levelLabel(row.registration.level) }} • Wali: {{ row.registration.parent_name }} ({{ row.registration.parent_phone }})</span>
            </div>
          </template>
          <span v-else>Pendaftaran resmi belum diisi.</span>
        </div>

        <div class="flex flex-wrap gap-space-xs">
          <button
            v-if="!isSubmitted(row)"
            class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low"
            @click="openDecision(row)"
          >
            {{ row.decision ? 'Ubah Keputusan' : 'Catat Keputusan' }}
          </button>
          <button
            v-if="row.decision?.decision === 'lanjut' && !isSubmitted(row)"
            class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low"
            @click="openRegistration(row)"
          >
            {{ row.registration ? 'Ubah Pendaftaran' : 'Isi Pendaftaran' }}
          </button>
          <button
            v-if="isDraft(row)"
            class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95"
            @click="submitForVerification(row)"
          >
            Ajukan Verifikasi
          </button>
        </div>
      </div>
    </div>

    <!-- Modal keputusan -->
    <div v-if="showDecision" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showDecision = false">
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Keputusan</h2>
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ activeRow?.participant_name }}</span>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showDecision = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md p-space-lg" @submit.prevent="submitDecision">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>
          <div class="flex flex-col gap-1">
            <span :class="labelCls">Keputusan orang tua</span>
            <div class="grid grid-cols-3 gap-space-xs">
              <button
                v-for="d in decisionOptions"
                :key="d.value"
                type="button"
                class="rounded-xl border px-2 py-2.5 font-label-md text-label-md font-semibold transition-all"
                :class="dForm.decision === d.value ? 'border-primary-container bg-primary-container text-on-primary' : 'border-outline-variant bg-white text-on-surface-variant hover:bg-surface-container-low'"
                @click="dForm.decision = d.value"
              >
                {{ d.label }}
              </button>
            </div>
          </div>
          <label class="flex flex-col gap-1">
            <span :class="labelCls">Tanggal keputusan</span>
            <input v-model="dForm.decided_at" type="date" :class="inputCls" />
          </label>
          <label v-if="dForm.decision === 'pikir_pikir'" class="flex flex-col gap-1">
            <span :class="labelCls">Tanggal follow-up</span>
            <input v-model="dForm.follow_up_date" type="date" required :class="inputCls" />
          </label>
          <label class="flex flex-col gap-1">
            <span :class="labelCls">Alasan / catatan {{ dForm.decision === 'tidak' ? '(wajib)' : '(opsional)' }}</span>
            <textarea v-model="dForm.reason" rows="3" :required="dForm.decision === 'tidak'" :class="inputCls"></textarea>
          </label>
          <button type="submit" :disabled="saving" class="inline-flex items-center justify-center rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
            {{ saving ? 'Menyimpan...' : 'Simpan Keputusan' }}
          </button>
        </form>
      </div>
    </div>

    <!-- Modal pendaftaran resmi -->
    <div v-if="showReg" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showReg = false">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Pendaftaran Resmi</h2>
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ activeRow?.participant_name }}</span>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showReg = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <form class="flex flex-col gap-space-md overflow-y-auto p-space-lg" @submit.prevent="submitRegistration">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Data siswa</span>
          <label class="flex flex-col gap-1">
            <span :class="labelCls">Nama siswa</span>
            <input v-model="rForm.student_name" required :class="inputCls" />
          </label>
          <div class="grid grid-cols-2 gap-space-sm">
            <label class="flex flex-col gap-1">
              <span :class="labelCls">Tanggal lahir</span>
              <input v-model="rForm.birth_date" type="date" :class="inputCls" />
            </label>
            <label class="flex flex-col gap-1">
              <span :class="labelCls">Level</span>
              <select v-model="rForm.level" required :class="inputCls">
                <option value="" disabled>Pilih...</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
              </select>
            </label>
          </div>
          <div class="grid grid-cols-2 gap-space-sm">
            <label class="flex flex-col gap-1">
              <span :class="labelCls">Asal sekolah</span>
              <input v-model="rForm.origin_school" :class="inputCls" />
            </label>
            <label class="flex flex-col gap-1">
              <span :class="labelCls">Kelas</span>
              <input v-model="rForm.origin_class" :class="inputCls" />
            </label>
          </div>

          <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Orang tua / wali</span>
          <label class="flex flex-col gap-1">
            <span :class="labelCls">Nama orang tua / wali</span>
            <input v-model="rForm.parent_name" required :class="inputCls" />
          </label>
          <div class="grid grid-cols-2 gap-space-sm">
            <label class="flex flex-col gap-1">
              <span :class="labelCls">No. HP / WhatsApp</span>
              <input v-model="rForm.parent_phone" required :class="inputCls" />
            </label>
            <label class="flex flex-col gap-1">
              <span :class="labelCls">Email (opsional)</span>
              <input v-model="rForm.parent_email" type="email" :class="inputCls" />
            </label>
          </div>
          <label class="flex flex-col gap-1">
            <span :class="labelCls">Alamat (opsional)</span>
            <textarea v-model="rForm.parent_address" rows="2" :class="inputCls"></textarea>
          </label>

          <button type="submit" :disabled="saving" class="inline-flex items-center justify-center rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
            {{ saving ? 'Menyimpan...' : 'Simpan Pendaftaran' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
