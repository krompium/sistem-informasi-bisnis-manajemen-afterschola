<script setup>
// Catatan Progres (Modul 8, TRN-08 / MGT-44). Satu halaman untuk dua peran:
// trainer menulis dan mengubah catatannya sendiri, Management hanya membaca. Aturan sebenarnya ditegakkan backend.
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatDateShort, unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canWrite = computed(() => auth.isTrainer && !auth.isManagement)

const inputCls = 'w-full rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'

const classes = ref([])
const roster = ref([])
const sessions = ref([])
const notes = ref([])
const classId = ref('')
const studentId = ref('')
const loading = ref(false)
const loadError = ref('')
const notice = ref('')

// Form catatan baru
const form = ref({ class_session_id: '', student_id: '', note: '' })
const saving = ref(false)
const formError = ref('')

// Ubah catatan sendiri
const editingId = ref(null)
const editText = ref('')

async function loadClasses() {
  try {
    const { data } = await api.get('/class-groups', { params: { per_page: 100, is_active: 1 } })
    classes.value = unwrap(data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat daftar kelas.')
  }
}

async function loadClassDetail() {
  roster.value = []
  sessions.value = []
  studentId.value = ''
  form.value = { class_session_id: '', student_id: '', note: '' }
  if (!classId.value) return
  try {
    const [r, s] = await Promise.all([
      api.get(`/class-groups/${classId.value}/enrollments`),
      api.get('/class-sessions', { params: { class_group_id: classId.value, per_page: 100 } }),
    ])
    roster.value = unwrap(r.data)
    // Sesi terbaru lebih dulu: trainer biasanya mencatat pertemuan yang baru selesai.
    sessions.value = unwrap(s.data).slice().reverse()
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat siswa dan sesi kelas.')
  }
}

async function loadNotes() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/progress-notes', {
      params: {
        class_group_id: classId.value || undefined,
        student_id: studentId.value || undefined,
        per_page: 50,
      },
    })
    notes.value = unwrap(data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat catatan progres.')
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  formError.value = ''
  notice.value = ''
  try {
    await api.post('/progress-notes', form.value)
    form.value.note = ''
    notice.value = 'Catatan tersimpan.'
    await loadNotes()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan catatan.')
  } finally {
    saving.value = false
  }
}

function startEdit(n) {
  editingId.value = n.id
  editText.value = n.note
  formError.value = ''
}

async function saveEdit(n) {
  formError.value = ''
  try {
    await api.put(`/progress-notes/${n.id}`, { note: editText.value })
    editingId.value = null
    await loadNotes()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal mengubah catatan.')
  }
}

const sessionLabel = (s) => `${formatDateShort(s.date)} · Pertemuan ${s.meeting_no}`
const isMine = (n) => n.trainer?.id === auth.user?.id

watch(classId, async () => {
  await loadClassDetail()
  await loadNotes()
})
watch(studentId, () => {
  form.value.student_id = studentId.value
  loadNotes()
})

onMounted(async () => {
  await loadClasses()
  await loadNotes()
})
</script>

<template>
  <div class="space-y-space-md">
    <div>
      <h1 class="font-headline-md text-headline-md text-on-surface">Catatan Progres</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        {{ canWrite ? 'Catat perkembangan siswa pada tiap sesi kelas Anda.' : 'Catatan progres siswa dari trainer di cabang Anda.' }}
      </p>
    </div>

    <p v-if="loadError" class="rounded-lg bg-error-container px-space-sm py-2 text-on-error-container">{{ loadError }}</p>

    <div class="grid gap-space-sm sm:grid-cols-2">
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Kelas</span>
        <select v-model="classId" :class="inputCls">
          <option value="">Semua kelas</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </label>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Siswa</span>
        <select v-model="studentId" :class="inputCls" :disabled="!classId">
          <option value="">Semua siswa</option>
          <option v-for="e in roster" :key="e.id" :value="e.student.id">{{ e.student.name }}</option>
        </select>
      </label>
    </div>

    <form v-if="canWrite && classId" class="space-y-space-sm rounded-xl border border-outline-variant bg-white p-space-md" @submit.prevent="submit">
      <h2 class="font-title-md text-title-md text-on-surface">Tambah catatan</h2>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Sesi</span>
        <select v-model="form.class_session_id" required :class="inputCls">
          <option value="" disabled>Pilih sesi</option>
          <option v-for="s in sessions" :key="s.id" :value="s.id">{{ sessionLabel(s) }}</option>
        </select>
      </label>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Siswa</span>
        <select v-model="form.student_id" required :class="inputCls">
          <option value="" disabled>Pilih siswa</option>
          <option v-for="e in roster" :key="e.id" :value="e.student.id">{{ e.student.name }}</option>
        </select>
      </label>
      <label class="block">
        <span class="font-label-md text-label-md text-on-surface-variant">Catatan</span>
        <textarea v-model="form.note" required rows="3" maxlength="2000" :class="inputCls" />
      </label>
      <p v-if="formError" class="text-error">{{ formError }}</p>
      <p v-if="notice" class="text-primary">{{ notice }}</p>
      <button type="submit" :disabled="saving" class="w-full rounded-lg bg-primary px-space-md py-2.5 font-label-md text-label-md text-on-primary disabled:opacity-60 sm:w-auto">
        {{ saving ? 'Menyimpan…' : 'Simpan catatan' }}
      </button>
    </form>

    <p v-if="loading" class="text-on-surface-variant">Memuat…</p>
    <p v-else-if="!notes.length" class="rounded-xl border border-dashed border-outline-variant p-space-md text-center text-on-surface-variant">
      Belum ada catatan.
    </p>

    <ul v-else class="space-y-space-sm">
      <li v-for="n in notes" :key="n.id" class="rounded-xl border border-outline-variant bg-white p-space-md">
        <div class="flex flex-wrap items-baseline justify-between gap-x-space-sm">
          <span class="font-title-md text-title-md text-on-surface">{{ n.student?.name }}</span>
          <span class="font-label-md text-label-md text-on-surface-variant">
            {{ formatDateShort(n.class_session?.date) }} · Pertemuan {{ n.class_session?.meeting_no }}
          </span>
        </div>
        <template v-if="editingId === n.id">
          <textarea v-model="editText" rows="3" maxlength="2000" :class="[inputCls, 'mt-space-xs']" />
          <div class="mt-space-xs flex gap-space-sm">
            <button class="rounded-lg bg-primary px-space-md py-2 text-on-primary" @click="saveEdit(n)">Simpan</button>
            <button class="rounded-lg border border-outline-variant px-space-md py-2" @click="editingId = null">Batal</button>
          </div>
        </template>
        <p v-else class="mt-space-xs whitespace-pre-line font-body-md text-body-md text-on-surface">{{ n.note }}</p>
        <div class="mt-space-xs flex items-center justify-between font-label-md text-label-md text-on-surface-variant">
          <span>oleh {{ n.trainer?.name }}</span>
          <button v-if="canWrite && isMine(n) && editingId !== n.id" class="text-primary underline" @click="startEdit(n)">Ubah</button>
        </div>
      </li>
    </ul>
  </div>
</template>
