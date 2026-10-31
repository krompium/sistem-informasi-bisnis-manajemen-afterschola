<script setup>
// Form publik pendaftaran trial (Modul 2, PUB-03..05). Tanpa login.
// POST /api/public/trial-registrations: memesan slot, membuat akun orang tua, dan mengirim email
// konfirmasi berisi tautan atur password. Slot dan program dipilih dari /api/public/*.
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { formatStamp } from '@/lib/format'
import { whatsappLink } from '@/lib/contact'
import PublicHeader from './PublicHeader.vue'

const route = useRoute()
const loadingPrograms = ref(true)
const programs = ref([])
const slots = ref([])
const loadingSlots = ref(false)

const saving = ref(false)
const result = ref(null) // data lead dari respons 201; menandai pendaftaran terkirim
const formError = ref('')
const fieldErrors = ref({})

const form = reactive({
  parent_name: '',
  parent_email: '',
  parent_phone: '',
  name: '',
  education_level: '',
  origin_school: '',
  program_id: '',
  trial_slot_id: '',
  consent: false,
})

async function loadPrograms() {
  loadingPrograms.value = true
  try {
    const { data } = await api.get('/public/programs')
    programs.value = data.data ?? data
    // Datang dari halaman program (/program/:id): program langsung terpilih.
    const wanted = Number(route.query.program)
    if (programs.value.some((p) => p.id === wanted)) {
      form.program_id = wanted
      await loadSlots()
    }
  } catch (err) {
    programs.value = []
    formError.value = 'Daftar program gagal dimuat. Muat ulang halaman ini.'
  } finally {
    loadingPrograms.value = false
  }
}

// Slot yang masih tersedia untuk program terpilih (kapasitas dihitung server).
async function loadSlots() {
  form.trial_slot_id = ''
  slots.value = []
  if (!form.program_id) return
  loadingSlots.value = true
  try {
    const { data } = await api.get('/public/trial-slots', { params: { program_id: form.program_id } })
    slots.value = data.data ?? []
  } catch (err) {
    slots.value = []
  } finally {
    loadingSlots.value = false
  }
}

async function submit() {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  try {
    const { data } = await api.post('/public/trial-registrations', {
      parent_name: form.parent_name,
      parent_email: form.parent_email,
      parent_phone: form.parent_phone,
      name: form.name,
      education_level: form.education_level,
      origin_school: form.origin_school,
      trial_slot_id: form.trial_slot_id,
      consent: form.consent,
    })
    result.value = data.data ?? data
  } catch (err) {
    const res = err?.response
    if (res?.status === 422) {
      fieldErrors.value = res.data?.errors ?? {}
      formError.value = 'Periksa lagi data yang diisi.'
      if (fieldErrors.value.trial_slot_id) loadSlots() // slot mungkin baru saja penuh
    } else if (res?.status === 429) {
      formError.value = 'Terlalu banyak percobaan. Coba lagi dalam beberapa menit.'
    } else {
      formError.value = 'Gagal mengirim pendaftaran. Coba lagi.'
    }
  } finally {
    saving.value = false
  }
}

onMounted(loadPrograms)

const inputCls =
  'min-h-[48px] rounded-lg border border-slate-300 bg-white px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50'
const labelCls = 'font-label-md text-sm font-semibold text-slate-700'
</script>

