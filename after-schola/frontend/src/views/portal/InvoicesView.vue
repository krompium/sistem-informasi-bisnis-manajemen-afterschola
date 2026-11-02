<script setup>
// Tagihan (ORT-08, ORT-09): invoice anak, instruksi pembayaran, unggah bukti transfer, status pembayaran, kuitansi.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { formatDateShort, formatRupiah } from '@/lib/format'
import { errMsg, inputCls, labelCls } from './portalCommon'

const loading = ref(true)
const loadError = ref('')
const invoices = ref([])
const info = ref({ instructions: null, bank_account: null })
const uploading = ref(null)
const rowError = reactive({})
const files = reactive({})

const STATUS = {
  unpaid: { label: 'Belum dibayar', cls: 'bg-error-container text-on-error-container' },
  paid: { label: 'Lunas', cls: 'bg-primary-fixed text-primary' },
  void: { label: 'Dibatalkan', cls: 'bg-surface-container text-on-surface-variant' },
}
const PAYMENT = {
  pending: 'Bukti terkirim, menunggu konfirmasi Keuangan',
  confirmed: 'Pembayaran dikonfirmasi',
  rejected: 'Bukti ditolak',
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/parent/invoices')
    invoices.value = data.data
    info.value = data.meta.payment_info
  } catch (err) {
    loadError.value = errMsg(err, 'Gagal memuat tagihan.')
  } finally {
    loading.value = false
  }
}

const latestPayment = (inv) => inv.payments?.[inv.payments.length - 1]

async function upload(inv) {
  const file = files[inv.id]
  rowError[inv.id] = ''
  if (!file) {
    rowError[inv.id] = 'Pilih foto bukti transfer lebih dulu.'
    return
  }
  uploading.value = inv.id
  try {
    const body = new FormData()
    body.append('proof', file)
    await api.post(`/parent/invoices/${inv.id}/payments`, body)
    delete files[inv.id]
    await load()
  } catch (err) {
    rowError[inv.id] = errMsg(err, 'Gagal mengunggah bukti.')
  } finally {
    uploading.value = null
  }
}

async function downloadReceipt(inv) {
  rowError[inv.id] = ''
  try {
    const res = await api.get(`/parent/invoices/${inv.id}/receipt`, { responseType: 'blob' })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    link.download = `kuitansi-${inv.number.replaceAll('/', '-')}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    setTimeout(() => URL.revokeObjectURL(link.href), 1000)
  } catch (err) {
    rowError[inv.id] = 'Gagal mengunduh kuitansi.'
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Portal Orang Tua</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Tagihan</h1>
    </div>

    <p v-if="loadError" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else>
      <div v-if="info.instructions || info.bank_account" class="mb-space-md flex flex-col gap-1 rounded-2xl bg-secondary-container px-space-lg py-space-md text-on-secondary-container">
        <span class="font-label-lg text-label-lg font-semibold">Cara pembayaran</span>
        <p v-if="info.bank_account" class="font-body-md text-body-md">Rekening tujuan: <b>{{ info.bank_account }}</b></p>
        <p v-if="info.instructions" class="whitespace-pre-line font-body-md text-body-md">{{ info.instructions }}</p>
      </div>

      <div v-if="!invoices.length" class="rounded-2xl bg-surface-container-lowest px-space-lg py-space-2xl text-center font-body-md text-on-surface-variant">
        Belum ada tagihan. Invoice terbit otomatis setelah pendaftaran terverifikasi.
      </div>

      <div v-else class="flex flex-col gap-space-sm">
        <div v-for="inv in invoices" :key="inv.id" class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
          <div class="flex flex-wrap items-start justify-between gap-space-sm">
            <div class="flex flex-col">
              <span class="font-headline-sm text-headline-sm font-bold text-on-surface">{{ inv.student_name }}</span>
              <span class="font-body-sm text-body-sm text-on-surface-variant">{{ inv.program_name }} • {{ inv.number }}</span>
            </div>
            <span :class="['rounded-full px-3 py-1 font-label-sm text-label-sm font-semibold', STATUS[inv.status]?.cls]">{{ STATUS[inv.status]?.label }}</span>
          </div>

          <div class="flex flex-wrap items-baseline justify-between gap-space-sm">
            <b class="font-headline-md text-headline-md text-on-surface">{{ formatRupiah(inv.amount) }}</b>
            <span v-if="inv.status === 'unpaid'" :class="['font-body-sm text-body-sm', inv.is_overdue ? 'font-semibold text-error' : 'text-on-surface-variant']">
              Jatuh tempo {{ formatDateShort(inv.due_date) }}<template v-if="inv.is_overdue"> • terlambat {{ inv.days_overdue }} hari</template>
            </span>
          </div>

          <p v-if="latestPayment(inv)" class="rounded-xl bg-surface-container-low px-space-md py-space-sm font-body-sm text-body-sm text-on-surface">
            {{ PAYMENT[latestPayment(inv).status] }}
            <span v-if="latestPayment(inv).status === 'rejected' && latestPayment(inv).reject_reason"> — {{ latestPayment(inv).reject_reason }}</span>
          </p>

          <form v-if="inv.can_pay" class="flex flex-col gap-space-xs" @submit.prevent="upload(inv)">
            <label :class="labelCls" :for="`proof-${inv.id}`">Unggah bukti transfer (JPG atau PNG, maks. 5 MB)</label>
            <input :id="`proof-${inv.id}`" type="file" accept="image/png,image/jpeg" :class="inputCls" @change="files[inv.id] = $event.target.files[0]" />
            <div>
              <button type="submit" :disabled="uploading === inv.id" class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60">
                {{ uploading === inv.id ? 'Mengunggah…' : 'Kirim bukti pembayaran' }}
              </button>
            </div>
          </form>

          <div v-if="inv.receipt_available">
            <button class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low" @click="downloadReceipt(inv)">
              Unduh kuitansi
            </button>
          </div>

          <p v-if="rowError[inv.id]" class="rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-on-error-container">{{ rowError[inv.id] }}</p>
        </div>
      </div>
    </template>
  </div>
</template>
