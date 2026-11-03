<script setup>
// Rekap Absensi → tab Trainer: daftar trainer. Klik trainer → tabel absensi/sesi trainer tsb.
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/lib/api'
import { formatDate, initials, unwrap } from '@/lib/format'
import { currentMonthKey, normalizeSession } from '@/lib/sessionRecap'

const router = useRouter()
const loading = ref(true)
const errorMessage = ref('')
const trainers = ref([])
const sessions = ref([])
const search = ref('')

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const [usersRes, sessionsRes] = await Promise.all([api.get('/users'), api.get('/sessions')])
    trainers.value = unwrap(usersRes.data).filter((u) => (u.roles ?? []).includes('trainer'))
    sessions.value = unwrap(sessionsRes.data).map(normalizeSession)
  } catch (e) {
    errorMessage.value = 'Gagal memuat data trainer.'
  } finally {
    loading.value = false
  }
}

// Kalau sesi tidak membawa info trainer, angka per trainer pasti salah — beri tahu, jangan diam.
const trainerFieldMissing = computed(
  () => sessions.value.length > 0 && sessions.value.every((s) => s.trainerId == null),
)

const thisMonth = currentMonthKey()

const rows = computed(() => {
  const q = search.value.toLowerCase()
  return trainers.value
    .map((t) => {
      const mine = sessions.value.filter((s) => String(s.trainerId) === String(t.id))
      const dates = mine.map((s) => s.date).filter(Boolean).sort()
      return {
        id: t.id,
        name: t.name,
        total: mine.length,
        monthCount: mine.filter((s) => s.monthKey === thisMonth).length,
        last: dates.length ? dates[dates.length - 1] : null,
      }
    })
    .filter((t) => t.name.toLowerCase().includes(q))
})

function open(t) {
  router.push(`/management/absensi/trainer/${t.id}`)
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col">
    <div class="relative mb-space-lg w-full sm:max-w-sm">
      <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-outline">search</span>
      <input
        v-model="search"
        type="text"
        placeholder="Cari trainer…"
        class="w-full rounded-xl bg-surface-container-lowest py-2.5 pl-9 pr-space-md font-body-md text-body-md text-on-surface shadow-sm placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
      />
    </div>

    <div v-if="errorMessage" class="mb-space-lg flex items-center gap-space-sm rounded-2xl bg-error-container px-space-md py-space-sm text-on-error-container">
      <span class="material-symbols-outlined">error</span>
      <span class="font-body-md text-body-md">{{ errorMessage }}</span>
      <button class="ml-auto font-label-md text-label-md font-semibold underline" @click="load">Muat ulang</button>
    </div>

    <div v-if="trainerFieldMissing" class="mb-space-lg flex items-start gap-space-sm rounded-2xl bg-surface-container px-space-md py-space-sm text-on-surface-variant">
      <span class="material-symbols-outlined text-[20px]">info</span>
      <span class="font-body-sm text-body-sm">
        Data sesi dari server belum menyertakan trainer pengampu, jadi jumlah sesi per trainer belum bisa dihitung.
        Sesuaikan nama field di <code>lib/sessionRecap.js</code> (atau sertakan <code>trainer_id</code> di respons <code>/sessions</code>).
      </span>
    </div>

    <div v-if="loading" class="grid grid-cols-1 gap-space-md sm:grid-cols-2 xl:grid-cols-3">
      <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">person_off</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">
        {{ search ? 'Trainer tidak ditemukan' : 'Belum ada trainer' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 gap-space-md sm:grid-cols-2 xl:grid-cols-3">
      <button
        v-for="t in rows"
        :key="t.id"
        class="group flex items-center gap-space-md rounded-2xl bg-surface-container-lowest p-space-lg text-left shadow-sm transition-all hover:shadow-md"
        @click="open(t)"
      >
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-secondary-container text-[15px] font-bold text-on-secondary-container">
          {{ initials(t.name) }}
        </span>
        <span class="flex min-w-0 flex-col">
          <span class="truncate font-headline-sm text-headline-sm font-bold text-on-surface">{{ t.name }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ t.monthCount }} sesi bulan ini • {{ t.total }} total</span>
          <span class="mt-0.5 font-label-sm text-label-sm text-outline">
            {{ t.last ? `Sesi terakhir: ${formatDate(t.last, { day: 'numeric', month: 'short', year: 'numeric' })}` : 'Belum ada sesi' }}
          </span>
        </span>
        <span class="material-symbols-outlined ml-auto text-[22px] text-primary transition-transform group-hover:translate-x-0.5">arrow_forward</span>
      </button>
    </div>
  </div>
</template>
