<script setup>
// Minta tautan reset password lewat email (ORT-01, SYS-01).
// Backend selalu menjawab sama untuk email terdaftar maupun tidak, jadi halaman ini juga tidak membedakannya.
import { ref } from 'vue'
import api from '@/lib/api'
import { whatsappLink } from '@/lib/contact'
import PublicHeader from './PublicHeader.vue'

const email = ref('')
const loading = ref(false)
const sent = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/forgot-password', { email: email.value.trim() })
    sent.value = true
  } catch (e) {
    const res = e.response
    if (res?.status === 429) error.value = 'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.'
    else if (res?.status === 422) error.value = 'Format email belum benar. Contoh: nama@email.com'
    else error.value = 'Permintaan belum berhasil. Periksa koneksi lalu coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-landing">
    <PublicHeader hide-cta />

    <main class="mx-auto max-w-md px-4 py-10">
      <div v-if="sent" class="flex flex-col items-center gap-4 rounded-3xl bg-white p-8 text-center shadow-md">
        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
          <span class="material-symbols-outlined text-4xl" aria-hidden="true">mark_email_read</span>
        </div>
        <h1 class="text-2xl font-extrabold text-brandNavy">Cek email Anda</h1>
        <p class="text-base text-slate-600">
          Jika <strong class="[overflow-wrap:anywhere]">{{ email }}</strong> terdaftar, tautan untuk mengatur ulang password sudah kami kirim.
          Tautan berlaku 60 menit. Belum masuk? Cek juga folder spam.
        </p>
        <RouterLink
          :to="{ name: 'login' }"
          class="flex min-h-[48px] w-full items-center justify-center rounded-full bg-brandNavy px-6 py-3 text-center text-base font-bold text-white hover:bg-brandBlue"
        >
          Kembali ke halaman masuk
        </RouterLink>
        <button
          type="button"
          class="min-h-[44px] text-base font-semibold text-brandBlue hover:underline"
          @click="sent = false"
        >
          Email salah? Kirim ulang
        </button>
      </div>

      <div v-else class="rounded-3xl bg-white p-6 shadow-md sm:p-8">
        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
          <span class="material-symbols-outlined text-3xl" aria-hidden="true">lock_reset</span>
        </div>
        <h1 class="text-2xl font-extrabold text-brandNavy">Lupa password?</h1>
        <p class="mt-2 text-base text-slate-600">
          Tenang, hal ini sering terjadi. Masukkan email akun Anda dan kami kirimkan tautan untuk membuat password baru.
        </p>

        <form class="mt-6 flex flex-col gap-4" novalidate @submit.prevent="submit">
          <label class="flex flex-col gap-1.5">
            <span class="text-sm font-semibold text-slate-700">Email</span>
            <input
              v-model="email"
              type="email"
              required
              autocomplete="email"
              inputmode="email"
              placeholder="nama@email.com"
              class="min-h-[48px] rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-800 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20"
            />
          </label>

          <p v-if="error" class="rounded-xl bg-red-50 px-4 py-3 text-base text-red-700" role="alert">{{ error }}</p>

          <button
            type="submit"
            :disabled="loading || !email.trim()"
            class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-full bg-brandSun px-6 text-base font-bold text-brandDeepNavy shadow-lg hover:bg-brandSunDark disabled:cursor-not-allowed disabled:opacity-60"
          >
            {{ loading ? 'Mengirim…' : 'Kirim tautan reset' }}
          </button>
        </form>

        <div class="mt-4 flex flex-col items-center gap-1 text-base">
          <RouterLink :to="{ name: 'login' }" class="flex min-h-[44px] items-center font-semibold text-brandBlue hover:underline">
            Kembali ke halaman masuk
          </RouterLink>
          <a
            :href="whatsappLink('Halo admin AfterSchola, saya tidak bisa mengatur ulang password akun saya.')"
            target="_blank"
            rel="noopener"
            class="flex min-h-[44px] items-center text-slate-500 hover:underline"
          >
            Tidak ada email? Hubungi admin via WhatsApp
          </a>
        </div>
      </div>
    </main>
  </div>
</template>
