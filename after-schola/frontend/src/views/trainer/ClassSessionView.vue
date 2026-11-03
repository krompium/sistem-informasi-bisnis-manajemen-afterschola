<script setup>
// Absensi sesi kelas reguler (Modul 7a, TRN-06) — centang hadir, simpan, kunci sesi.
// Check-in trainer (foto/GPS) ditambahkan di irisan 7b. Mobile-first: baris besar, tombol aksi menempel di bawah.
import { computed, onMounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'

const route = useRoute()
const sessionId = route.params.id

const loading = ref(true)
const error = ref('')
const session = ref(null)
const roster = ref([]) // {student_id, name, is_present, note, noteOpen}
const saving = ref(false)
const locking = ref(false)
const toast = reactive({ show: false, message: '', ok: true })

const isLocked = computed(() => !!session.value?.is_locked)
const presentCount = computed(() => roster.value.filter((r) => r.is_present).length)
const dateLabel = computed(() => (session.value ? new Date(`${session.value.date}T00:00:00`).toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) : ''))

function showToast(message, ok = true) {
  Object.assign(toast, { show: true, message, ok })
  setTimeout(() => (toast.show = false), 2500)
}

const applyRoster = (rows) => {
  roster.value = rows.map((r) => ({ ...r, noteOpen: !!r.note }))
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [s, r] = await Promise.all([api.get(`/class-sessions/${sessionId}`), api.get(`/class-sessions/${sessionId}/student-attendances`)])
    session.value = s.data.data
    applyRoster(r.data.data)
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal memuat sesi.')
  } finally {
    loading.value = false
  }
}

const markAll = (value) => roster.value.forEach((r) => (r.is_present = value))

async function save() {
  saving.value = true
  try {
    const { data } = await api.put(`/class-sessions/${sessionId}/student-attendances`, {
      attendances: roster.value.map((r) => ({ student_id: r.student_id, is_present: r.is_present, note: r.note || null })),
    })
    applyRoster(data.data)
    showToast('Absensi tersimpan.')
  } catch (err) {
    showToast(apiErrorMessage(err, 'Gagal menyimpan absensi.'), false)
  } finally {
    saving.value = false
  }
}

async function lock() {
  if (!window.confirm('Kunci sesi ini? Setelah dikunci, absensi tidak bisa diubah dan hanya koordinator yang dapat membukanya.')) return
  locking.value = true
  try {
    // Simpan dulu supaya centang terakhir tidak hilang, lalu kunci.
    if (roster.value.length) await save()
    const { data } = await api.post(`/class-sessions/${sessionId}/lock`)
    session.value = data.data
    showToast('Sesi dikunci.')
  } catch (err) {
    showToast(apiErrorMessage(err, 'Gagal mengunci sesi.'), false)
  } finally {
    locking.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-32">
    <RouterLink to="/trainer/sesi-kelas" class="mt-space-md inline-flex items-center gap-1 self-start font-label-md text-label-md text-primary">
      <span class="material-symbols-outlined text-[18px]">arrow_back</span> Sesi Kelas
    </RouterLink>

    <div v-if="loading" class="mt-space-md space-y-space-sm">
      <div v-for="i in 4" :key="i" class="h-14 animate-pulse rounded-xl bg-surface-container"></div>
    </div>
    <div v-else-if="error" class="mt-space-md rounded-2xl bg-error-container px-space-lg py-space-md font-body-md text-body-md text-on-error-container">{{ error }}</div>

    <template v-else>
      <div class="py-space-md">
        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface">{{ session.class_group.name }}</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ dateLabel }} • {{ session.start_time }}–{{ session.end_time }}</p>
        <p class="font-body-sm text-body-sm text-on-surface-variant">Pertemuan ke-{{ session.meeting_no }} • {{ session.mode === 'online' ? 'Online' : 'Onsite' }}</p>
      </div>

      <div v-if="isLocked" class="mb-space-md flex items-center gap-space-xs rounded-xl bg-surface-container px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">
        <span class="material-symbols-outlined text-[18px]">lock</span>
        Sesi terkunci. Absensi tidak dapat diubah; hubungi koordinator untuk membuka kunci.
      </div>

      <div class="mb-space-sm flex items-center justify-between">
        <span class="font-label-lg text-label-lg font-semibold text-on-surface">Hadir {{ presentCount }} dari {{ roster.length }}</span>
        <div v-if="!isLocked && roster.length" class="flex gap-space-xs">
          <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-primary hover:bg-surface-container" @click="markAll(true)">Hadir semua</button>
          <button class="rounded-lg px-space-sm py-1.5 font-label-md text-label-md text-on-surface-variant hover:bg-surface-container" @click="markAll(false)">Kosongkan</button>
        </div>
      </div>

      <p v-if="!roster.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-xl text-center font-body-md text-on-surface-variant">
        Belum ada siswa terdaftar di kelas ini pada tanggal sesi.
      </p>

      <div v-else class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm">
        <div v-for="r in roster" :key="r.student_id" class="border-b border-surface-container last:border-0">
          <label :class="['flex min-h-[56px] items-center gap-space-md px-space-md py-space-xs', isLocked ? '' : 'cursor-pointer']">
            <input v-model="r.is_present" type="checkbox" :disabled="isLocked" class="h-6 w-6 shrink-0" />
            <span class="flex-1 font-body-lg text-body-lg text-on-surface">{{ r.name }}</span>
            <button
              v-if="!isLocked || r.note"
              type="button"
              class="rounded-lg p-2 text-on-surface-variant"
              :title="r.noteOpen ? 'Tutup catatan' : 'Tambah catatan'"
              @click.prevent="r.noteOpen = !r.noteOpen"
            >
              <span class="material-symbols-outlined text-[20px]">{{ r.note ? 'sticky_note_2' : 'edit_note' }}</span>
            </button>
          </label>
          <input
            v-if="r.noteOpen"
            v-model="r.note"
            maxlength="255"
            :disabled="isLocked"
            placeholder="Catatan (opsional)"
            class="mx-space-md mb-space-sm w-[calc(100%-2rem)] rounded-lg border border-outline-variant bg-white px-space-sm py-2 font-body-md text-body-md text-on-surface"
          />
        </div>
      </div>

      <div v-if="!isLocked && roster.length" class="fixed inset-x-0 bottom-0 z-30 flex gap-space-sm border-t border-surface-container bg-surface-container-lowest/95 px-space-md py-space-sm backdrop-blur">
        <button :disabled="saving || locking" class="flex-1 rounded-xl bg-primary-container py-3 font-label-lg text-label-lg text-on-primary shadow-sm transition-all active:scale-95 disabled:opacity-60" @click="save">
          {{ saving ? 'Menyimpan...' : 'Simpan Absensi' }}
        </button>
        <button :disabled="saving || locking" class="rounded-xl border border-outline-variant px-space-md py-3 font-label-lg text-label-lg text-on-surface transition-all active:scale-95 disabled:opacity-60" @click="lock">
          Kunci
        </button>
      </div>
    </template>

    <div v-if="toast.show" :class="['fixed bottom-24 left-1/2 z-50 -translate-x-1/2 rounded-xl px-space-md py-space-sm font-body-md text-body-md shadow-lg', toast.ok ? 'bg-inverse-surface text-inverse-on-surface' : 'bg-error text-on-error']">
      {{ toast.message }}
    </div>
  </div>
</template>
