<script setup>
// Board kanban satu proyek (Modul 12, FR-12.3 sampai FR-12.5).
// Developer mengelola task di team-nya; Management hanya melihat (backend yang menjaga akses).
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateShort, initials, unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'
import { usePolling } from '@/lib/usePolling'
import TaskActivity from './TaskActivity.vue'
import TaskAttachments from './TaskAttachments.vue'
import TaskChecklist from './TaskChecklist.vue'
import TaskDependencies from './TaskDependencies.vue'
import { confirmDialog } from '@/lib/feedback'

const auth = useAuthStore()
const route = useRoute()
const projectId = route.params.id

const canWrite = computed(() => auth.isDeveloper && !auth.isManagement)
const backPath = computed(() => (auth.isManagement ? '/management/proyek' : '/developer/proyek'))

const PRIORITIES = [
  { key: 'low', label: 'Rendah' },
  { key: 'medium', label: 'Sedang' },
  { key: 'high', label: 'Tinggi' },
]
const CATEGORIES = [
  { key: 'feature', label: 'Fitur' },
  { key: 'bug', label: 'Bug' },
  { key: 'docs', label: 'Dokumentasi' },
  { key: 'other', label: 'Lainnya' },
]
const categoryLabel = (key) => CATEGORIES.find((c) => c.key === key)?.label ?? key
const COLUMNS = [
  { key: 'todo', label: 'To Do', icon: 'radio_button_unchecked' },
  { key: 'in_progress', label: 'In Progress', icon: 'pending' },
  { key: 'review', label: 'Review', icon: 'rate_review' },
  { key: 'done', label: 'Done', icon: 'check_circle' },
]

const PROJECT_STATUS = {
  planning: { label: 'Perencanaan', cls: 'bg-surface-container text-on-surface-variant' },
  ongoing: { label: 'Berjalan', cls: 'bg-primary-fixed text-primary' },
  done: { label: 'Selesai', cls: 'bg-[#D1FAE5] text-[#065F46]' },
}

const loading = ref(true)
const errorMessage = ref('')
const project = ref(null)
const team = ref(null)
const tasks = ref([])

const moveError = ref('')
const movingId = ref(null)

const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ title: '', description: '', assignee_id: '', status: 'todo', progress: 0, priority: 'medium', category: 'feature', start_date: '', due_date: '', github_url: '' })
// Laporan progres (Excel/PDF): format yang sedang diunduh.
const exporting = ref('')
const exportError = ref('')
// Task yang dibuka di modal detail read-only (Management).
const detail = ref(null)

const members = computed(() => team.value?.members ?? [])
// Filter assignee: '' = semua, 'none' = tanpa assignee, selain itu id user.
const assigneeFilter = ref('')
const priorityFilter = ref('')
const categoryFilter = ref('')
const visibleTasks = computed(() =>
  tasks.value.filter((t) => {
    if (priorityFilter.value && t.priority !== priorityFilter.value) return false
    if (categoryFilter.value && t.category !== categoryFilter.value) return false
    if (!assigneeFilter.value) return true
    if (assigneeFilter.value === 'none') return !t.assignee_id
    return String(t.assignee_id) === assigneeFilter.value
  }),
)
const columns = computed(() => COLUMNS.map((c) => ({ ...c, tasks: visibleTasks.value.filter((t) => t.status === c.key) })))
// Tanggal lokal (bukan UTC), supaya task jatuh tempo hari ini tidak salah ditandai di dini hari WIB.
const today = new Date().toLocaleDateString('en-CA') // format YYYY-MM-DD
const isOverdue = (t) => t.status !== 'done' && t.due_date < today

async function loadTasks() {
  // TODO(nanti): maksimal 100 task per proyek ditampilkan — tambah paginasi bila ada proyek sebesar itu.
  const { data } = await api.get('/tasks', { params: { project_id: projectId, per_page: 100 } })
  tasks.value = unwrap(data)
}

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await api.get(`/projects/${projectId}`)
    project.value = data.data
    const [, teamRes] = await Promise.all([loadTasks(), api.get(`/teams/${project.value.team_id}`)])
    team.value = teamRes.data.data
  } catch (err) {
    errorMessage.value =
      err?.response?.status === 404 ? 'Proyek tidak ditemukan.' : 'Gagal memuat board.'
  } finally {
    loading.value = false
  }
}

