<script setup>
// Detail program untuk publik (PUB-02). URL /program/:id statis, cocok untuk QR code (PUB-06).
// Slot trial yang masih tersedia diambil dari /public/trial-slots (berkapasitas, per cabang).
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah, formatStamp } from '@/lib/format'

const route = useRoute()
const program = ref(null)
const loading = ref(true)
const slots = ref([])

const slotsByBranch = computed(() => {
  const groups = {}
  for (const s of slots.value) (groups[s.branch.name] ??= []).push(s)
  return Object.entries(groups)
})

onMounted(async () => {
  try {
    const { data } = await api.get(`/public/programs/${route.params.id}`)
    program.value = data.data ?? data
  } catch (e) {
    program.value = null // 404: program tidak ada atau tidak aktif
  }

  if (program.value) {
    try {
      const { data } = await api.get('/public/trial-slots', { params: { program_id: program.value.id } })
      slots.value = data.data ?? []
    } catch (e) {
      slots.value = [] // non-fatal: halaman program tetap tampil tanpa daftar slot
    }
  }
  loading.value = false
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-landing">
    <header class="bg-brandNavy text-white">
      <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
        <RouterLink to="/" class="text-lg font-extrabold tracking-tight">AfterSchola</RouterLink>
        <RouterLink :to="{ name: 'login' }" class="text-sm font-semibold text-white/90 hover:text-brandSun">Masuk</RouterLink>
      </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-10">
      <RouterLink :to="{ name: 'public.programs' }" class="text-sm font-semibold text-brandBlue hover:underline">← Semua program</RouterLink>

      <div v-if="loading" class="mt-6 h-64 animate-pulse rounded-2xl bg-slate-200"></div>

      <div v-else-if="!program" class="mt-6 rounded-2xl bg-white p-8 text-center shadow-md">
        <h1 class="text-xl font-bold text-brandNavy">Program tidak ditemukan</h1>
        <p class="mt-2 text-sm text-slate-600">Program ini tidak tersedia atau sudah ditutup.</p>
      </div>

      <article v-else class="mt-6 flex flex-col gap-6 rounded-2xl bg-white p-6 shadow-md sm:p-8">
        <div>
          <div class="flex flex-wrap gap-2">
            <span v-if="program.jenjang" class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ program.jenjang }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-600">{{ program.tipe }}</span>
          </div>
          <h1 class="mt-3 text-3xl font-extrabold text-brandNavy">{{ program.name }}</h1>
          <p class="mt-1 text-lg font-bold text-brandNavy">{{ formatRupiah(program.price) }}</p>
        </div>

        <p v-if="program.description" class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ program.description }}</p>

        <section v-if="program.levels?.length">
          <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Level</h2>
          <ul class="mt-2 flex flex-wrap gap-2">
            <li v-for="l in program.levels" :key="l.id" class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700">{{ l.name }}</li>
          </ul>
        </section>

        <section v-if="slotsByBranch.length">
          <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">Jadwal trial</h2>
          <div v-for="[branch, items] in slotsByBranch" :key="branch" class="mt-2">
            <p class="text-sm font-semibold text-brandNavy">{{ branch }}</p>
            <ul class="mt-1 space-y-1 text-sm text-slate-700">
              <li v-for="s in items" :key="s.id">{{ formatStamp(s.starts_at) }} • sisa {{ s.remaining }} kursi</li>
            </ul>
          </div>
        </section>

        <RouterLink
          :to="{ name: 'public.trial-registration', query: { program: program.id } }"
          class="inline-flex items-center justify-center rounded-full bg-brandSun px-7 py-3 font-bold text-brandDeepNavy shadow-lg hover:bg-brandSunDark"
        >
          Daftar Trial Gratis
        </RouterLink>
      </article>
    </main>
  </div>
</template>
