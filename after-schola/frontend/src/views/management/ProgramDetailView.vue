<script setup>
// Detail program: kelola level (MGT-11) dan kriteria rubrik (MGT-12). Dua daftar CRUD memakai satu pola.
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/lib/api'
import { confirmDialog } from '@/lib/feedback'

const route = useRoute()
const programId = route.params.id

const sections = [
  {
    key: 'levels',
    endpoint: 'levels',
    title: 'Level',
    hint: 'Tingkat kemampuan dalam program. Rekomendasi hasil trial dipilih dari daftar ini.',
    fields: [
      { name: 'name', label: 'Nama level', type: 'text', placeholder: 'mis. Dasar' },
      { name: 'sort_order', label: 'Urutan', type: 'number', placeholder: '0' },
    ],
  },
  {
    key: 'rubric_criteria',
    endpoint: 'rubric-criteria',
    title: 'Rubrik penilaian',
    hint: 'Kriteria yang dinilai trainer pada evaluasi periodik, beserta skor maksimumnya.',
    fields: [
      { name: 'name', label: 'Nama kriteria', type: 'text', placeholder: 'mis. Logika' },
      { name: 'max_score', label: 'Skor maks', type: 'number', placeholder: '10' },
    ],
  },
]

const INPUT =
  'min-w-0 rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none'

const program = ref(null)
const loading = ref(true)
const drafts = reactive({ levels: {}, rubric_criteria: {} })
const errors = reactive({ levels: '', rubric_criteria: '' })

function errorMessage(e) {
  const first = Object.values(e?.response?.data?.errors ?? {})[0]?.[0]
  return first || e?.response?.data?.message || 'Terjadi kesalahan.'
}

async function load() {
  try {
    const { data } = await api.get(`/programs/${programId}`)
    program.value = data.data ?? data
  } finally {
    loading.value = false
  }
}

async function run(section, request) {
  errors[section.key] = ''
  try {
    await request()
    await load()
  } catch (e) {
    errors[section.key] = errorMessage(e)
  }
}

const url = (section, row) =>
  `/programs/${programId}/${section.endpoint}` + (row ? `/${row.id}` : '')

function payload(section, source) {
  return Object.fromEntries(section.fields.map((f) => [f.name, source[f.name] ?? '']))
}

function add(section) {
  run(section, async () => {
    await api.post(url(section), payload(section, drafts[section.key]))
    drafts[section.key] = {}
  })
}

function save(section, row) {
  run(section, () => api.put(url(section, row), payload(section, row)))
}

async function remove(section, row) {
  if (!(await confirmDialog(`Hapus "${row.name}"?`, { title: 'Hapus data?', danger: true, confirmText: 'Ya, hapus' }))) return
  run(section, () => api.delete(url(section, row)))
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col gap-space-lg pb-space-3xl">
    <div class="flex flex-col gap-space-2xs pt-space-xl">
      <RouterLink
        :to="{ name: 'management.programs' }"
        class="inline-flex items-center gap-1 self-start font-label-md text-label-md text-secondary hover:underline"
      >
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
        Program & Trial
      </RouterLink>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">
        {{ program?.name ?? 'Detail Program' }}
      </h1>
      <p v-if="program" class="font-body-md text-body-md text-on-surface-variant">
        {{ [program.jenjang, program.tipe].filter(Boolean).join(' • ') }}
      </p>
    </div>

    <div v-if="loading" class="h-40 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else-if="program">
      <section
        v-for="section in sections"
        :key="section.key"
        class="rounded-2xl bg-surface-container-lowest p-space-md shadow-sm"
      >
        <h2 class="font-headline-md text-headline-md text-on-surface">{{ section.title }}</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ section.hint }}</p>

        <p v-if="errors[section.key]" class="mt-space-sm rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-body-sm text-on-error-container">
          {{ errors[section.key] }}
        </p>

        <p v-if="!program[section.key].length" class="py-space-md font-body-md text-body-md text-outline">
          Belum ada data.
        </p>

        <div
          v-for="row in program[section.key]"
          :key="row.id"
          class="mt-space-sm flex flex-wrap items-center gap-space-xs"
        >
          <input
            v-for="f in section.fields"
            :key="f.name"
            v-model="row[f.name]"
            :type="f.type"
            :min="f.type === 'number' ? 0 : undefined"
            :aria-label="f.label"
            :class="[INPUT, f.type === 'number' ? 'w-24' : 'flex-1']"
          />
          <button class="rounded-lg p-2 text-primary hover:bg-surface-container" title="Simpan" @click="save(section, row)">
            <span class="material-symbols-outlined text-[20px]">save</span>
          </button>
          <button class="rounded-lg p-2 text-error hover:bg-error-container" title="Hapus" @click="remove(section, row)">
            <span class="material-symbols-outlined text-[20px]">delete</span>
          </button>
        </div>

        <div class="mt-space-md flex flex-wrap items-center gap-space-xs border-t border-surface-container pt-space-md">
          <input
            v-for="f in section.fields"
            :key="f.name"
            v-model="drafts[section.key][f.name]"
            :type="f.type"
            :min="f.type === 'number' ? 0 : undefined"
            :placeholder="f.placeholder"
            :aria-label="f.label"
            :class="[INPUT, f.type === 'number' ? 'w-24' : 'flex-1']"
          />
          <button
            class="inline-flex items-center gap-1 rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary hover:opacity-95"
            @click="add(section)"
          >
            <span class="material-symbols-outlined text-[18px]">add</span>
            Tambah
          </button>
        </div>
      </section>
    </template>
  </div>
</template>
