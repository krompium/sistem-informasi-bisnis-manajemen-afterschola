<script setup>
// Jadwal Sesi (Modul 6c, MGT-34/35/36) — sesi kelas reguler per tanggal, dengan cek bentrok trainer di backend.
// Trainer pengganti = pelaksana yang bukan trainer penugasan kelas; ditandai "Pengganti".
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canManage = computed(() => auth.can('manage schedule'))

const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const today = new Date()
const inThirtyDays = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 30)

const loading = ref(true)
const loadError = ref('')
const sessions = ref([])
const meta = ref(null)
const page = ref(1)
const classes = ref([])
const options = reactive({ trainers: [] })
const filters = reactive({ class_group_id: '', trainer_id: '', from: ymd(today), to: ymd(inThirtyDays) })
const inputCls = 'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'

const dayLabel = (d) => new Date(`${d}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
const grouped = computed(() => {
  const byDate = new Map()
  for (const s of sessions.value) byDate.set(s.date, [...(byDate.get(s.date) ?? []), s])
  return [...byDate.entries()].map(([date, items]) => ({ date, items }))
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/class-sessions', {
      params: {
        page: page.value,
        per_page: 50,
        class_group_id: filters.class_group_id || undefined,
        trainer_id: filters.trainer_id || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
      },
    })
    sessions.value = unwrap(data)
    meta.value = data.meta ?? null
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat jadwal.')
  } finally {
    loading.value = false
  }
}

async function loadChoices() {
  try {
    const [cls, opts] = await Promise.all([
      api.get('/class-groups', { params: { is_active: 1, per_page: 100 } }),
      api.get('/class-groups/options'),
    ])
    classes.value = unwrap(cls.data)
    options.trainers = opts.data.data.trainers
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat pilihan kelas dan trainer.')
  }
}

// --- Form sesi ---
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref('')
const form = reactive({ class_group_id: '', date: ymd(today), start_time: '09:00', end_time: '10:30', meeting_no: 1, mode: 'onsite', trainer_id: '', note: '' })
const busySlots = ref([]) // sesi trainer terpilih pada tanggal terpilih (bantuan memilih, MGT-35)

const formClass = computed(() => classes.value.find((c) => c.id === form.class_group_id) ?? null)
// Trainer ditugaskan di kelas didahulukan; sisanya (satu cabang) dapat menjadi pengganti.
const trainerGroups = computed(() => {
  if (!formClass.value) return { assigned: [], substitutes: [] }
  const assignedIds = new Set(formClass.value.assignments.map((a) => a.trainer.id))
  const inBranch = options.trainers.filter((t) => t.branch_ids.includes(formClass.value.branch.id))
  return { assigned: inBranch.filter((t) => assignedIds.has(t.id)), substitutes: inBranch.filter((t) => !assignedIds.has(t.id)) }
})

function openForm(session = null) {
  editing.value = session
  Object.assign(form, session
    ? { class_group_id: session.class_group.id, date: session.date, start_time: session.start_time, end_time: session.end_time, meeting_no: session.meeting_no, mode: session.mode, trainer_id: session.trainer.id, note: session.note ?? '' }
    : { class_group_id: '', date: ymd(today), start_time: '09:00', end_time: '10:30', meeting_no: 1, mode: 'onsite', trainer_id: '', note: '' })
  formError.value = ''
  busySlots.value = []
  showForm.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  const body = { ...form, note: form.note || null }
  try {
    if (editing.value) {
      delete body.class_group_id // kelas tidak bisa dipindah
      await api.put(`/class-sessions/${editing.value.id}`, body)
    } else {
      await api.post('/class-sessions', body)
    }
    showForm.value = false
    await load()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan sesi.')
  } finally {
    saving.value = false
  }
}

watch(() => form.class_group_id, () => {
  if (![...trainerGroups.value.assigned, ...trainerGroups.value.substitutes].some((t) => t.id === form.trainer_id)) form.trainer_id = ''
})
watch([() => form.trainer_id, () => form.date], async ([trainerId, date]) => {
  busySlots.value = []
  if (!showForm.value || !trainerId || !date) return
  try {
    const { data } = await api.get('/class-sessions', { params: { trainer_id: trainerId, from: date, to: date } })
    busySlots.value = unwrap(data).filter((s) => s.id !== editing.value?.id)
  } catch (err) {
    // non-fatal: bentrok tetap dicek backend saat disimpan
  }
})
watch(filters, () => {
  page.value = 1
  load()
})

onMounted(() => {
  loadChoices()
  load()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Kelas dan Penjadwalan</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Jadwal Sesi</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Sesi kelas reguler per tanggal. Trainer tidak boleh punya dua sesi dengan jam bertabrakan.</p>
      </div>
      <button
        v-if="canManage"
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openForm()"
      >
        <span class="material-symbols-outlined text-[18px]">add</span>
        <span>Tambah Sesi</span>
      </button>
    </div>

    <div class="mb-space-md flex flex-wrap items-center gap-space-sm">
      <input v-model="filters.from" type="date" :class="inputCls" aria-label="Dari tanggal" />
      <input v-model="filters.to" type="date" :class="inputCls" aria-label="Sampai tanggal" />
      <select v-model="filters.class_group_id" :class="inputCls">
        <option value="">Semua kelas</option>
        <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-model="filters.trainer_id" :class="inputCls">
        <option value="">Semua trainer</option>
        <option v-for="t in options.trainers" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-surface-container"></div>
    </div>
    <div v-else-if="loadError" class="rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">{{ loadError }}</div>
    <div v-else-if="!sessions.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">event</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada sesi pada rentang ini</p>
    </div>

    <div v-else class="flex flex-col gap-space-md">
      <section v-for="day in grouped" :key="day.date">
        <h2 class="mb-space-xs font-label-lg text-label-lg font-semibold text-on-surface-variant">{{ dayLabel(day.date) }}</h2>
        <div class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
          <div v-for="s in day.items" :key="s.id" class="flex flex-col gap-space-xs border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center">
            <div class="flex flex-col gap-1">
              <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ s.start_time }}–{{ s.end_time }} • {{ s.class_group.name }}</span>
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                Pertemuan ke-{{ s.meeting_no }} • {{ s.mode === 'online' ? 'Online' : 'Onsite' }} • {{ s.trainer.name }}
              </span>
            </div>
            <div class="flex items-center gap-space-xs sm:ml-auto">
              <span v-if="s.is_substitute" class="rounded-full bg-secondary-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-secondary-container">Pengganti</span>
              <span v-if="s.is_locked" class="material-symbols-outlined text-[18px] text-outline" title="Sesi dikunci">lock</span>
              <button v-else-if="canManage" class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah sesi" @click="openForm(s)">
                <span class="material-symbols-outlined text-[18px]">edit</span>
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-space-md flex items-center justify-center gap-space-sm">
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page <= 1" @click="page--; load()">Sebelumnya</button>
      <span class="font-body-sm text-body-sm text-on-surface-variant">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page >= meta.last_page" @click="page++; load()">Berikutnya</button>
    </div>

    <!-- Modal tambah / ubah sesi -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showForm = false">
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Sesi' : 'Tambah Sesi' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showForm = false"><span class="material-symbols-outlined">close</span></button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div class="flex flex-col gap-space-md">
            <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
              <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kelas</span>
              <select v-model.number="form.class_group_id" required :disabled="!!editing" :class="[inputCls, 'disabled:opacity-60']">
                <option value="" disabled>Pilih kelas</option>
                <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }} • {{ c.branch.name }}</option>
              </select>
            </label>

            <div class="grid grid-cols-1 gap-space-md sm:grid-cols-3">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Tanggal</span>
                <input v-model="form.date" type="date" required :class="inputCls" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mulai</span>
                <input v-model="form.start_time" type="time" required :class="inputCls" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Selesai</span>
                <input v-model="form.end_time" type="time" required :class="inputCls" />
              </label>
            </div>

            <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Pertemuan ke-</span>
                <input v-model.number="form.meeting_no" type="number" min="1" max="200" required :class="inputCls" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Mode</span>
                <select v-model="form.mode" :class="inputCls">
                  <option value="onsite">Onsite</option>
                  <option value="online">Online</option>
                </select>
              </label>
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Trainer pelaksana</span>
              <select v-model.number="form.trainer_id" required :disabled="!form.class_group_id" :class="[inputCls, 'disabled:opacity-60']">
                <option value="" disabled>{{ form.class_group_id ? 'Pilih trainer' : 'Pilih kelas dulu' }}</option>
                <optgroup v-if="trainerGroups.assigned.length" label="Ditugaskan di kelas">
                  <option v-for="t in trainerGroups.assigned" :key="t.id" :value="t.id">{{ t.name }}</option>
                </optgroup>
                <optgroup v-if="trainerGroups.substitutes.length" label="Pengganti (tidak ditugaskan)">
                  <option v-for="t in trainerGroups.substitutes" :key="t.id" :value="t.id">{{ t.name }}</option>
                </optgroup>
              </select>
              <span v-if="busySlots.length" class="font-body-sm text-body-sm text-on-surface-variant">
                Sesi trainer ini pada tanggal tersebut: {{ busySlots.map((b) => `${b.start_time}–${b.end_time} (${b.class_group.name})`).join(', ') }}
              </span>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Catatan (opsional)</span>
              <textarea v-model="form.note" rows="2" maxlength="500" :class="inputCls"></textarea>
            </label>
          </div>

          <div class="mt-space-lg flex items-center justify-end gap-space-sm">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="showForm = false">Batal</button>
            <button type="submit" :disabled="saving" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Simpan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
