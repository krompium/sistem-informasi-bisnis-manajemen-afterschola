<script setup>
// Kelas (Modul 6a, MGT-30/33) — kelas reguler per program, level, dan cabang, lengkap dengan penugasan trainer.
// Menulis kelas butuh `manage classes`, menugaskan trainer butuh `assign trainers` (backend tetap otoritas).
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const canManage = computed(() => auth.can('manage classes'))
const canAssign = computed(() => auth.can('assign trainers'))

const loading = ref(true)
const loadError = ref('')
const groups = ref([])
const meta = ref(null)
const page = ref(1)
const options = reactive({ branches: [], programs: [], trainers: [] })
const filters = reactive({ branch_id: '', program_id: '', is_active: '1' })

const roleLabel = { utama: 'Utama', asisten: 'Asisten' }
const inputCls = 'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'

// --- Daftar ---
async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/class-groups', {
      params: {
        page: page.value,
        branch_id: filters.branch_id || undefined,
        program_id: filters.program_id || undefined,
        is_active: filters.is_active === '' ? undefined : filters.is_active,
      },
    })
    groups.value = unwrap(data)
    meta.value = data.meta ?? null
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat kelas.')
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const { data } = await api.get('/class-groups/options')
    Object.assign(options, data.data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat pilihan cabang, program, dan trainer.')
  }
}

// --- Form kelas ---
const showForm = ref(false)
const editing = ref(null) // null = tambah baru
const saving = ref(false)
const formError = ref('')
const form = reactive({ branch_id: '', program_id: '', level_id: '', name: '', capacity: 10, is_active: true })
const levelChoices = computed(() => options.programs.find((p) => p.id === form.program_id)?.levels ?? [])

function openForm(group = null) {
  editing.value = group
  Object.assign(form, {
    branch_id: group?.branch.id ?? (options.branches.length === 1 ? options.branches[0].id : ''),
    program_id: group?.program.id ?? '',
    level_id: group?.level.id ?? '',
    name: group?.name ?? '',
    capacity: group?.capacity ?? 10,
    is_active: group?.is_active ?? true,
  })
  formError.value = ''
  showForm.value = true
}

const payloadOf = (g) => ({
  branch_id: g.branch_id, program_id: g.program_id, level_id: g.level_id,
  name: g.name, capacity: g.capacity, is_active: g.is_active,
})

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    if (editing.value) await api.put(`/class-groups/${editing.value.id}`, payloadOf(form))
    else await api.post('/class-groups', payloadOf(form))
    showForm.value = false
    await load()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan kelas.')
  } finally {
    saving.value = false
  }
}

async function toggleActive(group) {
  try {
    await api.put(`/class-groups/${group.id}`, payloadOf({
      branch_id: group.branch.id, program_id: group.program.id, level_id: group.level.id,
      name: group.name, capacity: group.capacity, is_active: !group.is_active,
    }))
    await load()
  } catch (err) {
    window.alert(apiErrorMessage(err, 'Gagal mengubah status kelas.'))
  }
}

// --- Penugasan trainer ---
const assigning = ref(null) // kelas yang sedang diatur trainernya
const assignForm = reactive({ trainer_id: '', role: 'utama' })
const assignError = ref('')
const assignBusy = ref(false)

const trainerChoices = computed(() => {
  if (!assigning.value) return []
  const taken = new Set(assigning.value.assignments.map((a) => a.trainer.id))
  return options.trainers.filter((t) => t.branch_ids.includes(assigning.value.branch.id) && !taken.has(t.id))
})

function openAssign(group) {
  assigning.value = group
  Object.assign(assignForm, { trainer_id: '', role: 'utama' })
  assignError.value = ''
}

// Setelah tiap perubahan, muat ulang daftar lalu sambungkan lagi panel ke data terbaru.
async function reloadAssigning() {
  await load()
  assigning.value = groups.value.find((g) => g.id === assigning.value.id) ?? null
}

async function runAssign(request) {
  assignBusy.value = true
  assignError.value = ''
  try {
    await request()
    await reloadAssigning()
  } catch (err) {
    assignError.value = apiErrorMessage(err, 'Gagal menyimpan penugasan.')
  } finally {
    assignBusy.value = false
  }
}

const addTrainer = () => runAssign(async () => {
  await api.post('/trainer-assignments', { class_group_id: assigning.value.id, trainer_id: assignForm.trainer_id, role: assignForm.role })
  assignForm.trainer_id = ''
})
const changeRole = (a, role) => runAssign(() => api.put(`/trainer-assignments/${a.id}`, { role }))
const removeAssignment = (a) => {
  if (!window.confirm(`Lepas ${a.trainer.name} dari kelas ini?`)) return
  runAssign(() => api.delete(`/trainer-assignments/${a.id}`))
}

watch(() => form.program_id, () => {
  // Level yang bukan milik program terpilih dikosongkan.
  if (!levelChoices.value.some((l) => l.id === form.level_id)) form.level_id = ''
})
watch(filters, () => {
  page.value = 1
  load()
})

