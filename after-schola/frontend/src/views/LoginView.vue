<script setup>
// Login split-screen mengikuti design system Stitch "Remix":
// panel kiri navy #0B2A5B (branding), panel kanan form putih (royal blue CTA).
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const remember = ref(false)
const showPassword = ref(false)
const errorMessage = ref('')

// Pola ikon dekoratif pada panel kiri (edukasi + teknologi), opacity rendah.
const decorIcons = [
  'code', 'smart_toy', 'school', 'lightbulb', 'memory', 'settings',
  'terminal', 'laptop_mac', 'science', 'psychology', 'hub', 'biotech',
  'precision_manufacturing', 'menu_book', 'developer_board', 'calculate',
  'deployed_code', 'draw', 'code', 'smart_toy', 'school', 'lightbulb',
  'memory', 'settings', 'terminal', 'laptop_mac', 'science', 'psychology',
  'hub', 'biotech', 'precision_manufacturing', 'menu_book', 'developer_board',
  'calculate', 'deployed_code', 'draw',
]

async function handleSubmit() {
  errorMessage.value = ''
  try {
    await auth.login(email.value, password.value)
    const redirect =
      typeof route.query.redirect === 'string' ? route.query.redirect : auth.homeRoute
    router.push(redirect)
  } catch (err) {
    const res = err?.response
    if (res?.status === 422) {
      const errors = res.data?.errors ?? {}
      errorMessage.value = errors.email?.[0] || res.data?.message || 'Email atau kata sandi salah.'
    } else if (res?.status === 401) {
      errorMessage.value = 'Email atau kata sandi salah.'
    } else {
      errorMessage.value = 'Tidak dapat terhubung ke server. Coba lagi.'
    }
  }
}
</script>

