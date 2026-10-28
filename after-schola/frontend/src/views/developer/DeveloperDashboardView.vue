<script setup>
// Dashboard team dev (Modul 12, FR-12.6): proyek berjalan, task per status, siapa mengerjakan apa.
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatDateShort, initials } from '@/lib/format'

const loading = ref(true)
const errorMessage = ref('')
const data = ref(null)

const STATUS_CARDS = [
  { key: 'todo', label: 'To Do', icon: 'radio_button_unchecked' },
  { key: 'in_progress', label: 'In Progress', icon: 'pending' },
  { key: 'review', label: 'Review', icon: 'rate_review' },
  { key: 'done', label: 'Done', icon: 'check_circle' },
]

const projects = computed(() => data.value?.ongoing_projects ?? [])
const workload = computed(() => data.value?.workload ?? [])
const maxOpen = computed(() => Math.max(1, ...workload.value.map((w) => w.open_tasks)))
const percent = (p) => (p.tasks_total ? Math.round((p.tasks_done / p.tasks_total) * 100) : 0)

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const res = await api.get('/dashboard')
    data.value = res.data
  } catch (err) {
    errorMessage.value = 'Gagal memuat dashboard.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Modul Manajemen Proyek</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Dashboard Team</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Ringkasan proyek dan task di team kamu.</p>
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-if="loading" class="grid grid-cols-2 gap-space-md lg:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else-if="data">
      <div class="grid grid-cols-2 gap-space-md lg:grid-cols-4">
        <div v-for="s in STATUS_CARDS" :key="s.key" class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <span class="flex items-center gap-space-xs font-label-md text-label-md text-on-surface-variant">
            <span class="material-symbols-outlined text-[18px] text-outline">{{ s.icon }}</span>{{ s.label }}
          </span>
          <span class="font-headline-xl text-headline-xl font-bold text-on-surface">{{ data.tasks_by_status[s.key] }}</span>
        </div>
      </div>

      <div class="mt-space-lg grid grid-cols-1 gap-space-lg lg:grid-cols-3">
        <section class="flex flex-col gap-space-sm lg:col-span-2">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Proyek Berjalan</h2>
          <div v-if="!projects.length" class="flex flex-col items-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-space-lg py-space-xl text-center">
            <span class="material-symbols-outlined text-[32px] text-outline">view_kanban</span>
            <p class="mt-space-xs font-body-md text-body-md text-on-surface-variant">Belum ada proyek yang berjalan.</p>
          </div>
          <router-link
            v-for="p in projects"
            :key="p.id"
            :to="`/developer/proyek/${p.id}`"
            class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm transition-all hover:shadow-md"
          >
            <div class="flex items-center justify-between gap-space-sm">
              <h3 class="truncate font-headline-sm text-headline-sm font-bold text-on-surface">{{ p.name }}</h3>
              <span class="shrink-0 font-label-md text-label-md text-outline">Tenggat {{ formatDateShort(p.due_date) }}</span>
            </div>
            <div class="flex items-center gap-space-sm">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-container">
                <div class="h-full rounded-full bg-primary-container" :style="{ width: percent(p) + '%' }"></div>
              </div>
              <span class="font-label-md text-label-md text-on-surface-variant">{{ p.tasks_done }}/{{ p.tasks_total }} task selesai</span>
            </div>
          </router-link>
        </section>

        <section class="flex flex-col gap-space-sm">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Siapa Mengerjakan Apa</h2>
          <div class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
            <p v-if="!workload.length" class="font-body-sm text-body-sm text-on-surface-variant">Tidak ada task yang sedang dikerjakan.</p>
            <div v-for="w in workload" :key="w.user_id" class="flex flex-col gap-1">
              <div class="flex items-center justify-between">
                <span class="inline-flex items-center gap-space-xs font-body-md text-body-md text-on-surface">
                  <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-fixed text-[10px] font-bold text-primary">{{ initials(w.name) }}</span>
                  {{ w.name }}
                </span>
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">{{ w.open_tasks }} task</span>
              </div>
              <div class="h-1.5 overflow-hidden rounded-full bg-surface-container">
                <div class="h-full rounded-full bg-secondary" :style="{ width: (w.open_tasks / maxOpen) * 100 + '%' }"></div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>