<template>
  <div class="flex min-h-screen w-full flex-col bg-white">
    <PublicHeader hide-cta />

    <main class="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center px-6 pb-16 pt-8 sm:px-0">
      <!-- Sukses -->
      <div v-if="result" class="flex flex-col items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-xl text-center shadow-sm">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#D1FAE5] text-[#065F46]">
          <span class="material-symbols-outlined text-[30px]">celebration</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">Pendaftaran Terkirim!</h1>
        <p v-if="result.trial_slot" class="font-body-md text-base text-on-surface">
          Trial <b>{{ result.trial_slot.program }}</b> di {{ result.trial_slot.branch }}<br />
          {{ formatStamp(result.trial_slot.starts_at) }}
        </p>
        <p class="font-body-md text-base text-on-surface-variant">
          Kami mengirim konfirmasi ke <b class="break-all">{{ result.parent_email }}</b>. Buka email tersebut dan klik
          "Atur Password" untuk mengaktifkan akun orang tua.
        </p>
        <div class="mt-2 flex w-full flex-col gap-3">
          <RouterLink
            :to="{ name: 'public.programs' }"
            class="flex min-h-[48px] items-center justify-center rounded-full bg-[#1D4ED8] px-6 text-base font-semibold text-white shadow-md hover:bg-blue-700"
          >
            Lihat program lainnya
          </RouterLink>
          <a
            :href="whatsappLink('Halo AfterSchola, saya baru mendaftar trial dan ingin bertanya.')"
            target="_blank"
            rel="noopener"
            class="flex min-h-[48px] items-center justify-center gap-2 rounded-full border border-slate-300 px-6 text-base font-semibold text-slate-700 hover:bg-slate-50"
          >
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">chat</span>
            Tanya lewat WhatsApp
          </a>
          <RouterLink to="/" class="flex min-h-[44px] items-center justify-center text-base font-semibold text-slate-500 hover:text-slate-700">
            Kembali ke beranda
          </RouterLink>
        </div>
      </div>

      <!-- Form -->
      <form v-else class="flex flex-col gap-space-md" @submit.prevent="submit">
        <div class="mb-space-sm">
          <h1 class="font-headline-xl text-3xl font-extrabold tracking-tight text-slate-900">Daftar Trial Kelas</h1>
          <p class="mt-2 font-body-md text-base text-slate-500">
            Isi data di bawah untuk mencoba kelas After Schola secara gratis.
            Sudah punya akun? <RouterLink :to="{ name: 'login' }" class="font-semibold text-blue-700 hover:underline">Masuk</RouterLink>
          </p>
        </div>

        <p v-if="formError" class="flex items-center gap-2 rounded-lg bg-error-container px-3 py-2 text-sm text-on-error-container">
          <span class="material-symbols-outlined text-[18px]">error</span>
          {{ formError }}
        </p>

        <h2 class="mt-2 font-label-md text-base font-bold text-[#0B2A5B]">Data Orang Tua/Wali</h2>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Nama Orang Tua/Wali</span>
          <input v-model="form.parent_name" type="text" required autocomplete="name" placeholder="Nama lengkap" :class="inputCls" />
          <span v-if="fieldErrors.parent_name" class="font-body-sm text-sm text-error">{{ fieldErrors.parent_name[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Email</span>
          <input v-model="form.parent_email" type="email" required autocomplete="email" placeholder="nama@email.com" :class="inputCls" />
          <span v-if="fieldErrors.parent_email" class="font-body-sm text-sm text-error">{{ fieldErrors.parent_email[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Nomor Telepon/WhatsApp</span>
          <input v-model="form.parent_phone" type="tel" required autocomplete="tel" placeholder="0812-3456-7890" :class="inputCls" />
          <span v-if="fieldErrors.parent_phone" class="font-body-sm text-sm text-error">{{ fieldErrors.parent_phone[0] }}</span>
        </label>

        <h2 class="mt-2 font-label-md text-base font-bold text-[#0B2A5B]">Data Calon Siswa</h2>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Nama Calon Siswa</span>
          <input v-model="form.name" type="text" required placeholder="Nama lengkap" :class="inputCls" />
          <span v-if="fieldErrors.name" class="font-body-sm text-sm text-error">{{ fieldErrors.name[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Jenjang</span>
          <select v-model="form.education_level" required :class="inputCls">
            <option value="" disabled>Pilih jenjang…</option>
            <option value="sd">SD</option>
            <option value="smp">SMP</option>
            <option value="sma">SMA</option>
          </select>
          <span v-if="fieldErrors.education_level" class="font-body-sm text-sm text-error">{{ fieldErrors.education_level[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Asal Sekolah</span>
          <input v-model="form.origin_school" type="text" required placeholder="mis. SMP Negeri 5 Bandung" :class="inputCls" />
          <span v-if="fieldErrors.origin_school" class="font-body-sm text-sm text-error">{{ fieldErrors.origin_school[0] }}</span>
        </label>

        <h2 class="mt-2 font-label-md text-base font-bold text-[#0B2A5B]">Jadwal Trial</h2>

        <label class="flex flex-col gap-1">
          <span :class="labelCls">Pilih Program</span>
          <select v-model="form.program_id" required :disabled="loadingPrograms" :class="inputCls" @change="loadSlots">
            <option value="" disabled>{{ loadingPrograms ? 'Memuat…' : 'Pilih program…' }}</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>

        <label v-if="form.program_id" class="flex flex-col gap-1">
          <span :class="labelCls">Pilih Jadwal</span>
          <select v-model="form.trial_slot_id" required :disabled="loadingSlots || !slots.length" :class="inputCls">
            <option value="" disabled>{{ loadingSlots ? 'Memuat…' : slots.length ? 'Pilih jadwal…' : 'Belum ada jadwal tersedia' }}</option>
            <option v-for="s in slots" :key="s.id" :value="s.id">
              {{ s.branch.name }} • {{ formatStamp(s.starts_at) }} • sisa {{ s.remaining }}
            </option>
          </select>
          <span v-if="fieldErrors.trial_slot_id" class="font-body-sm text-sm text-error">{{ fieldErrors.trial_slot_id[0] }}</span>
        </label>

        <label class="mt-2 flex min-h-[44px] items-start gap-3">
          <input v-model="form.consent" type="checkbox" class="mt-0.5 h-6 w-6 shrink-0" />
          <span class="font-body-sm text-base text-slate-600">
            Saya menyetujui penggunaan data pribadi saya dan anak saya oleh After Schola untuk keperluan
            pendaftaran dan pembelajaran.
          </span>
        </label>
        <span v-if="fieldErrors.consent" class="-mt-2 font-body-sm text-sm text-error">{{ fieldErrors.consent[0] }}</span>

        <button
          type="submit"
          :disabled="saving"
          class="mt-2 flex min-h-[52px] w-full items-center justify-center gap-2 rounded-full bg-[#1D4ED8] px-6 py-3.5 text-base font-semibold text-white shadow-md transition-all hover:bg-blue-700 disabled:opacity-60"
        >
          <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
          <span>{{ saving ? 'Mengirim…' : 'Daftar Trial' }}</span>
        </button>
      </form>
    </main>
  </div>
</template>
