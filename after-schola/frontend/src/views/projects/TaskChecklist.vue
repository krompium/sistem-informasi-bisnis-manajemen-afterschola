<script setup>
// Checklist sub-tugas satu task. Dipakai di modal ubah (Developer) dan modal detail (Management, read-only).
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const props = defineProps({
  taskId: { type: Number, required: true },
  canEdit: { type: Boolean, default: false },
})
const emit = defineEmits(['changed'])

const items = ref([])
const loading = ref(true)
const error = ref('')
const title = ref('')
const busy = ref(false)

const doneCount = computed(() => items.value.filter((i) => i.is_done).length)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/tasks/${props.taskId}/checklist`)
    items.value = unwrap(data)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat checklist.')
  } finally {
    loading.value = false
  }
}

async function run(action) {
  busy.value = true
  error.value = ''
  try {
    await action()
    emit('changed')
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal menyimpan checklist.')
  } finally {
    busy.value = false
  }
}

function add() {
  const value = title.value.trim()
  if (!value) return
  return run(async () => {
    const { data } = await api.post(`/tasks/${props.taskId}/checklist`, { title: value })
    items.value.push(data.data)
    title.value = ''
  })
}

function toggle(item) {
  return run(async () => {
    const { data } = await api.put(`/checklist/${item.id}`, { is_done: !item.is_done })
    Object.assign(item, data.data)
  })
}

function remove(item) {
  return run(async () => {
    await api.delete(`/checklist/${item.id}`)
    items.value = items.value.filter((i) => i.id !== item.id)
  })
}

onMounted(load)
watch(() => props.taskId, load)
</script>

<template>
  <section class="flex flex-col gap-space-sm border-t border-surface-container p-space-lg">
    <div class="flex items-center justify-between">
      <h3 class="font-label-lg text-label-lg font-semibold text-on-surface">Checklist</h3>
      <span v-if="items.length" class="font-label-sm text-label-sm text-on-surface-variant">{{ doneCount }}/{{ items.length }} selesai</span>
    </div>

    <p v-if="error" class="rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ error }}</p>
    <p v-if="loading" class="font-body-sm text-body-sm text-outline">Memuat checklist…</p>
    <p v-else-if="!items.length" class="font-body-sm text-body-sm text-outline">Belum ada item.</p>

    <ul v-else class="flex flex-col gap-1">
      <li v-for="i in items" :key="i.id" class="flex items-center gap-space-xs">
        <input
          type="checkbox"
          :checked="i.is_done"
          :disabled="!canEdit || busy"
          class="h-4 w-4 shrink-0 accent-[#0B2A5B]"
          @change="toggle(i)"
        />
        <span class="min-w-0 flex-1 break-words font-body-sm text-body-sm" :class="i.is_done ? 'text-outline line-through' : 'text-on-surface'">{{ i.title }}</span>
        <button
          v-if="canEdit"
          type="button"
          :disabled="busy"
          class="shrink-0 rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-low disabled:opacity-60"
          title="Hapus item"
          @click="remove(i)"
        >
          <span class="material-symbols-outlined text-[16px]">close</span>
        </button>
      </li>
    </ul>

    <div v-if="canEdit" class="flex gap-space-xs">
      <input
        v-model="title"
        type="text"
        maxlength="255"
        placeholder="Tambah item…"
        class="min-w-0 flex-1 rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
        @keydown.enter.prevent="add"
      />
      <button
        type="button"
        :disabled="busy || !title.trim()"
        class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
        @click="add"
      >
        Tambah
      </button>
    </div>
  </section>
</template>
