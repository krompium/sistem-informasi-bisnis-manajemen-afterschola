<script setup>
// Form publik pendaftaran trial (Modul 2 — Pendaftaran Trial & Leads).
// Tanpa login. Mengirim ke POST /api/leads (endpoint publik, lihat routes/api.php).
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { whatsappLink } from '@/lib/contact'
import PublicHeader from './PublicHeader.vue'

const route = useRoute()
const loadingPrograms = ref(true)
const programs = ref([])

const saving = ref(false)
const submitted = ref(false)
const formError = ref('')
const fieldErrors = ref({})

const form = reactive({
  name: '',
  education_level: '',
  origin_school: '',
  parent_email: '',
  program_id: '',
  program_trial_schedule_id: '',
})

const selectedProgram = computed(() =>
  programs.value.find((p) => p.id === Number(form.program_id)) || null,
)

const hariLabel = {
  senin: 'Senin',
  selasa: 'Selasa',
  rabu: 'Rabu',
  kamis: 'Kamis',
  jumat: 'Jumat',
  sabtu: 'Sabtu',
  minggu: 'Minggu',
}

async function loadPrograms() {
  loadingPrograms.value = true
  try {
    const { data } = await api.get('/public/programs')
    programs.value = data.data ?? data
    // Datang dari halaman program (/program/:id): program langsung terpilih.
    const wanted = Number(route.query.program)
    if (programs.value.some((p) => p.id === wanted)) form.program_id = wanted
  } catch (err) {
    // Non-fatal: form tetap bisa dikirim tanpa pilih program.
    programs.value = []
  } finally {
    loadingPrograms.value = false
  }
}

function onProgramChange() {
  form.program_trial_schedule_id = ''
}

async function submit() {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  try {
    await api.post('/leads', {
      name: form.name,
      education_level: form.education_level,
      origin_school: form.origin_school,
      parent_email: form.parent_email,
      program_id: form.program_id || null,
      program_trial_schedule_id: form.program_trial_schedule_id || null,
    })
    submitted.value = true
  } catch (err) {
    const res = err?.response
    if (res?.status === 422) {
      fieldErrors.value = res.data?.errors ?? {}
      formError.value = 'Periksa lagi data yang diisi.'
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
</script>

<template>
  <div class="flex min-h-screen w-full flex-col bg-white">
    <PublicHeader hide-cta />

    <main class="mx-auto flex w-full max-w-lg flex-1 flex-col justify-center px-6 pb-16 sm:px-0">
      <!-- Sukses -->
      <div v-if="submitted" class="flex flex-col items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-xl text-center shadow-sm">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#D1FAE5] text-[#065F46]">
          <span class="material-symbols-outlined text-[30px]">celebration</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">Pendaftaran Terkirim!</h1>
        <p class="font-body-md text-base text-on-surface-variant">
          Terima kasih! Tim kami akan menghubungi orang tua/wali melalui email yang didaftarkan untuk
          info jadwal trial selanjutnya.
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
          <h1 class="font-headline-xl text-3xl font-extrabold tracking-tight text-slate-900">
            Daftar Trial Kelas
          </h1>
          <p class="mt-2 font-body-md text-sm text-slate-500">
            Isi data di bawah untuk mencoba kelas After Schola secara gratis.
          </p>
        </div>

        <p
          v-if="formError"
          class="flex items-center gap-2 rounded-lg bg-error-container px-3 py-2 text-sm text-on-error-container"
        >
          <span class="material-symbols-outlined text-[18px]">error</span>
          {{ formError }}
        </p>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-sm font-semibold text-slate-700">Nama Calon Siswa</span>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Nama lengkap"
            class="rounded-lg border border-slate-300 bg-white min-h-[48px] px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <span v-if="fieldErrors.name" class="font-body-sm text-sm text-error">{{ fieldErrors.name[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-sm font-semibold text-slate-700">Jenjang</span>
          <select
            v-model="form.education_level"
            required
            class="rounded-lg border border-slate-300 bg-white min-h-[48px] px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="" disabled>Pilih jenjang…</option>
            <option value="sd">SD</option>
            <option value="smp">SMP</option>
            <option value="sma">SMA</option>
          </select>
          <span v-if="fieldErrors.education_level" class="font-body-sm text-sm text-error">{{ fieldErrors.education_level[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-sm font-semibold text-slate-700">Asal Sekolah</span>
          <input
            v-model="form.origin_school"
            type="text"
            required
            placeholder="mis. SMP Negeri 5 Bandung"
            class="rounded-lg border border-slate-300 bg-white min-h-[48px] px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <span v-if="fieldErrors.origin_school" class="font-body-sm text-sm text-error">{{ fieldErrors.origin_school[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-sm font-semibold text-slate-700">Email Orang Tua/Wali</span>
          <input
            v-model="form.parent_email"
            type="email"
            required
            autocomplete="email"
            placeholder="nama@email.com"
            class="rounded-lg border border-slate-300 bg-white min-h-[48px] px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
          />
          <span v-if="fieldErrors.parent_email" class="font-body-sm text-sm text-error">{{ fieldErrors.parent_email[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-sm font-semibold text-slate-700">
            Pilih Mata Pelajaran <span class="font-normal text-slate-500">(opsional)</span>
          </span>
          <select
            v-model="form.program_id"
            :disabled="loadingPrograms"
            class="rounded-lg border border-slate-300 bg-white min-h-[48px] px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 disabled:bg-slate-50"
            @change="onProgramChange"
          >
            <option value="">{{ loadingPrograms ? 'Memuat…' : 'Belum menentukan' }}</option>
            <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </label>

        <label v-if="selectedProgram?.jadwal_trial?.length" class="flex flex-col gap-1">
          <span class="font-label-md text-sm font-semibold text-slate-700">
            Pilih Jadwal Trial <span class="font-normal text-slate-500">(opsional)</span>
          </span>
          <select
            v-model="form.program_trial_schedule_id"
            class="rounded-lg border border-slate-300 bg-white min-h-[48px] px-4 py-3 text-base text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
          >
            <option value="">Belum menentukan</option>
            <option v-for="s in selectedProgram.jadwal_trial" :key="s.id" :value="s.id">
              {{ hariLabel[s.hari] || s.hari }}, {{ s.jam_mulai }}–{{ s.jam_selesai }}
            </option>
          </select>
        </label>

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