// Pindah kolom lewat select (tanpa drag-and-drop). TODO(nanti): drag-and-drop bila diminta.
async function moveTask(task, event) {
  const status = event.target.value
  if (status === task.status) return
  moveError.value = ''
  movingId.value = task.id
  try {
    const { data } = await api.put(`/tasks/${task.id}`, { status })
    Object.assign(task, data.data)
  } catch (err) {
    event.target.value = task.status
    moveError.value = apiErrorMessage(err, 'Gagal memindahkan task.')
  } finally {
    movingId.value = null
  }
}

function openCreate(status = 'todo') {
  editing.value = null
  Object.assign(form, {
    title: '',
    description: '',
    assignee_id: members.value.length === 1 ? members.value[0].id : '',
    status,
    progress: 0,
    priority: 'medium',
    category: 'feature',
    start_date: '',
    due_date: project.value?.due_date ?? '',
    github_url: '',
  })
  formError.value = ''
  showModal.value = true
}

function openEdit(t) {
  editing.value = t
  Object.assign(form, {
    title: t.title,
    description: t.description ?? '',
    assignee_id: t.assignee_id ?? '',
    status: t.status,
    progress: t.progress,
    priority: t.priority ?? 'medium',
    category: t.category ?? 'feature',
    start_date: t.start_date ?? '',
    due_date: t.due_date,
    github_url: t.github_url ?? '',
  })
  formError.value = ''
  showModal.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  const payload = {
    title: form.title,
    description: form.description,
    assignee_id: form.assignee_id,
    status: form.status,
    progress: form.progress,
    priority: form.priority,
    category: form.category,
    start_date: form.start_date || null,
    due_date: form.due_date,
    github_url: form.github_url || null,
  }
  try {
    if (editing.value) {
      await api.put(`/tasks/${editing.value.id}`, payload)
    } else {
      await api.post('/tasks', { ...payload, project_id: projectId })
    }
    showModal.value = false
    await loadTasks()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan task.')
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!(await confirmDialog(`Hapus task "${editing.value.title}"?`, { title: 'Hapus data?', danger: true, confirmText: 'Ya, hapus' }))) return
  saving.value = true
  formError.value = ''
  try {
    await api.delete(`/tasks/${editing.value.id}`)
    showModal.value = false
    await loadTasks()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menghapus task.')
  } finally {
    saving.value = false
  }
}

onMounted(load)

// Refresh berkala agar perubahan rekan tim terlihat tanpa muat ulang (dilewati saat memindah task atau modal terbuka).
// TODO(nanti): ganti dengan push sungguhan (websocket) bila diperlukan; butuh paket baru dan persetujuan.
async function downloadReport(format) {
  exporting.value = format
  exportError.value = ''
  try {
    const res = await api.get(`/projects/${projectId}/export`, { params: { format }, responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data]))
    const cd = res.headers['content-disposition'] || ''
    const m = /filename="?([^";]+)"?/.exec(cd)
    link.download = m ? m[1] : `laporan-proyek.${format}`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch {
    exportError.value = 'Gagal mengunduh laporan. Coba lagi.'
  } finally {
    exporting.value = ''
  }
}

