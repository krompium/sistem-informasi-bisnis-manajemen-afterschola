<script setup>
// Verifikasi Pendaftaran (Modul 5) — Management memeriksa pendaftaran resmi yang diajukan:
// setujui (invoice terbit otomatis) atau minta revisi dengan catatan.
import { onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { formatRupiah, unwrap } from '@/lib/format'
import { confirmDialog, toast } from '@/lib/feedback'

const loading = ref(true)
const rows = ref([])
const search = ref('')
const status = ref('menunggu_verifikasi')
const busyId = ref(null)

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'

const statusMeta = {
  menunggu_verifikasi: { label: 'Menunggu verifikasi', cls: 'bg-[#DBEAFE] text-[#1E40AF]' },
  perlu_revisi: { label: 'Perlu revisi', cls: 'bg-error-container text-on-error-container' },
  terverifikasi: { label: 'Terverifikasi', cls: 'bg-[#D1FAE5] text-[#065F46]' },
}
const levelLabel = (v) => ({ beginner: 'Beginner', intermediate: 'Intermediate' })[v] || '-'

const errMsg = (err, fallback) => {
  const errors = err.response?.data?.errors
  return (errors && Object.values(errors).flat()[0]) || err.response?.data?.message || fallback
}

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/registrations', {
      params: { status: status.value || undefined, q: search.value || undefined, per_page: 100 },
    })
    rows.value = unwrap(data)
  } catch (err) {
    toast.error(errMsg(err, 'Gagal memuat pendaftaran.'))
  } finally {
    loading.value = false
  }
}

async function approve(row) {
  if (!(await confirmDialog(`Setujui pendaftaran ${row.student_name}? Invoice akan terbit otomatis.`))) return
  busyId.value = row.id
  try {
    await api.post(`/registrations/${row.id}/approve`)
    await load()
  } catch (err) {
    toast.error(errMsg(err, 'Gagal menyetujui pendaftaran.'))
  } finally {
    busyId.value = null
  }
}

const revision = reactive({ open: false, row: null, note: '', error: '', saving: false })
function openRevision(row) {
  Object.assign(revision, { open: true, row, note: '', error: '', saving: false })
}
async function submitRevision() {
  revision.saving = true
  revision.error = ''
  try {
    await api.post(`/registrations/${revision.row.id}/request-revision`, { note: revision.note })
    revision.open = false
    await load()
  } catch (err) {
    revision.error = errMsg(err, 'Gagal meminta revisi.')
  } finally {
    revision.saving = false
  }
}

let timer
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(load, 300)
})
watch(status, load)
onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Pendaftaran & Pembayaran</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Verifikasi Pendaftaran</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Periksa data pendaftaran resmi. Setelah disetujui, invoice terbit otomatis sesuai harga program.
      </p>
    </div>

    <div class="mb-space-md flex flex-col gap-space-sm sm:flex-row">
      <input v-model="search" placeholder="Cari nama siswa atau wali..." :class="[inputCls, 'flex-1']" />
      <select v-model="status" :class="[inputCls, 'sm:w-52']">
        <option value="menunggu_verifikasi">Menunggu verifikasi</option>
        <option value="perlu_revisi">Perlu revisi</option>
        <option value="terverifikasi">Terverifikasi</option>
        <option value="">Semua</option>
      </select>
    </div>

    <div v-if="loading" class="flex flex-col gap-space-sm">
      <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">fact_check</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada pendaftaran</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Pendaftaran yang diajukan dari Keputusan & Pendaftaran akan muncul di sini.</p>
    </div>

    <div v-else class="flex flex-col gap-space-sm">
      <div v-for="row in rows" :key="row.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ row.student_name }}</h3>
            <span class="font-label-sm text-label-sm text-on-surface-variant">
              {{ row.program_name || 'Program belum dipilih' }}
              <template v-if="row.program_price != null"> • {{ formatRupiah(row.program_price) }}</template>
              • Level {{ levelLabel(row.level) }}
            </span>
          </div>
          <span class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="statusMeta[row.status]?.cls">
            {{ statusMeta[row.status]?.label || row.status }}
          </span>
        </div>

        <div class="rounded-xl bg-surface-container-low p-space-md font-body-sm text-body-sm text-on-surface-variant">
          <div>Wali: <b class="text-on-surface">{{ row.parent_name }}</b> • {{ row.parent_phone }}<template v-if="row.parent_email"> • {{ row.parent_email }}</template></div>
          <div v-if="row.origin_school || row.origin_class">Asal: {{ [row.origin_school, row.origin_class].filter(Boolean).join(' • ') }}</div>
          <div v-if="row.parent_address">Alamat: {{ row.parent_address }}</div>
        </div>

        <p v-if="row.status === 'perlu_revisi' && row.revision_note" class="whitespace-pre-line font-body-sm text-body-sm text-on-surface-variant">
          <b class="text-on-surface">Catatan revisi:</b> {{ row.revision_note }}
        </p>
        <p v-if="row.invoice" class="font-body-sm text-body-sm text-on-surface-variant">
          <b class="text-on-surface">Invoice:</b> {{ row.invoice.number }} • {{ formatRupiah(row.invoice.amount) }}
        </p>

        <div v-if="row.status === 'menunggu_verifikasi'" class="flex flex-wrap gap-space-xs">
          <button
            class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            :disabled="busyId === row.id"
            @click="approve(row)"
          >
            {{ busyId === row.id ? 'Memproses...' : 'Setujui' }}
          </button>
          <button
            class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low"
            @click="openRevision(row)"
          >
            Minta Revisi
          </button>
        </div>
      </div>
    </div>

    <!-- Modal minta revisi -->
    <div v-if="revision.open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="revision.open = false">
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="border-b border-surface-container p-space-lg">
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Minta revisi</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant">{{ revision.row?.student_name }}</p>
        </div>
        <div class="flex flex-col gap-space-sm p-space-lg">
          <label class="font-label-md text-label-md font-semibold text-on-surface-variant">Catatan untuk perbaikan</label>
          <textarea v-model="revision.note" rows="4" :class="inputCls" placeholder="Mis. nomor HP wali tidak valid"></textarea>
          <p v-if="revision.error" class="font-body-sm text-body-sm text-error">{{ revision.error }}</p>
          <div class="flex justify-end gap-space-xs">
            <button class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg" @click="revision.open = false">Batal</button>
            <button
              class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary disabled:opacity-60"
              :disabled="revision.saving || !revision.note.trim()"
              @click="submitRevision"
            >
              Kirim
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
