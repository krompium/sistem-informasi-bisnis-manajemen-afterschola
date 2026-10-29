<script setup>
// Daftar program aktif untuk publik (PUB-01). Tanpa login.
import { onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatRupiah, unwrap } from '@/lib/format'

const programs = ref([])
const loading = ref(true)
const failed = ref(false)

onMounted(async () => {
  try {
    const { data } = await api.get('/public/programs')
    programs.value = unwrap(data)
  } catch (e) {
    failed.value = true
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-landing">
    <header class="bg-brandNavy text-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
        <RouterLink to="/" class="text-lg font-extrabold tracking-tight">AfterSchola</RouterLink>
        <RouterLink :to="{ name: 'login' }" class="text-sm font-semibold text-white/90 hover:text-brandSun">Masuk</RouterLink>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10">
      <h1 class="text-3xl font-extrabold text-brandNavy">Program Kami</h1>
      <p class="mt-2 text-sm text-slate-600">Pilih program, lalu coba kelas pertama secara gratis.</p>

      <div v-if="loading" class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="i in 3" :key="i" class="h-40 animate-pulse rounded-2xl bg-slate-200"></div>
      </div>
      <p v-else-if="failed" class="mt-8 text-sm text-red-700">Program belum dapat dimuat. Coba lagi beberapa saat.</p>
      <p v-else-if="!programs.length" class="mt-8 text-sm text-slate-600">Belum ada program yang dibuka.</p>

      <div v-else class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <RouterLink
          v-for="p in programs"
          :key="p.id"
          :to="{ name: 'public.program-detail', params: { id: p.id } }"
          class="flex flex-col gap-3 rounded-2xl bg-white p-5 shadow-md transition-transform hover:-translate-y-1"
        >
          <div class="flex flex-wrap gap-2">
            <span v-if="p.jenjang" class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">{{ p.jenjang }}</span>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold capitalize text-slate-600">{{ p.tipe }}</span>
          </div>
          <h2 class="text-lg font-bold text-brandNavy">{{ p.name }}</h2>
          <p v-if="p.description" class="line-clamp-3 text-sm text-slate-600">{{ p.description }}</p>
          <p class="mt-auto text-base font-extrabold text-brandNavy">{{ formatRupiah(p.price) }}</p>
        </RouterLink>
      </div>
    </main>
  </div>
</template>
