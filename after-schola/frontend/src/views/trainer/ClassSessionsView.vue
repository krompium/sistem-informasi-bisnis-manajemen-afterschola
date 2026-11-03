<script setup>
// Sesi Kelas (Modul 7, TRN-02/06) — sesi kelas reguler milik trainer: yang ditugaskan, atau diisi sebagai pengganti.
// Mobile-first: satu kolom, target sentuh besar.
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const now = new Date()
const todayKey = ymd(now)
const from = ymd(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7))
const to = ymd(new Date(now.getFullYear(), now.getMonth(), now.getDate() + 14))

const loading = ref(true)
const error = ref('')
const days = ref([])

const dayLabel = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/class-sessions', { params: { from, to, per_page: 100 } })
    const byDate = new Map()
    for (const s of unwrap(data)) byDate.set(s.date, [...(byDate.get(s.date) ?? []), s])
    days.value = [...byDate.entries()].map(([date, items]) => ({ date, items }))
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat sesi kelas.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-lg">
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Sesi Kelas</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Pilih sesi untuk mengabsen siswa. 7 hari terakhir sampai 14 hari ke depan.</p>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 3" :key="i" class="h-20 animate-pulse rounded-xl bg-surface-container"></div>
    </div>
    <div v-else-if="error" class="rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">{{ error }}</div>
    <div v-else-if="!days.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">event_busy</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada sesi pada rentang ini</p>
    </div>

    <div v-else class="flex flex-col gap-space-md">
      <section v-for="day in days" :key="day.date">
        <h2 :class="['mb-space-xs font-label-lg text-label-lg font-semibold', day.date === todayKey ? 'text-primary' : 'text-on-surface-variant']">
          {{ day.date === todayKey ? 'Hari ini • ' : '' }}{{ dayLabel(day.date) }}
        </h2>
        <div class="flex flex-col gap-space-xs">
          <RouterLink
            v-for="s in day.items"
            :key="s.id"
            :to="`/trainer/sesi-kelas/${s.id}`"
            class="flex min-h-[56px] items-center gap-space-sm rounded-2xl bg-surface-container-lowest px-space-md py-space-sm shadow-sm transition-all active:scale-[0.99]"
          >
            <div class="flex flex-1 flex-col gap-0.5">
              <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ s.start_time }}–{{ s.end_time }} • {{ s.class_group.name }}</span>
              <span class="font-body-sm text-body-sm text-on-surface-variant">Pertemuan ke-{{ s.meeting_no }} • {{ s.mode === 'online' ? 'Online' : 'Onsite' }}</span>
            </div>
            <span v-if="s.is_substitute" class="rounded-full bg-secondary-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-secondary-container">Pengganti</span>
            <span v-if="s.is_locked" class="material-symbols-outlined text-[18px] text-outline" title="Sesi dikunci">lock</span>
            <span class="material-symbols-outlined text-[20px] text-outline">chevron_right</span>
          </RouterLink>
        </div>
      </section>
    </div>
  </div>
</template>
