<script setup>
// Pengaturan pembayaran global (MGT-14): jatuh tempo default, interval reminder, instruksi dan rekening.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'

const INPUT =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none'

const form = reactive({
  due_days: 7,
  reminder_interval_days: 3,
  payment_instructions: '',
  bank_account: '',
})
const loading = ref(true)
const saving = ref(false)
const saved = ref(false)
const error = ref('')

async function load() {
  try {
    const { data } = await api.get('/payment-settings')
    const d = data.data ?? data
    Object.assign(form, {
      due_days: d.due_days,
      reminder_interval_days: d.reminder_interval_days,
      payment_instructions: d.payment_instructions ?? '',
      bank_account: d.bank_account ?? '',
    })
  } catch (e) {
    error.value = 'Gagal memuat pengaturan.'
  } finally {
    loading.value = false
  }
}

async function submit() {
  saving.value = true
  saved.value = false
  error.value = ''
  try {
    await api.put('/payment-settings', { ...form })
    saved.value = true
  } catch (e) {
    const first = Object.values(e?.response?.data?.errors ?? {})[0]?.[0]
    error.value = first || e?.response?.data?.message || 'Gagal menyimpan pengaturan.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full max-w-2xl flex-col gap-space-lg pb-space-3xl">
    <div class="flex flex-col gap-space-2xs pt-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Keuangan</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Pengaturan Pembayaran</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Berlaku untuk semua cabang. Jatuh tempo dipakai saat invoice terbit.
      </p>
    </div>

    <div v-if="loading" class="h-48 animate-pulse rounded-2xl bg-surface-container"></div>

    <form v-else class="flex flex-col gap-space-md rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm" @submit.prevent="submit">
      <p v-if="error" class="rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-body-sm text-on-error-container">{{ error }}</p>
      <p v-if="saved" class="rounded-lg bg-[#D1FAE5] px-space-sm py-2 font-body-sm text-body-sm text-[#065F46]">Pengaturan tersimpan.</p>

      <div class="grid gap-space-md sm:grid-cols-2">
        <label class="flex flex-col gap-1">
          <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Jatuh tempo (hari)</span>
          <input v-model.number="form.due_days" type="number" min="1" max="90" required :class="INPUT" />
        </label>
        <label class="flex flex-col gap-1">
          <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Interval reminder (hari)</span>
          <input v-model.number="form.reminder_interval_days" type="number" min="1" max="30" required :class="INPUT" />
        </label>
      </div>

      <label class="flex flex-col gap-1">
        <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Rekening tujuan transfer</span>
        <textarea v-model="form.bank_account" rows="2" maxlength="500" placeholder="mis. BCA 1234567890 a.n. AfterSchola" :class="INPUT"></textarea>
      </label>

      <label class="flex flex-col gap-1">
        <span class="font-label-md text-label-md font-semibold text-on-surface-variant">Instruksi pembayaran</span>
        <textarea v-model="form.payment_instructions" rows="4" maxlength="2000" :class="INPUT"></textarea>
      </label>

      <button
        type="submit"
        :disabled="saving"
        class="inline-flex items-center justify-center gap-space-xs self-start rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary hover:opacity-95 disabled:opacity-60"
      >
        {{ saving ? 'Menyimpan…' : 'Simpan' }}
      </button>
    </form>
  </div>
</template>
