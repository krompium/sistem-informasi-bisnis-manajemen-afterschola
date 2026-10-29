<script setup>
// Invoice & Pembayaran (Modul 5) — catat pembayaran (bukti transfer / tunai), konfirmasi atau tolak,
// dan batalkan invoice. Nominal pembayaran selalu = nominal invoice (lunas sekaligus).
import { computed, onMounted, reactive, ref, watch } from 'vue'
import api from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { formatDate, formatRupiah, unwrap } from '@/lib/format'
import { confirmDialog, toast } from '@/lib/feedback'

const auth = useAuthStore()
const loading = ref(true)
const rows = ref([])
const search = ref('')
const status = ref('unpaid')
const busyId = ref(null)

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

const invoiceMeta = {
  unpaid: { label: 'Belum lunas', cls: 'bg-[#FEF3C7] text-[#92400E]' },
  paid: { label: 'Lunas', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  void: { label: 'Dibatalkan', cls: 'bg-surface-container text-on-surface-variant' },
}
const paymentMeta = {
  pending: { label: 'Menunggu konfirmasi', cls: 'bg-[#DBEAFE] text-[#1E40AF]' },
  confirmed: { label: 'Dikonfirmasi', cls: 'bg-[#D1FAE5] text-[#065F46]' },
  rejected: { label: 'Ditolak', cls: 'bg-error-container text-on-error-container' },
}
const methodLabel = (m) => ({ transfer: 'Transfer', tunai: 'Tunai' })[m] || m

const errMsg = (err, fallback) => {
  const errors = err.response?.data?.errors
  return (errors && Object.values(errors).flat()[0]) || err.response?.data?.message || fallback
}

const canConfirm = computed(() => auth.can('confirm payments'))
// Invoice bisa menerima pembayaran / dibatalkan bila belum lunas dan tidak ada pembayaran aktif.
const hasActivePayment = (inv) => inv.payments.some((p) => ['pending', 'confirmed'].includes(p.status))
const canRecord = (inv) => inv.status === 'unpaid' && !hasActivePayment(inv)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/invoices', {
      params: { status: status.value || undefined, q: search.value || undefined, per_page: 100 },
    })
    rows.value = unwrap(data)
  } catch (err) {
    toast.error(errMsg(err, 'Gagal memuat invoice.'))
  } finally {
    loading.value = false
  }
}

// --- Catat pembayaran ---
const pay = reactive({ open: false, inv: null, method: 'transfer', paid_date: '', file: null, error: '', saving: false })
const today = () => new Date().toISOString().slice(0, 10)
function openPay(inv) {
  Object.assign(pay, { open: true, inv, method: 'transfer', paid_date: today(), file: null, error: '', saving: false })
}
async function submitPay() {
  pay.saving = true
  pay.error = ''
  const form = new FormData()
  form.append('method', pay.method)
  form.append('paid_date', pay.paid_date)
  if (pay.file) form.append('proof', pay.file)
  try {
    await api.post(`/invoices/${pay.inv.id}/payments`, form)
    pay.open = false
    await load()
  } catch (err) {
    pay.error = errMsg(err, 'Gagal mencatat pembayaran.')
  } finally {
    pay.saving = false
  }
}

// --- Konfirmasi / tolak pembayaran ---
async function confirmPayment(p) {
  if (!(await confirmDialog('Konfirmasi pembayaran ini? Invoice akan berstatus lunas.'))) return
  busyId.value = p.id
  try {
    await api.post(`/payments/${p.id}/confirm`)
    await load()
  } catch (err) {
    toast.error(errMsg(err, 'Gagal mengonfirmasi pembayaran.'))
  } finally {
    busyId.value = null
  }
}