onMounted(() => {
  loadOptions()
  load()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Kelas dan Penjadwalan</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Kelas</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Kelas reguler per program, level, dan cabang, beserta trainer yang bertugas.</p>
      </div>
      <button
        v-if="canManage"
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openForm()"
      >
        <span class="material-symbols-outlined text-[18px]">add</span>
        <span>Tambah Kelas</span>
      </button>
    </div>

    <div class="mb-space-md flex flex-wrap items-center gap-space-sm">
      <select v-model="filters.is_active" :class="inputCls">
        <option value="1">Aktif</option>
        <option value="0">Diarsipkan</option>
        <option value="">Semua</option>
      </select>
      <select v-if="options.branches.length > 1" v-model="filters.branch_id" :class="inputCls">
        <option value="">Semua cabang</option>
        <option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <select v-model="filters.program_id" :class="inputCls">
        <option value="">Semua program</option>
        <option v-for="p in options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-surface-container"></div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">{{ loadError }}</div>

    <div v-else-if="!groups.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">school</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada kelas</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div v-for="g in groups" :key="g.id" class="flex flex-col gap-space-sm border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center">
        <div class="flex flex-col gap-1">
          <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ g.name }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ g.program.name }} • {{ g.level.name }} • {{ g.branch.name }} • Kapasitas {{ g.capacity }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">
            <template v-if="g.assignments.length">
              <span v-for="(a, i) in g.assignments" :key="a.id">{{ i ? ', ' : '' }}{{ a.trainer.name }} ({{ roleLabel[a.role] }})</span>
            </template>
            <template v-else>Belum ada trainer</template>
          </span>
        </div>

        <div class="flex items-center gap-space-xs sm:ml-auto">
          <span v-if="!g.is_active" class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-outline">Diarsipkan</span>
          <button v-if="canAssign" class="rounded-lg p-1.5 text-primary hover:bg-surface-container" title="Atur trainer" @click="openAssign(g)">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
          </button>
          <button v-if="canManage" class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah kelas" @click="openForm(g)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button v-if="canManage" class="rounded-lg p-1.5 text-primary hover:bg-surface-container" :title="g.is_active ? 'Arsipkan kelas' : 'Aktifkan kelas'" @click="toggleActive(g)">
            <span class="material-symbols-outlined text-[18px]">{{ g.is_active ? 'inventory_2' : 'unarchive' }}</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-space-md flex items-center justify-center gap-space-sm">
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page <= 1" @click="page--; load()">Sebelumnya</button>
      <span class="font-body-sm text-body-sm text-on-surface-variant">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page >= meta.last_page" @click="page++; load()">Berikutnya</button>
    </div>

    <!-- Modal tambah / ubah kelas -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="showForm = false">
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Kelas' : 'Tambah Kelas' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showForm = false"><span class="material-symbols-outlined">close</span></button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div class="flex flex-col gap-space-md">
            <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
              <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Cabang</span>
              <select v-model.number="form.branch_id" required :class="inputCls">
                <option value="" disabled>Pilih cabang</option>
                <option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Program</span>
              <select v-model.number="form.program_id" required :class="inputCls">
                <option value="" disabled>Pilih program</option>
                <option v-for="p in options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Level</span>
              <select v-model.number="form.level_id" required :disabled="!form.program_id" :class="[inputCls, 'disabled:opacity-60']">
                <option value="" disabled>{{ form.program_id ? 'Pilih level' : 'Pilih program dulu' }}</option>
                <option v-for="l in levelChoices" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </label>
            <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Nama kelas</span>
                <input v-model="form.name" required maxlength="100" :class="inputCls" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kapasitas</span>
                <input v-model.number="form.capacity" type="number" min="1" max="100" required :class="inputCls" />
              </label>
            </div>
            <label v-if="editing" class="flex items-center gap-space-xs">
              <input v-model="form.is_active" type="checkbox" class="h-4 w-4" />
              <span class="font-body-md text-body-md text-on-surface">Kelas aktif</span>
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

    <!-- Modal penugasan trainer -->
    <div v-if="assigning" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="assigning = null">
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Trainer Kelas</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ assigning.name }} • {{ assigning.branch.name }}</p>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="assigning = null"><span class="material-symbols-outlined">close</span></button>
        </div>

        <div class="flex-1 overflow-y-auto p-space-lg">
          <div v-if="assignError" class="mb-space-md flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ assignError }}
          </div>

          <p v-if="!assigning.assignments.length" class="font-body-md text-body-md text-on-surface-variant">Belum ada trainer di kelas ini.</p>
          <div v-for="a in assigning.assignments" :key="a.id" class="flex items-center gap-space-sm border-b border-surface-container py-space-xs last:border-0">
            <span class="flex-1 font-body-md text-body-md text-on-surface">{{ a.trainer.name }}</span>
            <select :value="a.role" :disabled="assignBusy" :class="[inputCls, '!py-1.5']" @change="changeRole(a, $event.target.value)">
              <option value="utama">Utama</option>
              <option value="asisten">Asisten</option>
            </select>
            <button :disabled="assignBusy" class="rounded-lg p-1.5 text-error hover:bg-error-container" title="Lepas trainer" @click="removeAssignment(a)">
              <span class="material-symbols-outlined text-[18px]">delete</span>
            </button>
          </div>

          <form class="mt-space-lg flex flex-col gap-space-sm sm:flex-row" @submit.prevent="addTrainer">
            <select v-model.number="assignForm.trainer_id" required :class="[inputCls, 'flex-1']">
              <option value="" disabled>{{ trainerChoices.length ? 'Pilih trainer' : 'Tidak ada trainer tersedia' }}</option>
              <option v-for="t in trainerChoices" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
            <select v-model="assignForm.role" :class="inputCls">
              <option value="utama">Utama</option>
              <option value="asisten">Asisten</option>
            </select>
            <button type="submit" :disabled="assignBusy || !assignForm.trainer_id" class="rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">Tugaskan</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>
