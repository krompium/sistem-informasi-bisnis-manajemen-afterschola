<script setup>
// Portal Orang Tua (ORT-02/03): daftar anak beserta tahapnya, dan tambah anak untuk trial tanpa isi ulang data orang tua.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatStamp, unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { LEVELS, STAGES } from './stages'

const auth = useAuthStore()
const loading = ref(true)
const error = ref('')
const children = ref([])

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/parent/students')
    children.value = unwrap(data)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Daftar anak gagal dimuat.')
  } finally {
    loading.value = false
  }
}

// --- Tambah anak (ORT-03) ---
const add = reactive({ open: false, saving: false, error: '', programs: [], slots: [], loadingSlots: false })
const form = reactive({ name: '', education_level: '', origin_school: '', program_id: '', trial_slot_id: '', consent: false })

async function openAdd() {
  Object.assign(form, { name: '', education_level: '', origin_school: '', program_id: '', trial_slot_id: '', consent: false })
  Object.assign(add, { open: true, error: '', slots: [] })
  if (!add.programs.length) {
    try {
      const { data } = await api.get('/public/programs')
      add.programs = unwrap(data)
    } catch (err) {
      add.error = 'Daftar program gagal dimuat.'
    }
  }
}

async function loadSlots() {
  form.trial_slot_id = ''
  add.slots = []
  if (!form.program_id) return
  add.loadingSlots = true
  try {
    const { data } = await api.get('/public/trial-slots', { params: { program_id: form.program_id } })
    add.slots = unwrap(data)
  } catch (err) {
    add.slots = []
  } finally {
    add.loadingSlots = false
  }
}

async function submitAdd() {
  add.saving = true
  add.error = ''
  try {
    await api.post('/parent/students', {
      name: form.name,
      education_level: form.education_level,
      origin_school: form.origin_school,
      trial_slot_id: form.trial_slot_id,
      consent: form.consent,
    })
    add.open = false
    await load()
  } catch (err) {
    add.error = apiErrorMessage(err, 'Gagal mendaftarkan anak.')
    if (err?.response?.data?.errors?.trial_slot_id) loadSlots() // slot mungkin baru saja penuh
  } finally {
    add.saving = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-wrap items-end justify-between gap-space-sm py-space-xl">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Portal Orang Tua</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Halo, {{ auth.firstName }}</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Pantau trial, pendaftaran, dan pembayaran anak Anda.</p>
      </div>
      <button class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm active:scale-95" @click="openAdd">
        Tambah Anak
      </button>
    </div>

    <p v-if="error" class="font-body-md text-body-md text-error">{{ error }}</p>
    <div v-if="loading" class="grid gap-space-sm sm:grid-cols-2">
      <div v-for="i in 2" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!children.length && !error" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center shadow-sm">
      <p class="font-body-md text-body-md text-on-surface-variant">Belum ada anak terdaftar. Daftarkan anak untuk mengikuti kelas trial.</p>
    </div>

    <div v-else class="grid gap-space-sm sm:grid-cols-2">
      <RouterLink
        v-for="c in children"
        :key="c.id"
        :to="`/portal/anak/${c.id}`"
        class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-all hover:shadow-md"
      >
        <div class="flex items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ c.name }}</h3>
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ LEVELS[c.education_level] }} • {{ c.origin_school }}</span>
          </div>
          <span class="shrink-0 rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="STAGES[c.stage]?.cls">{{ STAGES[c.stage]?.label || c.stage }}</span>
        </div>
        <span v-if="c.program_name" class="font-body-sm text-body-sm text-on-surface-variant">
          {{ c.program_name }}<template v-if="c.branch_name"> • {{ c.branch_name }}</template><template v-if="c.stage === 'trial_terjadwal'"> • {{ formatStamp(c.trial_at) }}</template>
        </span>
        <span class="font-label-lg text-label-lg font-semibold text-primary">{{ STAGES[c.stage]?.action }} →</span>
      </RouterLink>
    </div>

    <div v-if="add.open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="add.open = false">
      <div class="flex max-h-[90vh] w-full max-w-md flex-col gap-space-md overflow-y-auto rounded-2xl bg-white p-space-lg shadow-xl">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Daftarkan Anak untuk Trial</h2>
        <label class="flex flex-col gap-1"><span :class="labelCls">Nama anak</span><input v-model="form.name" :class="inputCls" /></label>
        <label class="flex flex-col gap-1">
          <span :class="labelCls">Jenjang</span>
          <select v-model="form.education_level" :class="inputCls">
            <option value="" disabled>Pilih jenjang…</option>
            <option v-for="(label, key) in LEVELS" :key="key" :value="key">{{ label }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1"><span :class="labelCls">Asal sekolah</span><input v-model="form.origin_school" :class="inputCls" /></label>
        <label class="flex flex-col gap-1">
          <span :class="labelCls">Program</span>
          <select v-model="form.program_id" :class="inputCls" @change="loadSlots">
            <option value="" disabled>Pilih program…</option>
            <option v-for="p in add.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1">
          <span :class="labelCls">Jadwal trial</span>
          <select v-model="form.trial_slot_id" :class="inputCls" :disabled="!form.program_id || add.loadingSlots">
            <option value="" disabled>{{ add.loadingSlots ? 'Memuat…' : 'Pilih jadwal…' }}</option>
            <option v-for="s in add.slots" :key="s.id" :value="s.id">{{ formatStamp(s.starts_at) }} • {{ s.branch.name }} (sisa {{ s.remaining }})</option>
          </select>
          <span v-if="form.program_id && !add.loadingSlots && !add.slots.length" class="font-body-sm text-body-sm text-on-surface-variant">Belum ada jadwal tersedia untuk program ini.</span>
        </label>
        <label class="flex items-start gap-space-xs font-body-sm text-body-sm text-on-surface">
          <input v-model="form.consent" type="checkbox" class="mt-1" />
          <span>Saya menyetujui penggunaan data pribadi anak untuk keperluan trial dan pendaftaran.</span>
        </label>
        <p v-if="add.error" class="font-body-sm text-body-sm text-error">{{ add.error }}</p>
        <div class="flex justify-end gap-space-xs">
          <button class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg" @click="add.open = false">Batal</button>
          <button class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary disabled:opacity-60" :disabled="add.saving" @click="submitAdd">Daftarkan</button>
        </div>
      </div>
    </div>
  </div>
</template>
