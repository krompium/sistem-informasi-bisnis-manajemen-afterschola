<script setup>
// Daftar program aktif untuk publik (PUB-01). Tanpa login.
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatRupiah, unwrap } from '@/lib/format'
import PublicHeader from './PublicHeader.vue'

const programs = ref([])
const loading = ref(true)
const failed = ref(false)

// Warna + ikon per jenjang supaya kartu mudah dibedakan (anak dan orang tua).
const jenjangStyle = {
  SD: { icon: 'toys', band: 'from-brandSun to-amber-400' },
  SMP: { icon: 'rocket_launch', band: 'from-brandTeal to-emerald-400' },
  SMA: { icon: 'workspace_premium', band: 'from-brandPurple to-indigo-400' },
}
const defaultStyle = { icon: 'school', band: 'from-brandBlue to-sky-400' }

async function load() {
  loading.value = true
  failed.value = false
  try {
    const { data } = await api.get('/public/programs')
    programs.value = unwrap(data)
  } catch (e) {
    failed.value = true
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-landing">
    <PublicHeader />

    <main class="mx-auto max-w-5xl px-4 py-10">
      <h1 class="text-3xl font-extrabold text-brandNavy sm:text-4xl">Program Kami</h1>
      <p class="mt-2 text-base text-slate-600">
        Pilih program yang cocok untuk si kecil, lalu coba kelas pertamanya gratis!
      </p>

      <div v-if="loading" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true">
        <div v-for="i in 3" :key="i" class="h-56 animate-pulse rounded-3xl bg-slate-200"></div>
      </div>

      <div v-else-if="failed" class="mt-8 rounded-3xl bg-white p-8 text-center shadow-md">
        <span class="material-symbols-outlined text-4xl text-red-500" aria-hidden="true">wifi_off</span>
        <p class="mt-2 text-base text-slate-700">Program belum bisa dimuat. Periksa koneksi lalu coba lagi.</p>
        <button
          type="button"
          class="mt-4 inline-flex min-h-[48px] items-center justify-center rounded-full bg-brandNavy px-6 font-bold text-white hover:bg-brandBlue"
          @click="load"
        >
          Coba lagi
        </button>
      </div>

      <div v-else-if="!programs.length" class="mt-8 rounded-3xl bg-white p-8 text-center shadow-md">
        <span class="material-symbols-outlined text-4xl text-brandSun" aria-hidden="true">auto_awesome</span>
        <p class="mt-2 text-base text-slate-700">Program baru sedang disiapkan. Tunggu kabar dari kami ya!</p>
      </div>

      <div v-else class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="p in programs"
          :key="p.id"
          :to="{ name: 'public.program-detail', params: { id: p.id } }"
          class="group flex flex-col overflow-hidden rounded-3xl bg-white shadow-md transition-transform hover:-translate-y-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brandBlue"
        >
          <div
            class="flex items-center justify-between bg-gradient-to-r px-5 py-4 text-white"
            :class="(jenjangStyle[p.jenjang] ?? defaultStyle).band"
          >
            <span class="material-symbols-outlined text-4xl transition-transform group-hover:rotate-12" aria-hidden="true">
              {{ (jenjangStyle[p.jenjang] ?? defaultStyle).icon }}
            </span>
            <span v-if="p.jenjang" class="rounded-full bg-white/25 px-3 py-1 text-sm font-bold">{{ p.jenjang }}</span>
          </div>
          <div class="flex flex-1 flex-col gap-2 p-5">
            <h2 class="text-xl font-bold text-brandNavy">{{ p.name }}</h2>
            <p v-if="p.description" class="line-clamp-3 text-base text-slate-600">{{ p.description }}</p>
            <div class="mt-auto flex items-center justify-between pt-3">
              <p class="text-lg font-extrabold text-brandNavy">{{ formatRupiah(p.price) }}</p>
              <span class="inline-flex items-center gap-1 text-sm font-bold text-brandBlue">
                Lihat detail
                <span class="material-symbols-outlined text-lg transition-transform group-hover:translate-x-1" aria-hidden="true">arrow_forward</span>
              </span>
            </div>
          </div>
        </RouterLink>
      </div>
    </main>
  </div>
</template>
