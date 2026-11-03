<script setup>
// Jadwal (ORT-10): kelas aktif tiap anak, trainer, dan pertemuan yang akan datang (read-only).
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import api from '@/lib/api'
import { formatDate } from '@/lib/format'
import { errMsg } from './portalCommon'

const loading = ref(true)
const loadError = ref('')
const classes = ref([])

const MODE = { onsite: 'Tatap muka', online: 'Online' }
const ROLE = { utama: 'Trainer utama', asisten: 'Asisten' }

async function load() {
  try {
    const { data } = await api.get('/parent/schedule')
    classes.value = data.data
  } catch (err) {
    loadError.value = errMsg(err, 'Gagal memuat jadwal.')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Portal Orang Tua</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Jadwal</h1>
    </div>

    <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <div v-else-if="!classes.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
      Belum ada kelas aktif. Jadwal muncul setelah anak ditempatkan di kelas.
      <div class="mt-space-sm"><RouterLink to="/portal" class="text-secondary hover:underline">Lihat status pendaftaran</RouterLink></div>
    </div>

    <div v-else class="flex flex-col gap-space-md">
      <section v-for="c in classes" :key="c.enrollment_id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex flex-col">
          <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ c.student_name }} — {{ c.class.name }}</h2>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ c.class.program }} • {{ c.class.level }} • {{ c.class.branch }}</span>
          <span v-if="c.trainers.length" class="font-body-sm text-body-sm text-on-surface-variant">
            {{ c.trainers.map((t) => `${t.name} (${ROLE[t.role] || t.role})`).join(', ') }}
          </span>
        </div>

        <p v-if="!c.sessions.length" class="font-body-md text-body-md text-on-surface-variant">Belum ada pertemuan terjadwal.</p>
        <div v-else class="flex flex-col gap-space-xs">
          <div v-for="s in c.sessions" :key="s.id" class="flex flex-wrap items-center justify-between gap-space-sm rounded-xl bg-surface-container-low px-space-md py-space-sm">
            <div class="flex flex-col">
              <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ formatDate(s.date) }}</span>
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                Pertemuan {{ s.meeting_no }} • {{ s.start_time }}–{{ s.end_time }} • {{ MODE[s.mode] || s.mode }}
              </span>
            </div>
            <span v-if="s.trainer" class="font-body-sm text-body-sm text-on-surface-variant">{{ s.trainer }}</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
