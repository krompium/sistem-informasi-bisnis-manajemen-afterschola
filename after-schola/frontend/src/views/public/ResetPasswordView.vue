<script setup>
// Buat password baru dari tautan email: /reset-password?token=...&email=... (ORT-01, SYS-01).
// Dipakai email pendaftaran trial (akun orang tua baru) dan alur lupa password (/lupa-password).
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/lib/api'
import { toast } from '@/lib/feedback'
import PublicHeader from './PublicHeader.vue'

const route = useRoute()
const router = useRouter()

const token = computed(() => String(route.query.token ?? ''))
const email = computed(() => String(route.query.email ?? ''))

const password = ref('')
const confirmation = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')
const linkInvalid = ref(false) // token ditolak server: kedaluwarsa atau sudah dipakai

const tooShort = computed(() => password.value.length > 0 && password.value.length < 8)
const mismatch = computed(() => confirmation.value.length > 0 && confirmation.value !== password.value)
const canSubmit = computed(() => password.value.length >= 8 && confirmation.value === password.value && !loading.value)

async function submit() {
  if (!canSubmit.value) return
  error.value = ''
  loading.value = true
  try {
    const { data } = await api.post('/reset-password', {
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: confirmation.value,
    })
    toast.success(data.message)
    router.push({ name: 'login' })
  } catch (e) {
    const res = e.response
    if (res?.status === 422 && res.data?.errors?.email) linkInvalid.value = true
    else if (res?.status === 422) error.value = res.data?.errors?.password?.[0] ?? 'Password belum memenuhi syarat.'
    else if (res?.status === 429) error.value = 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'
    else error.value = 'Password belum berhasil diubah. Periksa koneksi lalu coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-landing">
    <PublicHeader hide-cta />

    <main class="mx-auto max-w-md px-4 py-10">
      <!-- Tautan tidak lengkap atau ditolak server -->
      <div v-if="!token || !email || linkInvalid" class="flex flex-col items-center gap-4 rounded-3xl bg-white p-8 text-center shadow-md">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-red-100 text-red-600">
          <span class="material-symbols-outlined text-4xl" aria-hidden="true">link_off</span>
        </div>
        <h1 class="text-2xl font-extrabold text-brandNavy">Tautan tidak berlaku</h1>
        <p class="text-base text-slate-600">
          Tautan reset password ini tidak lengkap, sudah kedaluwarsa, atau sudah pernah dipakai. Minta tautan baru ya.
        </p>
        <RouterLink
          :to="{ name: 'public.forgot-password' }"
          class="flex min-h-[48px] w-full items-center justify-center rounded-full bg-brandSun px-6 text-base font-bold text-brandDeepNavy hover:bg-brandSunDark"
        >
          Minta tautan baru
        </RouterLink>
      </div>

      <div v-else class="rounded-3xl bg-white p-6 shadow-md sm:p-8">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
          <span class="material-symbols-outlined text-3xl" aria-hidden="true">key</span>
        </div>
        <h1 class="text-2xl font-extrabold text-brandNavy">Buat password baru</h1>
        <p class="mt-2 [overflow-wrap:anywhere] text-base text-slate-600">Untuk akun <strong>{{ email }}</strong></p>

        <form class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submit">
          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-semibold text-slate-700">Password baru</span>
            <div class="relative">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="new-password"
                class="min-h-[48px] w-full rounded-xl border border-slate-300 py-3 pl-4 pr-12 text-base text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-slate-400 hover:text-slate-600"
                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                @click="showPassword = !showPassword"
              >
                <span class="material-symbols-outlined" aria-hidden="true">{{ showPassword ? 'visibility_off' : 'visibility' }}</span>
              </button>
            </div>
            <span class="text-sm" :class="tooShort ? 'text-red-600' : 'text-slate-500'">Minimal 8 karakter.</span>
          </label>

          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-semibold text-slate-700">Ulangi password baru</span>
            <input
              v-model="confirmation"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="new-password"
              class="min-h-[48px] rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
            />
            <span v-if="mismatch" class="text-sm text-red-600">Password belum sama.</span>
          </label>

          <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-base text-red-700" role="alert">{{ error }}</p>

          <button
            type="submit"
            :disabled="!canSubmit"
            class="flex min-h-[52px] w-full items-center justify-center rounded-full bg-brandSun px-6 text-base font-bold text-brandDeepNavy shadow-lg hover:bg-brandSunDark disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loading ? 'Menyimpan…' : 'Simpan password baru' }}
          </button>
        </form>
      </div>
    </main>
  </div>
</template>
