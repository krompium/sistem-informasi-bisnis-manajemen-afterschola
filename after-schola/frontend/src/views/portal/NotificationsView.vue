<script setup>
// Notifikasi (ORT-13): kabar status di portal. Membuka satu notifikasi menandainya terbaca dan menuju halaman terkait.
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { formatStamp } from '@/lib/format'
import { errMsg } from './portalCommon'

const router = useRouter()
const loading = ref(true)
const loadError = ref('')
const items = ref([])
const unread = ref(0)

// Tujuan tiap jenis notifikasi; yang tidak dikenal kembali ke beranda.
function targetOf(n) {
  switch (n.kind) {
    case 'trial_registered':
    case 'trial_result':
      return n.lead_id ? `/portal/anak/${n.lead_id}` : '/portal'
    case 'registration_revision':
      return n.lead_id ? `/portal/anak/${n.lead_id}/pendaftaran` : '/portal'
    case 'registration_verified':
    case 'payment_confirmed':
    case 'payment_rejected':
    case 'payment_reminder':
      return '/portal/tagihan'
    case 'student_placed':
    case 'session_scheduled':
    case 'session_changed':
    case 'session_released':
      return '/portal/jadwal'
    default:
      return '/portal'
  }
}

async function load() {
  try {
    const { data } = await api.get('/notifications')
    items.value = data.data
    unread.value = data.unread_count
  } catch (err) {
    loadError.value = errMsg(err, 'Gagal memuat notifikasi.')
  } finally {
    loading.value = false
  }
}

async function open(n) {
  if (!n.read_at) {
    try {
      await api.post(`/notifications/${n.id}/read`)
    } catch (err) {
      // Gagal menandai terbaca tidak boleh menghalangi navigasi.
    }
  }
  router.push(targetOf(n))
}

async function readAll() {
  await api.post('/notifications/read-all')
  await load()
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-wrap items-end justify-between gap-space-sm py-space-xl">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Portal Orang Tua</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Notifikasi</h1>
      </div>
      <button v-if="unread" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low" @click="readAll">
        Tandai semua dibaca
      </button>
    </div>

    <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>
    <div v-else-if="!items.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
      Belum ada notifikasi.
    </div>

    <div v-else class="flex flex-col gap-space-xs">
      <button
        v-for="n in items"
        :key="n.id"
        :class="['flex flex-col gap-1 rounded-xl px-space-lg py-space-md text-left shadow-sm transition-all hover:opacity-90', n.read_at ? 'bg-surface-container-lowest' : 'bg-secondary-container']"
        @click="open(n)"
      >
        <span :class="['font-body-md text-body-md text-on-surface', !n.read_at && 'font-semibold']">{{ n.message }}</span>
        <span class="font-label-sm text-label-sm text-on-surface-variant">{{ formatStamp(n.created_at) }}</span>
      </button>
    </div>
  </div>
</template>
