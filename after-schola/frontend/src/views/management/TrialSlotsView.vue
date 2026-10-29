<script setup>
// Slot Trial (permission: manage trial slots) — jadwal trial berkapasitas per cabang dan program (MGT-20).
// starts_at dikirim dan diterima sebagai ISO 8601 (UTC); tampilan dan input memakai zona waktu browser.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatStamp, unwrap } from '@/lib/format'

const loading = ref(true)
const loadError = ref('')
const slots = ref([])
const meta = ref(null)
const page = ref(1)
const options = reactive({ branches: [], programs: [], trainers: [] })
const filters = reactive({ branch_id: '', program_id: '', period: 'upcoming' })

const showForm = ref(false)
const editing = ref(null) // null = tambah baru
const saving = ref(false)
const formError = ref('')
const form = reactive({ branch_id: '', program_id: '', trainer_id: '', starts_at: '', capacity: 8, is_open: true })

const trainerChoices = computed(() => options.trainers.filter((t) => t.branch_ids.includes(Number(form.branch_id))))

function toLocalInput(iso) {
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/trial-slots', {
      params: {
        page: page.value,
        period: filters.period || undefined,
        branch_id: filters.branch_id || undefined,
        program_id: filters.program_id || undefined,
      },
    })
    slots.value = unwrap(data)
    meta.value = data.meta ?? null
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat slot trial.')
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const { data } = await api.get('/trial-slots/options')
    Object.assign(options, data.data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat pilihan cabang, program, dan trainer.')
  }
}

function openForm(slot = null) {
  editing.value = slot
  Object.assign(form, {
    branch_id: slot?.branch.id ?? (options.branches.length === 1 ? options.branches[0].id : ''),
    program_id: slot?.program.id ?? '',
    trainer_id: slot?.trainer.id ?? '',
    starts_at: slot ? toLocalInput(slot.starts_at) : '',
    capacity: slot?.capacity ?? 8,
    is_open: slot?.is_open ?? true,
  })
  formError.value = ''
  showForm.value = true
}

function payloadOf(source, startsAtIso) {
  return {
    branch_id: source.branch_id,
    program_id: source.program_id,
    trainer_id: source.trainer_id,
    starts_at: startsAtIso,
    capacity: source.capacity,
    is_open: source.is_open,
  }
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    const payload = payloadOf(form, new Date(form.starts_at).toISOString())
    if (editing.value) {
      await api.put(`/trial-slots/${editing.value.id}`, payload)
    } else {
      await api.post('/trial-slots', payload)
    }
    showForm.value = false
    await load()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menyimpan slot.')
  } finally {
    saving.value = false
  }
}

async function toggleOpen(slot) {
  try {
    await api.put(`/trial-slots/${slot.id}`, {
      branch_id: slot.branch.id,
      program_id: slot.program.id,
      trainer_id: slot.trainer.id,
      starts_at: slot.starts_at,
      capacity: slot.capacity,
      is_open: !slot.is_open,
    })
    await load()
  } catch (err) {
    window.alert(apiErrorMessage(err, 'Gagal mengubah status slot.'))
  }
}

async function remove(slot) {
  if (!window.confirm('Hapus slot trial ini?')) return
  try {
    await api.delete(`/trial-slots/${slot.id}`)
    await load()
  } catch (err) {
    window.alert(apiErrorMessage(err, 'Gagal menghapus slot.'))
  }
}