usePolling(() => (movingId.value === null && !showModal.value && !detail.value ? loadTasks() : null), 15000)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="pt-space-lg">
      <router-link :to="backPath" class="inline-flex items-center gap-1 font-label-md text-label-md font-semibold text-primary hover:underline">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span> Kembali ke daftar proyek
      </router-link>
    </div>

    <div v-if="errorMessage" class="my-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-else-if="loading" class="mt-space-lg grid grid-cols-1 gap-space-md lg:grid-cols-4">
      <div v-for="i in 4" :key="i" class="h-64 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <template v-else-if="project">
      <div class="flex flex-col justify-between gap-space-md py-space-lg lg:flex-row lg:items-center">
        <div class="flex min-w-0 flex-col gap-space-2xs">
          <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">{{ team?.name || 'Team' }}</span>
          <div class="flex flex-wrap items-center gap-space-sm">
            <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">{{ project.name }}</h1>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-label-sm text-label-sm font-semibold" :class="PROJECT_STATUS[project.status]?.cls">
              {{ PROJECT_STATUS[project.status]?.label || project.status }}
            </span>
          </div>
          <p v-if="project.description" class="font-body-md text-body-md text-on-surface-variant">{{ project.description }}</p>
          <span class="flex items-center gap-1 font-label-md text-label-md text-outline">
            <span class="material-symbols-outlined text-[16px]">event</span>
            {{ formatDateShort(project.start_date) }} – {{ formatDateShort(project.due_date) }}
          </span>
        </div>
        <div class="flex flex-wrap items-center gap-space-sm self-start lg:self-center">
          <router-link
            :to="`${backPath}/${projectId}/gantt`"
            class="inline-flex items-center gap-space-xs rounded-xl border border-outline-variant bg-white px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low active:scale-95"
          >
            <span class="material-symbols-outlined text-[18px]">view_timeline</span>
            <span>Gantt</span>
          </router-link>
          <button
            v-for="f in [{ key: 'xlsx', label: 'Excel', icon: 'table_view' }, { key: 'pdf', label: 'PDF', icon: 'picture_as_pdf' }]"
            :key="f.key"
            type="button"
            :disabled="exporting !== ''"
            class="inline-flex items-center gap-space-xs rounded-xl border border-outline-variant bg-white px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low active:scale-95 disabled:opacity-60"
            @click="downloadReport(f.key)"
          >
            <span class="material-symbols-outlined text-[18px]">{{ exporting === f.key ? 'hourglass_top' : f.icon }}</span>
            <span>{{ f.label }}</span>
          </button>
        <button
          v-if="canWrite"
          class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
          @click="openCreate()"
        >
          <span class="material-symbols-outlined text-[18px]">add_task</span>
          <span>Task Baru</span>
        </button>
        </div>
      </div>

      <p v-if="exportError" class="mb-space-sm rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ exportError }}</p>
      <p v-if="moveError" class="mb-space-sm rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">{{ moveError }}</p>

      <div class="mb-space-sm flex flex-wrap items-center gap-space-sm">
        <label for="assignee-filter" class="font-label-md text-label-md text-on-surface-variant">Assignee</label>
        <select
          id="assignee-filter"
          v-model="assigneeFilter"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
        >
          <option value="">Semua</option>
          <option v-for="m in members" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
          <option value="none">Tanpa assignee</option>
        </select>
        <label for="priority-filter" class="ml-space-sm font-label-md text-label-md text-on-surface-variant">Prioritas</label>
        <select
          id="priority-filter"
          v-model="priorityFilter"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
        >
          <option value="">Semua</option>
          <option v-for="p in PRIORITIES" :key="p.key" :value="p.key">{{ p.label }}</option>
        </select>
        <label for="category-filter" class="ml-space-sm font-label-md text-label-md text-on-surface-variant">Kategori</label>
        <select
          id="category-filter"
          v-model="categoryFilter"
          class="rounded-lg border border-outline-variant bg-white px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"
        >
          <option value="">Semua</option>
          <option v-for="c in CATEGORIES" :key="c.key" :value="c.key">{{ c.label }}</option>
        </select>
      </div>

      <div class="grid grid-cols-1 gap-space-md lg:grid-cols-4">
        <section v-for="c in columns" :key="c.key" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-low p-space-sm">
          <div class="flex items-center justify-between px-space-xs py-space-xs">
            <h2 class="flex items-center gap-space-xs font-headline-sm text-headline-sm font-bold text-on-surface">
              <span class="material-symbols-outlined text-[18px] text-outline">{{ c.icon }}</span>{{ c.label }}
            </h2>
            <span class="rounded-full bg-surface-container-lowest px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-surface-variant">{{ c.tasks.length }}</span>
          </div>

          <p v-if="!c.tasks.length" class="px-space-xs py-space-md text-center font-body-sm text-body-sm text-outline">Belum ada task</p>

          <article v-for="t in c.tasks" :key="t.id" class="flex flex-col gap-space-xs rounded-xl bg-surface-container-lowest p-space-md shadow-sm">
            <div class="flex items-start justify-between gap-space-xs">
              <button type="button" class="min-w-0 text-left" @click="canWrite ? openEdit(t) : (detail = t)">
                <h3 class="font-label-lg text-label-lg font-semibold text-on-surface hover:underline">{{ t.title }}</h3>
              </button>
              <a
                v-if="t.github_url?.startsWith('https://github.com/')"
                :href="t.github_url"
                target="_blank"
                rel="noopener noreferrer"
                class="-m-1 shrink-0 rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-low"
                title="Buka di GitHub"
              >
                <span class="material-symbols-outlined text-[18px]">link</span>
              </a>
              <button v-if="canWrite" class="-m-1 shrink-0 rounded-lg p-1 text-on-surface-variant hover:bg-surface-container-low" title="Ubah task" @click="openEdit(t)">
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
            </div>
            <p v-if="t.description" class="line-clamp-2 font-body-sm text-body-sm text-on-surface-variant">{{ t.description }}</p>
            <span
              v-if="t.priority && t.priority !== 'medium'"
              class="self-start rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
              :class="t.priority === 'high' ? 'bg-error-container text-on-error-container' : 'bg-surface-container text-on-surface-variant'"
            >Prioritas {{ t.priority === 'high' ? 'tinggi' : 'rendah' }}</span>
            <div class="flex flex-wrap items-center gap-1">
              <span
                class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
                :class="t.category === 'bug' ? 'bg-error-container text-on-error-container' : 'bg-secondary-container text-on-secondary-container'"
              >{{ categoryLabel(t.category) }}</span>
              <span v-if="t.checklist_total > 0" class="inline-flex items-center gap-0.5 font-label-sm text-label-sm text-on-surface-variant" title="Checklist">
                <span class="material-symbols-outlined text-[14px]">checklist</span>{{ t.checklist_done }}/{{ t.checklist_total }}
              </span>
              <span v-if="t.attachments_count > 0" class="inline-flex items-center gap-0.5 font-label-sm text-label-sm text-on-surface-variant" title="Lampiran">
                <span class="material-symbols-outlined text-[14px]">attach_file</span>{{ t.attachments_count }}
              </span>
              <span v-if="t.blocked_count > 0 && t.status !== 'done'" class="inline-flex items-center gap-0.5 font-label-sm text-label-sm font-semibold text-error" title="Menunggu task prasyarat selesai">
                <span class="material-symbols-outlined text-[14px]">lock</span>Terblokir
              </span>
            </div>

            <div class="flex items-center gap-space-xs">
              <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-container">
                <div class="h-full rounded-full bg-primary-container" :style="{ width: t.progress + '%' }"></div>
              </div>
              <span class="font-label-sm text-label-sm text-on-surface-variant">{{ t.progress }}%</span>
            </div>

            <div class="flex items-center justify-between gap-space-xs">
              <span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant" :title="t.assignee?.name || 'Tanpa assignee'">
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-primary-fixed text-[10px] font-bold text-primary">{{ t.assignee ? initials(t.assignee.name) : '?' }}</span>
                {{ t.assignee?.name || 'Tanpa assignee' }}
              </span>
              <span class="inline-flex items-center gap-1 font-label-sm text-label-sm" :class="isOverdue(t) ? 'font-semibold text-error' : 'text-outline'">
                <span v-if="isOverdue(t)" class="material-symbols-outlined text-[14px]">warning</span>
                {{ formatDateShort(t.due_date) }}<template v-if="isOverdue(t)"> · Terlambat</template>
              </span>
            </div>

            <select
              v-if="canWrite"
              :value="t.status"
              :disabled="movingId === t.id"
              class="mt-space-xs rounded-lg border border-outline-variant bg-white px-space-sm py-1.5 font-body-sm text-body-sm text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15 disabled:opacity-60"
              @change="moveTask(t, $event)"
            >
              <option v-for="col in COLUMNS" :key="col.key" :value="col.key">{{ col.label }}</option>
            </select>
          </article>
        </section>
      </div>
    </template>

    <!-- Modal buat / ubah task -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showModal = false">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Task' : 'Task Baru' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showModal = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="overflow-y-auto">
        <form class="flex flex-col gap-space-md p-space-lg" @submit.prevent="submit">
          <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Judul</span>
            <input v-model="form.title" type="text" required maxlength="255" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Deskripsi <span class="font-normal text-outline">(opsional)</span></span>
            <textarea v-model="form.description" rows="2" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15"></textarea>
          </label>
          <div class="grid grid-cols-2 gap-space-md">
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Assignee</span>
              <select v-model="form.assignee_id" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option value="" disabled>Pilih anggota…</option>
                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Status</span>
              <select v-model="form.status" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
                <option v-for="col in COLUMNS" :key="col.key" :value="col.key">{{ col.label }}</option>
              </select>
            </label>
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Progres: {{ form.progress }}%</span>
            <input v-model.number="form.progress" type="range" min="0" max="100" step="1" class="w-full accent-primary-container" />
          </label>
          <div class="grid grid-cols-2 gap-space-md">
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mulai <span class="font-normal text-outline">(opsional)</span></span>
            <input v-model="form.start_date" type="date" :max="form.due_date || undefined" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tenggat</span>
            <input v-model="form.due_date" type="date" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Prioritas</span>
            <select v-model="form.priority" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
              <option v-for="p in PRIORITIES" :key="p.key" :value="p.key">{{ p.label }}</option>
            </select>
          </label>
          </div>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kategori</span>
            <select v-model="form.category" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15">
              <option v-for="c in CATEGORIES" :key="c.key" :value="c.key">{{ c.label }}</option>
            </select>
          </label>
          <label class="flex flex-col gap-1">
            <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tautan GitHub <span class="font-normal text-outline">(opsional, URL PR atau issue)</span></span>
            <input v-model="form.github_url" type="url" maxlength="500" placeholder="https://github.com/…" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15" />
          </label>

          <div class="flex items-center justify-between gap-space-sm border-t border-surface-container pt-space-md">
            <button v-if="editing" type="button" :disabled="saving" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-error hover:bg-error-container/50 disabled:opacity-60" @click="remove">Hapus</button>
            <span v-else></span>
            <div class="flex items-center gap-space-sm">
              <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showModal = false">Batal</button>
              <button type="submit" :disabled="saving" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
                <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                Simpan
              </button>
            </div>
          </div>
        </form>
        <TaskChecklist v-if="editing" :key="'c' + editing.id" :task-id="editing.id" can-edit @changed="loadTasks" />
        <TaskDependencies v-if="editing" :key="'d' + editing.id" :task-id="editing.id" :project-tasks="tasks" can-edit @changed="loadTasks" />
        <TaskAttachments v-if="editing" :key="'a' + editing.id" :task-id="editing.id" can-edit @changed="loadTasks" />
        <TaskActivity v-if="editing" :key="editing.id" :task-id="editing.id" can-comment />
        </div>
      </div>
    </div>

    <!-- Modal detail task (read-only, untuk Management) -->
    <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="detail = null">
      <div class="flex max-h-[92vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between gap-space-sm border-b border-surface-container p-space-lg">
          <h2 class="min-w-0 break-words font-headline-md text-headline-md font-bold text-on-surface">{{ detail.title }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="detail = null">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="overflow-y-auto">
          <div class="flex flex-col gap-space-sm p-space-lg font-body-sm text-body-sm text-on-surface">
            <p v-if="detail.description" class="whitespace-pre-line text-on-surface-variant">{{ detail.description }}</p>
            <p><span class="font-semibold">Assignee:</span> {{ detail.assignee?.name || 'Tanpa assignee' }}</p>
            <p><span class="font-semibold">Status:</span> {{ COLUMNS.find((c) => c.key === detail.status)?.label }} · {{ detail.progress }}%</p>
            <p><span class="font-semibold">Prioritas:</span> {{ PRIORITIES.find((p) => p.key === detail.priority)?.label }}</p>
            <p><span class="font-semibold">Kategori:</span> {{ categoryLabel(detail.category) }}</p>
            <p v-if="detail.start_date"><span class="font-semibold">Mulai:</span> {{ formatDateShort(detail.start_date) }}</p>
            <p><span class="font-semibold">Tenggat:</span> {{ formatDateShort(detail.due_date) }}</p>
            <a
              v-if="detail.github_url?.startsWith('https://github.com/')"
              :href="detail.github_url"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 break-all text-primary hover:underline"
            >
              <span class="material-symbols-outlined text-[16px]">link</span>{{ detail.github_url }}
            </a>
          </div>
          <TaskChecklist :key="'c' + detail.id" :task-id="detail.id" />
          <TaskDependencies :key="'d' + detail.id" :task-id="detail.id" :project-tasks="tasks" />
          <TaskAttachments :key="'a' + detail.id" :task-id="detail.id" />
          <TaskActivity :key="detail.id" :task-id="detail.id" />
        </div>
      </div>
    </div>
  </div>
</template>