// Satu modal alasan dipakai untuk tolak pembayaran dan batalkan invoice.
const reason = reactive({ open: false, kind: '', target: null, text: '', error: '', saving: false })
function openReason(kind, target) {
  Object.assign(reason, { open: true, kind, target, text: '', error: '', saving: false })
}
async function submitReason() {
  reason.saving = true
  reason.error = ''
  const url = reason.kind === 'reject' ? `/payments/${reason.target.id}/reject` : `/invoices/${reason.target.id}/void`
  try {
    await api.post(url, { reason: reason.text })
    reason.open = false
    await load()
  } catch (err) {
    reason.error = errMsg(err, 'Gagal menyimpan.')
  } finally {
    reason.saving = false
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
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Invoice & Pembayaran</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Catat bukti pembayaran dari orang tua, lalu konfirmasi setelah dicek.
      </p>
    </div>

    <div class="mb-space-md flex flex-col gap-space-sm sm:flex-row">
      <input v-model="search" placeholder="Cari nomor invoice, siswa, atau wali..." :class="[inputCls, 'flex-1']" />
      <select v-model="status" :class="[inputCls, 'sm:w-52']">
        <option value="unpaid">Belum lunas</option>
        <option value="paid">Lunas</option>
        <option value="void">Dibatalkan</option>
        <option value="">Semua</option>
      </select>
    </div>

    <div v-if="loading" class="flex flex-col gap-space-sm">
      <div v-for="i in 3" :key="i" class="h-28 animate-pulse rounded-2xl bg-surface-container"></div>
    </div>

    <div v-else-if="!rows.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center">
      <span class="material-symbols-outlined text-[36px] text-outline">receipt_long</span>
      <p class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">Tidak ada invoice</p>
      <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Invoice terbit otomatis saat pendaftaran disetujui.</p>
    </div>

    <div v-else class="flex flex-col gap-space-sm">
      <div v-for="inv in rows" :key="inv.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-space-sm">
          <div class="flex flex-col">
            <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ inv.student_name }}</h3>
            <span class="font-label-sm text-label-sm text-on-surface-variant">
              {{ inv.number }} • {{ inv.program_name || 'Program -' }} • Wali: {{ inv.parent_name }} ({{ inv.parent_phone }})
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-space-2xs">
            <span v-if="inv.is_overdue" class="rounded-full bg-error-container px-2.5 py-1 font-label-sm text-label-sm font-semibold text-on-error-container">
              Terlambat {{ inv.days_overdue }} hari
            </span>
            <span class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="invoiceMeta[inv.status]?.cls">
              {{ invoiceMeta[inv.status]?.label || inv.status }}
            </span>
          </div>
        </div>

        <div class="flex flex-wrap items-baseline gap-x-space-lg gap-y-1 font-body-sm text-body-sm text-on-surface-variant">
          <span class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ formatRupiah(inv.amount) }}</span>
          <span>Jatuh tempo {{ formatDate(inv.due_date) }}</span>
          <span v-if="inv.paid_at">Lunas {{ formatDate(inv.paid_at) }}</span>
        </div>
        <p v-if="inv.status === 'void' && inv.void_reason" class="font-body-sm text-body-sm text-on-surface-variant">
          <b class="text-on-surface">Dibatalkan:</b> {{ inv.void_reason }}
        </p>

        <div v-if="inv.payments.length" class="flex flex-col gap-space-2xs rounded-xl bg-surface-container-low p-space-md font-body-sm text-body-sm text-on-surface-variant">
          <div v-for="p in inv.payments" :key="p.id" class="flex flex-wrap items-center justify-between gap-space-xs">
            <div class="flex flex-wrap items-center gap-space-xs">
              <span class="rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="paymentMeta[p.status]?.cls">{{ paymentMeta[p.status]?.label }}</span>
              <span>{{ methodLabel(p.method) }} • {{ formatDate(p.paid_date) }}</span>
              <a v-if="p.proof_url" :href="p.proof_url" target="_blank" rel="noopener" class="font-semibold text-primary underline">Lihat bukti</a>
              <span v-if="p.reject_reason">• Alasan tolak: {{ p.reject_reason }}</span>
            </div>
            <div v-if="p.status === 'pending' && canConfirm" class="flex gap-space-xs">
              <button
                class="rounded-lg bg-primary-container px-space-sm py-1.5 font-label-md text-label-md text-on-primary disabled:opacity-60"
                :disabled="busyId === p.id"
                @click="confirmPayment(p)"
              >
                Konfirmasi
              </button>
              <button class="rounded-lg border border-outline-variant px-space-sm py-1.5 font-label-md text-label-md text-on-surface" @click="openReason('reject', p)">Tolak</button>
            </div>
          </div>
        </div>

        <div v-if="canRecord(inv)" class="flex flex-wrap gap-space-xs">
          <button class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95" @click="openPay(inv)">
            Catat Pembayaran
          </button>
          <button class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low" @click="openReason('void', inv)">
            Batalkan Invoice
          </button>
        </div>
      </div>
    </div>

    <!-- Modal catat pembayaran -->
    <div v-if="pay.open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="pay.open = false">
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="border-b border-surface-container p-space-lg">
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">Catat pembayaran</h3>
          <p class="font-body-sm text-body-sm text-on-surface-variant">{{ pay.inv?.student_name }} • {{ formatRupiah(pay.inv?.amount) }}</p>
        </div>
        <div class="flex flex-col gap-space-sm p-space-lg">
          <label :class="labelCls">Metode</label>
          <select v-model="pay.method" :class="inputCls">
            <option value="transfer">Transfer (bukti wajib)</option>
            <option value="tunai">Tunai</option>
          </select>
          <label :class="labelCls">Tanggal bayar</label>
          <input v-model="pay.paid_date" type="date" :max="today()" :class="inputCls" />
          <label :class="labelCls">Bukti pembayaran (JPG/PNG, maks. 5 MB)</label>
          <input type="file" accept="image/png,image/jpeg" :class="inputCls" @change="pay.file = $event.target.files[0] || null" />
          <p v-if="pay.error" class="font-body-sm text-body-sm text-error">{{ pay.error }}</p>
          <div class="flex justify-end gap-space-xs">
            <button class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg" @click="pay.open = false">Batal</button>
            <button class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary disabled:opacity-60" :disabled="pay.saving" @click="submitPay">Simpan</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal alasan (tolak pembayaran / batalkan invoice) -->
    <div v-if="reason.open" class="fixed inset-0 z-50 flex items-center justify-center bg-[#0B2A5B]/40 p-4 backdrop-blur-sm" @click.self="reason.open = false">
      <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
        <div class="border-b border-surface-container p-space-lg">
          <h3 class="font-headline-sm text-headline-sm font-bold text-on-surface">
            {{ reason.kind === 'reject' ? 'Tolak pembayaran' : 'Batalkan invoice' }}
          </h3>
          <p v-if="reason.kind === 'void'" class="font-body-sm text-body-sm text-on-surface-variant">
            Pendaftaran akan kembali ke antrian verifikasi dan bisa disetujui ulang.
          </p>
        </div>
        <div class="flex flex-col gap-space-sm p-space-lg">
          <label :class="labelCls">Alasan</label>
          <textarea v-model="reason.text" rows="3" :class="inputCls"></textarea>
          <p v-if="reason.error" class="font-body-sm text-body-sm text-error">{{ reason.error }}</p>
          <div class="flex justify-end gap-space-xs">
            <button class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg" @click="reason.open = false">Batal</button>
            <button class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary disabled:opacity-60" :disabled="reason.saving || !reason.text.trim()" @click="submitReason">Simpan</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
