<script setup>
// Halaman Trainer (Management) — dua tab: Daftar Trainer & Penugasan.
// Tab aktif disimpan di URL (?tab=penugasan) supaya bisa di-refresh / dibagikan.
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TrainerDirectory from '@/components/TrainerDirectory.vue'
import TrainerAssignmentView from '@/views/management/TrainerAssignmentView.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
  { key: 'daftar', label: 'Daftar Trainer', icon: 'groups' },
  { key: 'penugasan', label: 'Penugasan', icon: 'assignment_ind' },
]
const tab = computed(() => (route.query.tab === 'penugasan' ? 'penugasan' : 'daftar'))
function setTab(key) {
  router.replace({ query: key === 'daftar' ? {} : { tab: key } })
}

const subtitle = computed(() =>
  tab.value === 'daftar'
    ? 'Profil trainer: mata pelajaran dan level yang bisa diajar, sekolah yang dipegang, serta aktivitas hari ini.'
    : 'Tentukan trainer memegang sekolah apa saja. Satu trainer bisa banyak sekolah, dan sebaliknya.',
)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col justify-between gap-space-md py-space-xl lg:flex-row lg:items-center">
      <div class="flex flex-col gap-space-2xs">
        <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary"
          >Manajemen Ekstrakurikuler • Trainer</span
        >
        <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Trainer</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ subtitle }}</p>
      </div>
      <RouterLink
        to="/management/manajemen-user"
        class="inline-flex items-center gap-space-xs self-start rounded-xl bg-surface-container px-space-lg py-2.5 font-label-lg text-label-lg text-primary shadow-sm transition-all hover:bg-surface-container-high lg:self-center"
      >
        <span class="material-symbols-outlined text-[18px]">person_add</span>
        <span>Tambah / ubah akun</span>
      </RouterLink>
    </div>

    <!-- Tab -->
    <div class="mb-space-lg inline-flex w-fit rounded-xl bg-surface-container-low p-1">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg px-space-lg py-2 font-label-md text-label-md font-semibold transition-all"
        :class="tab === t.key ? 'bg-primary-container text-on-primary shadow-sm' : 'text-on-surface-variant hover:bg-surface-container'"
        @click="setTab(t.key)"
      >
        <span class="material-symbols-outlined text-[18px]">{{ t.icon }}</span>{{ t.label }}
      </button>
    </div>

    <TrainerDirectory v-if="tab === 'daftar'" />
    <TrainerAssignmentView v-else embedded />
  </div>
</template>
