<script setup>
// Lonceng notifikasi dalam aplikasi untuk Developer (ditugaskan, dikomentari, tenggat diubah).
// Pembaruan lewat polling; klik notifikasi menandainya dibaca dan membuka board proyeknya.
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { unwrap } from '@/lib/format'
import { onMounted } from 'vue'
import { usePolling } from '@/lib/usePolling'

const router = useRouter()
const open = ref(false)
const items = ref([])
const unread = ref(0)

const ICONS = { assigned: 'assignment_ind', commented: 'chat', due_date_changed: 'event' }

async function load() {
  const { data } = await api.get('/notifications')
  items.value = unwrap(data)
  unread.value = data.unread_count ?? 0
}

async function openItem(n) {
  open.value = false
  if (!n.read_at) {
    try {
      await api.post(`/notifications/${n.id}/read`)
      await load()
    } catch {
      /* non-kritis: tetap lanjut membuka proyeknya */
    }
  }
  router.push(`/developer/proyek/${n.project_id}`)
}

async function markAll() {
  try {
    await api.post('/notifications/read-all')
    await load()
  } catch {
    /* non-kritis */
  }
}

function formatTime(value) {
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(load)
usePolling(load, 15000)
</script>

<template>
  <div class="relative">
    <button
      class="relative rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container-low hover:text-on-surface"
      aria-label="Notifikasi"
      @click="open = !open"
    >
      <span class="material-symbols-outlined text-[22px]">notifications</span>
      <span
        v-if="unread"
        class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-on-error ring-2 ring-surface-container-lowest"
        >{{ unread > 9 ? '9+' : unread }}</span
      >
    </button>

    <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>
    <div
      v-if="open"
      class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl ring-1 ring-surface-container animate-fade-in"
    >
      <div class="flex items-center justify-between border-b border-surface-container px-space-md py-space-sm">
        <span class="font-headline-sm text-headline-sm font-bold text-on-surface">Notifikasi</span>
        <button v-if="unread" class="font-label-sm text-label-sm font-semibold text-primary hover:underline" @click="markAll">
          Tandai dibaca
        </button>
      </div>

      <div v-if="!items.length" class="flex flex-col items-center gap-1 px-space-md py-space-lg text-center">
        <span class="material-symbols-outlined text-[28px] text-outline">notifications_off</span>
        <span class="font-body-sm text-body-sm text-on-surface-variant">Belum ada notifikasi</span>
      </div>
      <div v-else class="max-h-80 overflow-y-auto">
        <button
          v-for="n in items"
          :key="n.id"
          class="flex w-full items-start gap-space-sm border-b border-surface-container px-space-md py-space-sm text-left transition-colors last:border-0 hover:bg-surface-container-low"
          :class="n.read_at ? '' : 'bg-primary-fixed/30'"
          @click="openItem(n)"
        >
          <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-secondary-fixed text-on-secondary-container">
            <span class="material-symbols-outlined text-[16px]">{{ ICONS[n.kind] || 'notifications' }}</span>
          </span>
          <span class="flex min-w-0 flex-col">
            <span class="break-words font-label-lg text-label-lg text-on-surface" :class="n.read_at ? 'font-normal' : 'font-semibold'">{{ n.message }}</span>
            <span class="truncate font-body-sm text-body-sm text-on-surface-variant">{{ n.task_title }}</span>
            <span class="font-label-sm text-label-sm text-outline">{{ formatTime(n.created_at) }}</span>
          </span>
        </button>
      </div>
    </div>
  </div>
</template>