watch(
  () => form.branch_id,
  () => {
    // Trainer yang tidak bertugas di cabang terpilih dikosongkan.
    if (!trainerChoices.value.some((t) => t.id === form.trainer_id)) form.trainer_id = ''
  },
)
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
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Trial dan Leads • Slot</span>
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Slot Trial</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
          Jadwal kelas trial per cabang dan program, lengkap dengan trainer dan kapasitas.
        </p>
      </div>
      <button
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 lg:self-center"
        @click="openForm()"
      >
        <span class="material-symbols-outlined text-[18px]">add</span>
        <span>Tambah Slot</span>
      </button>
    </div>

    <div class="mb-space-md flex flex-wrap items-center gap-space-sm">
      <select v-model="filters.period" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface">
        <option value="upcoming">Mendatang</option>
        <option value="past">Sudah lewat</option>
        <option value="">Semua</option>
      </select>
      <select v-if="options.branches.length > 1" v-model="filters.branch_id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface">
        <option value="">Semua cabang</option>
        <option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <select v-model="filters.program_id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface">
        <option value="">Semua program</option>
        <option v-for="p in options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
    </div>

    <div v-if="loading" class="space-y-space-sm">
      <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-surface-container"></div>
    </div>

    <div v-else-if="loadError" class="rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">
      {{ loadError }}
    </div>

    <div v-else-if="!slots.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">event_available</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Belum ada slot trial</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div
        v-for="s in slots"
        :key="s.id"
        class="flex flex-col gap-space-sm border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center"
      >
        <div class="flex flex-col">
          <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ formatStamp(s.starts_at) }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ s.program.name }} • {{ s.branch.name }} • Trainer {{ s.trainer.name }}</span>
        </div>

        <div class="flex items-center gap-space-xs sm:ml-auto">
          <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-surface-variant">Kapasitas {{ s.capacity }}</span>
          <span
            class="rounded-full px-2 py-0.5 font-label-sm text-label-sm font-semibold"
            :class="s.is_open ? 'bg-[#D1FAE5] text-[#065F46]' : 'bg-surface-container text-outline'"
          >{{ s.is_open ? 'Dibuka' : 'Ditutup' }}</span>
          <button class="rounded-lg p-1.5 text-primary hover:bg-surface-container" :title="s.is_open ? 'Tutup pendaftaran' : 'Buka pendaftaran'" @click="toggleOpen(s)">
            <span class="material-symbols-outlined text-[18px]">{{ s.is_open ? 'toggle_on' : 'toggle_off' }}</span>
          </button>
          <button class="rounded-lg p-1.5 text-secondary hover:bg-surface-container" title="Ubah slot" @click="openForm(s)">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button class="rounded-lg p-1.5 text-error hover:bg-error-container" title="Hapus slot" @click="remove(s)">
            <span class="material-symbols-outlined text-[18px]">delete</span>
          </button>
        </div>
      </div>
    </div>

    <div v-if="meta && meta.last_page > 1" class="mt-space-md flex items-center justify-center gap-space-sm">
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page <= 1" @click="page--; load()">Sebelumnya</button>
      <span class="font-body-sm text-body-sm text-on-surface-variant">Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
      <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container disabled:opacity-40" :disabled="page >= meta.last_page" @click="page++; load()">Berikutnya</button>
    </div>

    <!-- Modal tambah / ubah slot -->
    <div
      v-if="showForm"
      class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm"
      @click.self="showForm = false"
    >
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ editing ? 'Ubah Slot Trial' : 'Tambah Slot Trial' }}</h2>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="showForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div class="flex flex-col gap-space-md">
            <div v-if="formError" class="flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
              <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
            </div>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Cabang</span>
              <select v-model.number="form.branch_id" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface">
                <option value="" disabled>Pilih cabang</option>
                <option v-for="b in options.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Program</span>
              <select v-model.number="form.program_id" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface">
                <option value="" disabled>Pilih program</option>
                <option v-for="p in options.programs" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </label>

            <label class="flex flex-col gap-1">
              <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Trainer</span>
              <select v-model.number="form.trainer_id" required :disabled="!form.branch_id" class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface disabled:opacity-60">
                <option value="" disabled>{{ form.branch_id ? 'Pilih trainer' : 'Pilih cabang dulu' }}</option>
                <option v-for="t in trainerChoices" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
            </label>

            <div class="grid grid-cols-1 gap-space-md sm:grid-cols-2">
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Waktu mulai</span>
                <input v-model="form.starts_at" type="datetime-local" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface" />
              </label>
              <label class="flex flex-col gap-1">
                <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Kapasitas</span>
                <input v-model.number="form.capacity" type="number" min="1" max="100" required class="rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface" />
              </label>
            </div>

            <label class="flex items-center gap-space-xs">
              <input v-model="form.is_open" type="checkbox" class="h-4 w-4" />
              <span class="font-body-md text-body-md text-on-surface">Buka pendaftaran untuk slot ini</span>
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
