<script setup>
// Beranda Orang Tua (ORT-02, ORT-03): daftar anak, status tiap anak, tindakan yang menunggu, tambah anak untuk trial.
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { formatStamp } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { ACTION_LABELS, actionTo, errMsg, inputCls, labelCls, toneOf } from './portalCommon'

const auth = useAuthStore()
const loading = ref(true)
const children = ref([])
const loadError = ref('')

const adding = ref(false)
const saving = ref(false)
const formError = ref('')
const programs = ref([])
const slots = ref([])
const programId = ref('')
const form = reactive({ name: '', education_level: 'sd', origin_school: '', trial_slot_id: '', consent: false })

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/parent/children')
    children.value = data.data
  } catch (err) {
    loadError.value = errMsg(err, 'Gagal memuat data anak.')
  } finally {
    loading.value = false
  }
}

async function openForm() {
  adding.value = true
  if (programs.value.length) return
  try {
    const { data } = await api.get('/public/programs')
    programs.value = data.data ?? data
  } catch (err) {
    formError.value = 'Daftar program gagal dimuat.'
  }
}

async function loadSlots() {
  form.trial_slot_id = ''
  slots.value = []
  if (!programId.value) return
  try {
    const { data } = await api.get('/public/trial-slots', { params: { program_id: programId.value } })
    slots.value = data.data ?? []
  } catch (err) {
    slots.value = []
  }
}

async function addChild() {
  saving.value = true
  formError.value = ''
  try {
    await api.post('/parent/children', { ...form })
    Object.assign(form, { name: '', education_level: 'sd', origin_school: '', trial_slot_id: '', consent: false })
    programId.value = ''
    slots.value = []
    adding.value = false
    await load()
  } catch (err) {
    formError.value = errMsg(err, 'Gagal menambah anak.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Portal Orang Tua</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Halo, {{ auth.user?.name }}</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Pantau perkembangan pendaftaran dan belajar anak Anda di sini.</p>
    </div>

    <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else>
      <div v-if="!children.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
        Belum ada anak terdaftar. Tambahkan anak untuk mengikuti kelas trial.
      </div>

      <div v-else class="flex flex-col gap-space-sm">
        <div v-for="child in children" :key="child.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-space-sm">
            <div class="flex flex-col">
              <RouterLink :to="`/portal/anak/${child.id}`" class="font-headline-sm text-headline-sm font-bold text-on-surface hover:underline">{{ child.name }}</RouterLink>
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                {{ child.program?.name }} • {{ child.branch?.name }} • {{ child.education_level?.toUpperCase() }}
              </span>
            </div>
            <span :class="['rounded-full px-3 py-1 font-label-sm text-label-sm font-semibold', toneOf(child.stage)]">{{ child.stage_label }}</span>
          </div>
          <p v-if="child.stage === 'trial_terjadwal' && child.trial_starts_at" class="font-body-md text-body-md text-on-surface">
            Trial: <b>{{ formatStamp(child.trial_starts_at) }}</b>
          </p>
          <div v-if="child.next_action">
            <RouterLink :to="actionTo(child)" class="inline-block rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95">
              {{ ACTION_LABELS[child.next_action] }}
            </RouterLink>
          </div>
        </div>
      </div>

      <div class="mt-space-lg">
        <button v-if="!adding" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low" @click="openForm">
          + Tambah anak untuk trial
        </button>

        <form v-else class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm" @submit.prevent="addChild">
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Tambah anak untuk trial</h2>
          <p class="font-body-sm text-body-sm text-on-surface-variant">Data Anda sebagai orang tua diambil dari akun, tidak perlu diisi ulang.</p>

          <div class="flex flex-col gap-1">
            <label :class="labelCls" for="child-name">Nama anak</label>
            <input id="child-name" v-model="form.name" required :class="inputCls" />
          </div>
          <div class="grid gap-space-sm sm:grid-cols-2">
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="child-level">Jenjang</label>
              <select id="child-level" v-model="form.education_level" :class="inputCls">
                <option value="sd">SD</option>
                <option value="smp">SMP</option>
                <option value="sma">SMA</option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="child-school">Asal sekolah</label>
              <input id="child-school" v-model="form.origin_school" required :class="inputCls" />
            </div>
          </div>
          <div class="grid gap-space-sm sm:grid-cols-2">
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="child-program">Program</label>
              <select id="child-program" v-model="programId" required :class="inputCls" @change="loadSlots">
                <option value="" disabled>Pilih program</option>
                <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="child-slot">Jadwal trial</label>
              <select id="child-slot" v-model="form.trial_slot_id" required :disabled="!slots.length" :class="inputCls">
                <option value="" disabled>{{ programId && !slots.length ? 'Belum ada jadwal tersedia' : 'Pilih jadwal' }}</option>
                <option v-for="s in slots" :key="s.id" :value="s.id">{{ s.branch.name }} • {{ formatStamp(s.starts_at) }} (sisa {{ s.remaining }})</option>
              </select>
            </div>
          </div>
          <label class="flex items-start gap-space-xs font-body-sm text-body-sm text-on-surface">
            <input v-model="form.consent" type="checkbox" class="mt-1" />
            Saya menyetujui penggunaan data pribadi anak untuk keperluan pendaftaran dan pembelajaran.
          </label>

          <p v-if="formError" class="rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-on-error-container">{{ formError }}</p>
          <div class="flex gap-space-xs">
            <button type="submit" :disabled="saving" class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              {{ saving ? 'Menyimpan…' : 'Daftarkan trial' }}
            </button>
            <button type="button" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container-low" @click="adding = false">Batal</button>
          </div>
        </form>
      </div>
    </template>
  </div>
</template>
