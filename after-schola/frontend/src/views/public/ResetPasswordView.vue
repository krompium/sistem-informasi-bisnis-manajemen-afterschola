<script setup>
// Atur / reset password lewat tautan email (token + email di query). Dipakai email pendaftaran trial
// (akun orang tua baru) dan alur lupa password. POST /api/reset-password.
import { reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'

const route = useRoute()
const token = String(route.query.token ?? '')
const email = String(route.query.email ?? '')

const form = reactive({ password: '', password_confirmation: '' })
const saving = ref(false)
const done = ref(false)
const formError = ref('')
const fieldErrors = ref({})

async function submit() {
  saving.value = true
  formError.value = ''
  fieldErrors.value = {}
  try {
    await api.post('/reset-password', { token, email, ...form })
    done.value = true
  } catch (err) {
    const res = err?.response
    if (res?.status === 422) {
      fieldErrors.value = res.data?.errors ?? {}
      formError.value = res.data?.message || 'Tautan tidak valid atau sudah kedaluwarsa.'
    } else {
      formError.value = 'Gagal menyimpan password. Coba lagi.'
    }
  } finally {
    saving.value = false
  }
}

const inputCls =
  'rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100'
</script>

<template>
  <div class="flex min-h-screen w-full flex-col bg-white">
    <header class="flex items-center gap-2.5 px-6 py-5 sm:px-10">
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-container">
        <span class="material-symbols-outlined fill text-[20px] text-white">school</span>
      </div>
      <span class="text-[15px] font-bold tracking-tight text-[#0B2A5B]">AFTER SCHOLA</span>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center px-6 pb-16 sm:px-0">
      <div v-if="done" class="flex flex-col items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-xl text-center shadow-sm">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#D1FAE5] text-[#065F46]">
          <span class="material-symbols-outlined text-[30px]">check_circle</span>
        </div>
        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">Password Tersimpan</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Silakan masuk dengan password baru Anda.</p>
        <RouterLink :to="{ name: 'login' }" class="rounded-full bg-[#1D4ED8] px-6 py-3 text-sm font-semibold text-white shadow-md hover:bg-blue-700">Masuk</RouterLink>
      </div>

      <form v-else class="flex flex-col gap-space-md" @submit.prevent="submit">
        <div>
          <h1 class="font-headline-xl text-3xl font-extrabold tracking-tight text-slate-900">Atur Password</h1>
          <p class="mt-2 font-body-md text-sm text-slate-500">
            Buat password baru<template v-if="email"> untuk <b>{{ email }}</b></template>.
          </p>
        </div>

        <p v-if="!token || !email" class="rounded-lg bg-error-container px-3 py-2 text-sm text-on-error-container">
          Tautan tidak lengkap. Buka kembali tautan dari email Anda.
        </p>
        <p v-else-if="formError" class="flex items-center gap-2 rounded-lg bg-error-container px-3 py-2 text-sm text-on-error-container">
          <span class="material-symbols-outlined text-[18px]">error</span>
          {{ formError }}
        </p>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-xs font-semibold uppercase tracking-wider text-slate-700">Password Baru</span>
          <input v-model="form.password" type="password" required minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter" :class="inputCls" />
          <span v-if="fieldErrors.password" class="font-body-sm text-xs text-error">{{ fieldErrors.password[0] }}</span>
        </label>

        <label class="flex flex-col gap-1">
          <span class="font-label-md text-xs font-semibold uppercase tracking-wider text-slate-700">Ulangi Password</span>
          <input v-model="form.password_confirmation" type="password" required minlength="8" autocomplete="new-password" :class="inputCls" />
        </label>

        <button
          type="submit"
          :disabled="saving || !token || !email"
          class="mt-2 flex w-full items-center justify-center gap-2 rounded-full bg-[#1D4ED8] px-6 py-3.5 text-sm font-semibold text-white shadow-md transition-all hover:bg-blue-700 disabled:opacity-60"
        >
          <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
          <span>{{ saving ? 'Menyimpan…' : 'Simpan Password' }}</span>
        </button>
      </form>
    </main>
  </div>
</template>
