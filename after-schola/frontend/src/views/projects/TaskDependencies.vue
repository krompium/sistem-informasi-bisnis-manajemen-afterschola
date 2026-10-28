<script setup>
// Ketergantungan task: task lain di proyek yang sama yang harus selesai lebih dulu.
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const props = defineProps({
  taskId: { type: Number, required: true },
  // Daftar task proyek ini (dari board), sumber pilihan prasyarat.
  projectTasks: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
})
const emit = defineEmits(['changed'])

const STATUS = { todo: 'To Do', in_progress: 'In Progress', review: 'Review', done: 'Done' }

const items = ref([])
const loading = ref(true)
const error = ref('')
const selected = ref('')
const busy = ref(false)

// Backend yang menolak duplikat, diri sendiri, dan lingkaran; daftar ini hanya mempersempit pilihan.
const candidates = computed(() =>
  props.projectTasks.filter((t) => t.id !== props.taskId && !items.value.some((i) => i.depends_on_id === t.id)),
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get(`/tasks/${props.taskId}/dependencies`)
    items.value = unwrap(data)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat ketergantungan.')
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
    error.value = apiErrorMessage(err, 'Gagal menyimpan ketergantungan.')
  } finally {
    busy.value = false
  }
}

function add() {
  if (!selected.value) return
  return run(async () => {
    const { data } = await api.post(`/tasks/${props.taskId}/dependencies`, { depends_on_id: selected.value })
    items.value.push(data.data)
    selected.value = ''
  })
}

function remove(item) {
  return run(async () => {
    await api.delete(`/dependencies/${item.id}`)
    items.value = items.value.filter((i) => i.id !== item.id)
  })
}

onMounted(load)
watch(() => props.taskId, load)
</script>

<template>
  <section class="flex flex-col gap-space-sm border-t border-surface-container p-space-lg">
    <h3 class="font-label-lg text-label-lg font-semibold text-on-surface">Bergantung pada</h3>
    <p v-if="canEdit" class="font-label-sm text-label-sm text-outline">Task ini baru bisa mulai setelah task di bawah selesai. Tampil sebagai panah di Gantt.</p>

    <p v-if="error" class="rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ error }}</p>
    <p v-if="loading" class="font-body-sm text-body-sm text-outline">Memuat…</p>
    <p v-else-if="!items.length" class="font-body-sm text-body-sm text-outline">Tidak bergantung pada task lain.</p>

    <ul v-else class="flex flex-col gap-1">
      <li v-for="i in items" :key="i.id" class="flex items-center gap-space-xs">
        <span class="material-symbols-outlined shrink-0 text-[16px] text-on-surface-variant">subdirectory_arrow_right</span>
        <span class="min-w-0 flex-1 truncate font-body-sm text-body-sm" :class="i.prerequisite?.status === 'done' ? 'text-outline line-through' : 'text-on-surface'">{{ i.prerequisite?.title }}</span>
        <span class="shrink-0 rounded-full bg-secondary-container px-2 py-0.5 font-label-sm text-label-sm text-on-secondary-container">{{ STATUS[i.prerequisite?.status] }}</span>
        <button
          v-if="canEdit"
          type="button"
          :disabled="busy"
          class="shrink-0 rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-low disabled:opacity-60"
          title="Hapus ketergantungan"
          @click="remove(i)"
        >
          <span class="material-symbols-outlined text-[16px]">close</span>
        </button>
      </li>
    </ul>

    <div v-if="canEdit" class="flex gap-space-xs">
      <select
        v-model="selected"
        class="min-w-0 flex-1 rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
      >
        <option value="">Pilih task prasyarat…</option>
        <option v-for="t in candidates" :key="t.id" :value="t.id">{{ t.title }}</option>
      </select>
      <button
        type="button"
        :disabled="busy || !selected"
        class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
        @click="add"
      >
        Tambah
      </button>
    </div>
  </section>
</template>
