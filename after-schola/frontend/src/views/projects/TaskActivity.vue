<script setup>
// Log aktivitas + komentar satu task. Dipakai di modal ubah (Developer) dan modal detail (Management).
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { initials, unwrap } from '@/lib/format'

const props = defineProps({
  taskId: { type: Number, required: true },
  canComment: { type: Boolean, default: false },
})

const items = ref([])
const loading = ref(true)
const error = ref('')
const note = ref('')
const posting = ref(false)
const postError = ref('')

// TODO(nanti): baru halaman pertama (15 entri terbaru) — tambah "muat lebih banyak" bila perlu.
async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/tasks/${props.taskId}/updates`)
    items.value = unwrap(data)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat aktivitas.')
  } finally {
    loading.value = false
  }
}

async function post() {
  if (!note.value.trim()) return
  posting.value = true
  postError.value = ''
  try {
    const { data } = await api.post(`/tasks/${props.taskId}/updates`, { note: note.value })
    items.value.unshift(data.data)
    note.value = ''
  } catch (err) {
    postError.value = apiErrorMessage(err, 'Gagal mengirim komentar.')
  } finally {
    posting.value = false
  }
}

function formatTime(value) {
  return new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

onMounted(load)
watch(() => props.taskId, load)
</script>

<template>
  <section class="flex flex-col gap-space-sm border-t border-surface-container p-space-lg">
    <h3 class="font-label-lg text-label-lg font-semibold text-on-surface">Aktivitas</h3>

    <div v-if="canComment" class="flex flex-col gap-space-xs">
      <textarea
        v-model="note"
        rows="2"
        maxlength="2000"
        placeholder="Tulis komentar…"
        class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
      ></textarea>
      <p v-if="postError" class="rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ postError }}</p>
      <button
        type="button"
        :disabled="posting || !note.trim()"
        class="self-end rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
        @click="post"
      >
        Kirim
      </button>
    </div>

    <p v-if="loading" class="font-body-sm text-body-sm text-outline">Memuat aktivitas…</p>
    <p v-else-if="error" class="font-body-sm text-body-sm text-error">{{ error }}</p>
    <p v-else-if="!items.length" class="font-body-sm text-body-sm text-outline">Belum ada aktivitas.</p>
    <ul v-else class="flex flex-col gap-space-sm">
      <li v-for="u in items" :key="u.id" class="flex gap-space-xs">
        <span
          class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-fixed text-[10px] font-bold text-primary"
          :title="u.user?.name || 'Sistem'"
        >{{ u.user ? initials(u.user.name) : '•' }}</span>
        <div class="min-w-0 flex-1">
          <p class="break-words font-body-sm text-body-sm text-on-surface">{{ u.note }}</p>
          <p class="font-label-sm text-label-sm text-outline">{{ u.user?.name || 'Sistem' }} · {{ formatTime(u.created_at) }}</p>
        </div>
      </li>
    </ul>
  </section>
</template>
