<script setup>
// Detail program untuk publik (PUB-02). URL /program/:id statis, cocok untuk QR code (PUB-06).
// TODO(nanti): ganti jadwal trial mingguan dengan slot trial berkapasitas per cabang (Modul 2).
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah } from '@/lib/format'
import { whatsappLink } from '@/lib/contact'
import PublicHeader from './PublicHeader.vue'

const route = useRoute()
const program = ref(null)
const loading = ref(true)

onMounted(async () => {
  try {
    const { data } = await api.get(`/public/programs/${route.params.id}`)
    program.value = data.data ?? data
  } catch (e) {
    program.value = null // 404: program tidak ada atau tidak aktif
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-landing">
    <PublicHeader />

    <!-- pb besar di HP: ruang untuk bar tombol yang menempel di bawah layar -->
    <main class="mx-auto max-w-3xl px-4 pb-32 pt-6 sm:pb-12">
      <RouterLink
        :to="{ name: 'public.programs' }"
        class="inline-flex min-h-[44px] items-center gap-1 text-sm font-semibold text-brandBlue hover:underline"
      >
        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_back</span>
        Semua program
      </RouterLink>

      <div v-if="loading" class="mt-4 h-64 animate-pulse rounded-3xl bg-slate-200" aria-busy="true"></div>

      <div v-else-if="!program" class="mt-4 rounded-3xl bg-white p-8 text-center shadow-md">
        <span class="material-symbols-outlined text-4xl text-brandSun" aria-hidden="true">search_off</span>
        <h1 class="mt-2 text-xl font-bold text-brandNavy">Program tidak ditemukan</h1>
        <p class="mt-2 text-base text-slate-600">Program ini tidak tersedia atau sudah ditutup.</p>
        <RouterLink
          :to="{ name: 'public.programs' }"
          class="mt-4 inline-flex min-h-[48px] items-center justify-center rounded-full bg-brandNavy px-6 font-bold text-white hover:bg-brandBlue"
        >
          Lihat program lain
        </RouterLink>
      </div>

      <article v-else class="mt-4 flex flex-col gap-6 rounded-3xl bg-white p-6 shadow-md sm:p-8">
        <div>
          <div class="flex flex-wrap gap-2">
            <span v-if="program.jenjang" class="rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">{{ program.jenjang }}</span>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold capitalize text-slate-600">{{ program.tipe }}</span>
          </div>
          <h1 class="mt-3 text-3xl font-extrabold text-brandNavy sm:text-4xl">{{ program.name }}</h1>
          <p class="mt-1 text-xl font-bold text-brandNavy">{{ formatRupiah(program.price) }}</p>
        </div>

        <p v-if="program.description" class="whitespace-pre-line text-base leading-relaxed text-slate-700">{{ program.description }}</p>

        <section v-if="program.levels?.length">
          <h2 class="flex items-center gap-2 text-lg font-bold text-brandNavy">
            <span class="material-symbols-outlined text-brandTeal" aria-hidden="true">stairs</span>
            Level
          </h2>
          <ul class="mt-2 flex flex-wrap gap-2">
            <li v-for="l in program.levels" :key="l.id" class="rounded-full bg-slate-100 px-4 py-1.5 text-base text-slate-700">{{ l.name }}</li>
          </ul>
        </section>

        <section v-if="program.jadwal_trial?.length">
          <h2 class="flex items-center gap-2 text-lg font-bold text-brandNavy">
            <span class="material-symbols-outlined text-brandTeal" aria-hidden="true">event</span>
            Jadwal trial
          </h2>
          <ul class="mt-2 space-y-1 text-base text-slate-700">
            <li v-for="j in program.jadwal_trial" :key="j.id" class="capitalize">{{ j.hari }}, {{ j.jam_mulai }}–{{ j.jam_selesai }}</li>
          </ul>
        </section>

        <a
          :href="whatsappLink(`Halo AfterSchola, saya mau tanya tentang program ${program.name}.`)"
          target="_blank"
          rel="noopener"
          class="inline-flex min-h-[44px] items-center gap-2 text-base font-semibold text-brandTeal hover:underline"
        >
          <span class="material-symbols-outlined" aria-hidden="true">chat</span>
          Ada pertanyaan? Tanya lewat WhatsApp
        </a>
      </article>

      <!-- Tombol daftar: menempel di bawah layar pada HP agar selalu terjangkau jempol -->
      <div
        v-if="program"
        class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 px-4 pt-3 shadow-[0_-4px_12px_rgba(0,0,0,0.08)] backdrop-blur pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:static sm:mt-6 sm:border-0 sm:bg-transparent sm:p-0 sm:shadow-none sm:backdrop-blur-none"
      >
        <RouterLink
          :to="{ name: 'public.trial-registration', query: { program: program.id } }"
          class="flex min-h-[52px] w-full items-center justify-center gap-2 rounded-full bg-brandSun px-7 text-lg font-bold text-brandDeepNavy shadow-lg hover:bg-brandSunDark sm:inline-flex sm:w-auto"
        >
          <span class="material-symbols-outlined" aria-hidden="true">celebration</span>
          Daftar Trial Gratis
        </RouterLink>
      </div>
    </main>
  </div>
</template>
