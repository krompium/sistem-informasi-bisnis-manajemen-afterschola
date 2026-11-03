<script setup>
// Pendaftaran resmi (ORT-06, ORT-07): isian awal dari trial dan akun, pilih paket, simpan draft, kirim untuk verifikasi,
// dan perbaiki bila diminta revisi. Setelah diajukan tampil read-only.
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah } from '@/lib/format'
import { errMsg, inputCls, labelCls } from './portalCommon'

const route = useRoute()
const router = useRouter()
const base = `/parent/children/${route.params.id}`

const loading = ref(true)
const loadError = ref('')
const saving = ref('')
const formError = ref('')
const notice = ref('')
const meta = reactive({ status: '', editable: false, revision_note: null })
const programs = ref([])
const form = reactive({
  student_name: '', birth_date: '', origin_school: '', origin_class: '', program_id: '',
  parent_name: '', parent_phone: '', parent_email: '', parent_address: '',
})

const STATUS_LABELS = {
  belum_diisi: 'Belum diisi',
  draft: 'Draft',
  perlu_revisi: 'Perlu diperbaiki',
  menunggu_verifikasi: 'Menunggu verifikasi',
  terverifikasi: 'Terverifikasi',
}
const chosenProgram = computed(() => programs.value.find((p) => p.id === form.program_id))

function apply(payload) {
  const { data, options } = payload
  meta.status = data.status
  meta.editable = data.editable
  meta.revision_note = data.revision_note
  for (const key of Object.keys(form)) form[key] = data.form[key] ?? ''
  if (options) programs.value = options.programs
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get(`${base}/registration`)
    apply(data)
  } catch (err) {
    loadError.value = errMsg(err, 'Formulir pendaftaran belum dapat dibuka.')
  } finally {
    loading.value = false
  }
}

const body = () => ({ ...form, birth_date: form.birth_date || null, program_id: form.program_id || null })

async function save() {
  const { data } = await api.put(`${base}/registration`, body())
  apply(data)
}

async function saveDraft() {
  saving.value = 'draft'
  formError.value = ''
  notice.value = ''
  try {
    await save()
    notice.value = 'Draft tersimpan.'
  } catch (err) {
    formError.value = errMsg(err, 'Gagal menyimpan.')
  } finally {
    saving.value = ''
  }
}

async function submit() {
  saving.value = 'submit'
  formError.value = ''
  notice.value = ''
  try {
    await save() // simpan isian terakhir lebih dulu, baru diajukan
    await api.post(`${base}/registration/submit`)
    router.push('/portal')
  } catch (err) {
    formError.value = errMsg(err, 'Gagal mengirim pendaftaran.')
  } finally {
    saving.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="pt-space-lg">
      <RouterLink :to="`/portal/anak/${route.params.id}`" class="font-label-lg text-label-lg text-secondary hover:underline">← Kembali</RouterLink>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-space-sm py-space-lg">
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Pendaftaran resmi</h1>
      <span v-if="meta.status" class="rounded-full bg-surface-container-high px-3 py-1 font-label-sm text-label-sm font-semibold text-on-surface">
        {{ STATUS_LABELS[meta.status] || meta.status }}
      </span>
    </div>

    <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else>
      <div v-if="meta.status === 'perlu_revisi' && meta.revision_note" class="mb-space-md rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">
        <b>Catatan dari admin:</b> {{ meta.revision_note }}
      </div>
      <p v-if="!meta.editable" class="mb-space-md rounded-xl bg-surface-container-low px-space-md py-space-sm font-body-md text-on-surface-variant">
        Pendaftaran sudah diajukan dan tidak dapat diubah. Anda akan mendapat kabar bila perlu perbaikan atau setelah diverifikasi.
      </p>

      <form class="flex flex-col gap-space-md" @submit.prevent="submit">
        <fieldset :disabled="!meta.editable" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <legend class="font-headline-sm text-headline-sm font-bold text-on-surface">Data anak</legend>
          <div class="flex flex-col gap-1">
            <label :class="labelCls" for="reg-student">Nama lengkap anak</label>
            <input id="reg-student" v-model="form.student_name" required :class="inputCls" />
          </div>
          <div class="grid gap-space-sm sm:grid-cols-3">
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="reg-birth">Tanggal lahir</label>
              <input id="reg-birth" v-model="form.birth_date" type="date" :class="inputCls" />
            </div>
            <div class="flex flex-col gap-1 sm:col-span-1">
              <label :class="labelCls" for="reg-school">Asal sekolah</label>
              <input id="reg-school" v-model="form.origin_school" :class="inputCls" />
            </div>
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="reg-class">Kelas</label>
              <input id="reg-class" v-model="form.origin_class" :class="inputCls" />
            </div>
          </div>
        </fieldset>

        <fieldset :disabled="!meta.editable" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <legend class="font-headline-sm text-headline-sm font-bold text-on-surface">Data orang tua</legend>
          <div class="grid gap-space-sm sm:grid-cols-2">
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="reg-parent">Nama orang tua</label>
              <input id="reg-parent" v-model="form.parent_name" required :class="inputCls" />
            </div>
            <div class="flex flex-col gap-1">
              <label :class="labelCls" for="reg-phone">Nomor telepon</label>
              <input id="reg-phone" v-model="form.parent_phone" required inputmode="tel" :class="inputCls" />
            </div>
          </div>
          <div class="flex flex-col gap-1">
            <label :class="labelCls" for="reg-email">Email</label>
            <input id="reg-email" v-model="form.parent_email" type="email" :class="inputCls" />
          </div>
          <div class="flex flex-col gap-1">
            <label :class="labelCls" for="reg-address">Alamat</label>
            <textarea id="reg-address" v-model="form.parent_address" rows="2" :class="inputCls"></textarea>
          </div>
        </fieldset>

        <fieldset :disabled="!meta.editable" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <legend class="font-headline-sm text-headline-sm font-bold text-on-surface">Pilihan paket</legend>
          <div class="flex flex-col gap-1">
            <label :class="labelCls" for="reg-program">Program</label>
            <select id="reg-program" v-model="form.program_id" :class="inputCls">
              <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }} ({{ p.jenjang }})</option>
            </select>
          </div>
          <p v-if="chosenProgram" class="font-body-md text-body-md text-on-surface">Biaya: <b>{{ formatRupiah(chosenProgram.price) }}</b></p>
        </fieldset>

        <p v-if="formError" class="rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-on-error-container">{{ formError }}</p>
        <p v-if="notice" class="rounded-lg bg-primary-fixed px-space-sm py-2 font-body-sm text-primary">{{ notice }}</p>

        <div v-if="meta.editable" class="flex flex-wrap gap-space-xs">
          <button type="submit" :disabled="!!saving" class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
            {{ saving === 'submit' ? 'Mengirim…' : 'Kirim pendaftaran' }}
          </button>
          <button type="button" :disabled="!!saving" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low disabled:opacity-60" @click="saveDraft">
            {{ saving === 'draft' ? 'Menyimpan…' : 'Simpan draft' }}
          </button>
        </div>
      </form>
    </template>
  </div>
</template>
