<script setup>
// Penempatan Siswa (Modul 6b, MGT-31/32) — siswa yang sudah Lunas ditempatkan ke kelas.
// Aturan lunas, level, dan kapasitas ditegakkan backend; layar ini hanya membantu memilih.
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { unwrap } from '@/lib/format'

const loading = ref(true)
const loadError = ref('')
const notice = ref('')
const candidates = ref([])
const options = reactive({ branches: [], programs: [] })
const filters = reactive({ branch_id: '', program_id: '' })
const inputCls = 'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface'

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/placement-candidates', {
      params: { branch_id: filters.branch_id || undefined, program_id: filters.program_id || undefined },
    })
    candidates.value = unwrap(data)
  } catch (err) {
    loadError.value = apiErrorMessage(err, 'Gagal memuat daftar siswa.')
  } finally {
    loading.value = false
  }
}

async function loadOptions() {
  try {
    const { data } = await api.get('/class-groups/options')
    Object.assign(options, data.data)
  } catch (err) {
    // non-fatal: daftar tetap bisa dipakai tanpa filter
  }
}

// --- Modal pilih kelas ---
const target = ref(null) // kandidat yang sedang ditempatkan
const classes = ref([])
const classesLoading = ref(false)
const selectedId = ref(null)
const overrideReason = ref('')
const saving = ref(false)
const formError = ref('')

const selected = computed(() => classes.value.find((c) => c.id === selectedId.value) ?? null)
const needsOverride = computed(
  () => !!selected.value && !!target.value?.recommended_level && selected.value.level.id !== target.value.recommended_level.id,
)
const isFull = (c) => c.enrolled_count >= c.capacity

async function openPlacement(candidate) {
  target.value = candidate
  selectedId.value = null
  overrideReason.value = ''
  formError.value = ''
  classes.value = []
  classesLoading.value = true
  try {
    const { data } = await api.get('/class-groups', {
      params: { branch_id: candidate.branch.id, program_id: candidate.program.id, is_active: 1, per_page: 100 },
    })
    classes.value = unwrap(data)
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal memuat kelas.')
  } finally {
    classesLoading.value = false
  }
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    await api.post('/enrollments', {
      registration_id: target.value.id,
      class_group_id: selectedId.value,
      level_override_reason: needsOverride.value ? overrideReason.value : undefined,
    })
    notice.value = `${target.value.student_name} ditempatkan di ${selected.value.name}.`
    target.value = null
    await load()
  } catch (err) {
    formError.value = apiErrorMessage(err, 'Gagal menempatkan siswa.')
  } finally {
    saving.value = false
  }
}

watch(filters, load)
onMounted(() => {
  loadOptions()
  load()
})
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Kelas dan Penjadwalan</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Penempatan Siswa</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">Siswa yang pendaftarannya sudah terverifikasi dan lunas, siap ditempatkan ke kelas.</p>
    </div>

    <div v-if="notice" class="mb-space-md flex items-center justify-between gap-space-sm rounded-xl bg-primary-container/10 px-space-md py-space-sm font-body-md text-body-md text-on-surface">
      <span>{{ notice }}</span>
      <button class="material-symbols-outlined text-[18px] text-on-surface-variant" aria-label="Tutup" @click="notice = ''">close</button>
    </div>

    <div class="mb-space-md flex flex-wrap items-center gap-space-sm">
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
    <div v-else-if="!candidates.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">how_to_reg</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada siswa yang menunggu penempatan</p>
    </div>

    <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
      <div v-for="c in candidates" :key="c.id" class="flex flex-col gap-space-sm border-b border-surface-container px-space-md py-space-sm last:border-0 sm:flex-row sm:items-center">
        <div class="flex flex-col gap-1">
          <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ c.student_name }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ c.parent_name }} • {{ c.program.name }} • {{ c.branch?.name ?? 'Cabang belum ditetapkan' }}</span>
        </div>
        <div class="flex items-center gap-space-sm sm:ml-auto">
          <span class="rounded-full bg-surface-container px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-surface-variant">
            {{ c.recommended_level ? `Rekomendasi: ${c.recommended_level.name}` : 'Tanpa rekomendasi level' }}
          </span>
          <button
            :disabled="!c.branch"
            class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-50"
            @click="openPlacement(c)"
          >
            Tempatkan
          </button>
        </div>
      </div>
    </div>

    <!-- Modal pilih kelas -->
    <div v-if="target" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="target = null">
      <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
          <div>
            <h2 class="font-headline-md text-headline-md font-bold text-on-surface">Tempatkan {{ target.student_name }}</h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ target.program.name }} • {{ target.branch.name }}</p>
          </div>
          <button class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-low" @click="target = null"><span class="material-symbols-outlined">close</span></button>
        </div>

        <form class="flex-1 overflow-y-auto p-space-lg" @submit.prevent="submit">
          <div v-if="formError" class="mb-space-md flex items-center gap-space-xs rounded-lg bg-error-container px-space-sm py-space-xs font-body-sm text-body-sm text-on-error-container">
            <span class="material-symbols-outlined text-[18px]">error</span>{{ formError }}
          </div>

          <div v-if="classesLoading" class="h-16 animate-pulse rounded-xl bg-surface-container"></div>
          <p v-else-if="!classes.length" class="font-body-md text-body-md text-on-surface-variant">Belum ada kelas aktif untuk program ini di cabang tersebut. Buat kelas di menu Kelas.</p>

          <label
            v-for="cls in classes"
            :key="cls.id"
            :class="['mb-space-xs flex cursor-pointer items-center gap-space-sm rounded-xl border px-space-md py-space-sm', selectedId === cls.id ? 'border-primary-container bg-primary-container/5' : 'border-outline-variant', isFull(cls) ? 'cursor-not-allowed opacity-50' : '']"
          >
            <input v-model="selectedId" type="radio" name="class" :value="cls.id" :disabled="isFull(cls)" />
            <span class="flex flex-col">
              <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ cls.name }}</span>
              <span class="font-body-sm text-body-sm text-on-surface-variant">
                {{ cls.level.name }} • Terisi {{ cls.enrolled_count }}/{{ cls.capacity }}{{ isFull(cls) ? ' (penuh)' : '' }}
              </span>
            </span>
          </label>

          <div v-if="needsOverride" class="mt-space-md flex flex-col gap-1">
            <p class="font-body-sm text-body-sm text-on-surface-variant">
              Level kelas ({{ selected.level.name }}) berbeda dari rekomendasi trial ({{ target.recommended_level.name }}). Alasan wajib diisi dan akan tercatat.
            </p>
            <textarea v-model="overrideReason" required maxlength="500" rows="3" :class="inputCls" placeholder="Alasan penempatan di luar rekomendasi"></textarea>
          </div>

          <div class="mt-space-lg flex items-center justify-end gap-space-sm">
            <button type="button" class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container" @click="target = null">Batal</button>
            <button type="submit" :disabled="saving || !selectedId" class="inline-flex items-center gap-space-xs rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
              <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
              Tempatkan
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