<template>
  <div class="flex h-screen min-h-screen w-full flex-col overflow-hidden bg-white md:flex-row">
    <!-- ================= PANEL KIRI (branding, navy) ================= -->
    <div
      class="relative hidden select-none flex-col justify-between overflow-hidden bg-[#0B2A5B] p-8 text-white md:flex md:w-[42%] lg:w-[45%] lg:p-12"
    >
      <!-- Pola ikon dekoratif -->
      <div
        class="pointer-events-none absolute inset-0 grid -rotate-6 scale-110 grid-cols-6 gap-6 p-6 text-white opacity-10"
      >
        <span
          v-for="(ic, i) in decorIcons"
          :key="i"
          class="material-symbols-outlined text-4xl"
          >{{ ic }}</span
        >
      </div>

      <!-- Badge atas -->
      <div class="relative z-10 flex items-center gap-2.5">
        <div
          class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3.5 py-1.5 text-xs font-semibold uppercase tracking-wider text-blue-100 backdrop-blur-md"
        >
          <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span>
          <span>After Schola • Platform Operasional</span>
        </div>
      </div>

      <!-- Hero tengah -->
      <div class="relative z-10 my-auto py-8">
        <div
          class="mb-6 flex h-14 w-14 items-center justify-center rounded-2xl border border-blue-400/30 bg-blue-600/30 shadow-inner"
        >
          <span class="material-symbols-outlined text-3xl text-amber-400">auto_stories</span>
        </div>
        <h2 class="mb-4 font-headline-xl text-3xl font-extrabold leading-tight text-white lg:text-4xl">
          Membangun Generasi Cerdas Digital
        </h2>
        <p class="max-w-md font-body-md text-base leading-relaxed text-blue-200">
          Platform terpadu absensi, jadwal mentoring, dan pelaporan operasional sekolah After Schola
          seluruh Indonesia.
        </p>
      </div>

      <!-- Highlight fitur -->
      <div
        class="relative z-10 flex items-center gap-6 border-t border-white/10 pt-4 text-xs text-blue-200"
      >
        <div class="flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base text-emerald-400">verified</span>
          <span>Geofence Validated</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base text-amber-400">bolt</span>
          <span>Cepat &amp; Ringan</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span class="material-symbols-outlined text-base text-blue-300">lock</span>
          <span>TLS 256-bit</span>
        </div>
      </div>
    </div>

    <!-- ================= PANEL KANAN (form) ================= -->
    <div
      class="flex h-full w-full flex-col justify-between overflow-y-auto bg-white p-6 sm:p-10 md:w-[58%] md:p-12 lg:w-[55%] lg:p-16 xl:p-20"
    >
      <!-- Header brand -->
      <div class="flex w-full items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-primary-container">
            <span class="material-symbols-outlined fill text-[20px] text-white">school</span>
          </div>
          <div class="flex flex-col leading-tight">
            <span class="text-[15px] font-bold tracking-tight text-[#0B2A5B]">AFTER SCHOLA</span>
            <span class="text-[9px] uppercase tracking-[0.2em] text-slate-400">Operational</span>
          </div>
        </div>
        <span class="font-label-sm text-label-sm font-semibold text-slate-400">v1.0</span>
      </div>

      <!-- Form -->
      <div class="mx-auto my-auto w-full max-w-md py-8">
        <h1 class="font-headline-xl text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
          Login
        </h1>
        <p class="mb-8 mt-2 font-body-md text-sm text-slate-500">
          Masuk untuk mengakses dashboard sesuai peranmu
        </p>

        <form class="space-y-5" @submit.prevent="handleSubmit">
          <!-- Email -->
          <div>
            <label
              class="mb-2 block font-label-md text-xs font-semibold uppercase tracking-wider text-slate-700"
              for="email"
              >Email</label
            >
            <input
              id="email"
              v-model="email"
              type="email"
              required
              autocomplete="email"
              placeholder="nama@afterschola.id"
              class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800 shadow-sm transition-all placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
            />
          </div>

          <!-- Password -->
          <div>
            <label
              class="mb-2 block font-label-md text-xs font-semibold uppercase tracking-wider text-slate-700"
              for="password"
              >Password</label
            >
            <div class="relative">
              <input
                id="password"
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 pr-11 text-sm text-slate-800 shadow-sm transition-all placeholder:text-slate-400 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100"
              />
              <button
                type="button"
                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 transition-colors hover:text-blue-600 focus:outline-none"
                @click="showPassword = !showPassword"
              >
                <span class="material-symbols-outlined text-[20px]">{{
                  showPassword ? 'visibility_off' : 'visibility'
                }}</span>
              </button>
            </div>
          </div>

          <!-- Pesan error -->
          <p
            v-if="errorMessage"
            class="flex items-center gap-2 rounded-lg bg-error-container px-3 py-2 text-sm text-on-error-container"
          >
            <span class="material-symbols-outlined text-[18px]">error</span>
            {{ errorMessage }}
          </p>

          <!-- Kontrol -->
          <div class="flex items-center justify-between pt-1 text-sm">
            <label class="flex cursor-pointer select-none items-center gap-2 text-slate-600">
              <input
                v-model="remember"
                type="checkbox"
                class="h-4 w-4 cursor-pointer rounded border-slate-300 text-blue-600 focus:ring-0"
              />
              <span>Ingat saya</span>
            </label>
            <a
              href="#"
              class="font-label-sm font-semibold text-blue-600 transition-colors hover:text-blue-700"
              @click.prevent
              >Lupa Password?</a
            >
          </div>

          <!-- Submit -->
          <button
            type="submit"
            :disabled="auth.loading"
            class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-[#1D4ED8] px-6 py-3.5 text-sm font-semibold text-white shadow-md transition-all duration-200 hover:bg-blue-700 hover:shadow-lg active:scale-[0.99] disabled:opacity-60"
          >
            <template v-if="auth.loading">
              <span class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              <span>Memverifikasi Akses...</span>
            </template>
            <template v-else>
              <span>Login</span>
              <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
            </template>
          </button>

          <p class="pt-2 text-center font-body-sm text-xs text-slate-400">
            Sistem mendeteksi peran akun secara otomatis (Trainer / Management)
          </p>
        </form>
      </div>

      <!-- Footer -->
      <div class="w-full border-t border-slate-100 pt-6 text-center sm:text-left">
        <p class="font-body-sm text-xs text-slate-400">© 2026 Tim Website After Schola</p>
      </div>
    </div>
  </div>
</template>
