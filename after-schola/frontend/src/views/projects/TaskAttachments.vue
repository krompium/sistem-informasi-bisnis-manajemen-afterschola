<script setup>
// Lampiran file satu task. Unggah (Developer) dan unduh lewat endpoint ber-otorisasi (file privat).
import { onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const props = defineProps({
  taskId: { type: Number, required: true },
  canEdit: { type: Boolean, default: false },
})
const emit = defineEmits(['changed'])

// Sama dengan aturan backend (StoreTaskAttachmentRequest); backend tetap yang menentukan.
const ACCEPT = '.pdf,.png,.jpg,.jpeg,.gif,.txt,.csv,.docx,.xlsx,.pptx'

const items = ref([])
const loading = ref(true)
const error = ref('')
const busy = ref(false)
const fileInput = ref(null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/tasks/${props.taskId}/attachments`)
    items.value = unwrap(data)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat lampiran.')
  } finally {
    loading.value = false
  }
}

async function upload(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  busy.value = true
  error.value = ''
  try {
    const body = new FormData()
    body.append('file', file)
    // Instance api bawaan memakai JSON; untuk unggahan harus multipart.
    const { data } = await api.post(`/tasks/${props.taskId}/attachments`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    items.value.unshift(data.data)
    emit('changed')
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal mengunggah file.')
  } finally {
    busy.value = false
  }
}

async function download(item) {
  error.value = ''
  try {
    const res = await api.get(`/attachments/${item.id}/download`, { responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data], { type: item.mime }))
    link.download = item.original_name
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch {
    error.value = 'Gagal mengunduh file.'
  }
}

async function remove(item) {
  busy.value = true
  error.value = ''
  try {
    await api.delete(`/attachments/${item.id}`)
    items.value = items.value.filter((i) => i.id !== item.id)
    emit('changed')
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal menghapus lampiran.')
  } finally {
    busy.value = false
  }
}

function formatSize(bytes) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`
}

onMounted(load)
watch(() => props.taskId, load)
</script>

<template>
  <section class="flex flex-col gap-space-sm border-t border-surface-container p-space-lg">
    <div class="flex items-center justify-between">
      <h3 class="font-label-lg text-label-lg font-semibold text-on-surface">Lampiran</h3>
      <template v-if="canEdit">
        <input ref="fileInput" type="file" :accept="ACCEPT" class="hidden" @change="upload" />
        <button
          type="button"
          :disabled="busy"
          class="inline-flex items-center gap-1 rounded-xl border border-outline-variant bg-white px-space-md py-1.5 font-label-md text-label-md text-on-surface transition-all hover:bg-surface-container-low active:scale-95 disabled:opacity-60"
          @click="fileInput.click()"
        >
          <span class="material-symbols-outlined text-[16px]">upload_file</span>
          {{ busy ? 'Memproses…' : 'Unggah file' }}
        </button>
      </template>
    </div>
    <p v-if="canEdit" class="font-label-sm text-label-sm text-outline">PDF, gambar, teks/CSV, Word, Excel, atau PowerPoint. Maks. 10 MB per file, 20 file per task.</p>

    <p v-if="error" class="rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ error }}</p>
    <p v-if="loading" class="font-body-sm text-body-sm text-outline">Memuat lampiran…</p>
    <p v-else-if="!items.length" class="font-body-sm text-body-sm text-outline">Belum ada lampiran.</p>

    <ul v-else class="flex flex-col gap-1">
      <li v-for="i in items" :key="i.id" class="flex items-center gap-space-xs">
        <span class="material-symbols-outlined shrink-0 text-[18px] text-on-surface-variant">draft</span>
        <button type="button" class="min-w-0 flex-1 text-left" :title="`Unduh ${i.original_name}`" @click="download(i)">
          <span class="block truncate font-body-sm text-body-sm text-primary hover:underline">{{ i.original_name }}</span>
          <span class="block font-label-sm text-label-sm text-outline">{{ formatSize(i.size) }} · {{ i.user?.name || '—' }}</span>
        </button>
        <button
          v-if="canEdit"
          type="button"
          :disabled="busy"
          class="shrink-0 rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-low disabled:opacity-60"
          title="Hapus lampiran"
          @click="remove(i)"
        >
          <span class="material-symbols-outlined text-[16px]">close</span>
        </button>
      </li>
    </ul>
  </section>
</template>
