<script setup>
// Token Integrasi (SYS-05, PRD 10.3): token read-only untuk akun layanan Keuangan. Nilai token tampil sekali saja.
import { onMounted, reactive, ref } from 'vue'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiError'
import { formatStamp, unwrap } from '@/lib/format'

const loading = ref(true)
const error = ref('')
const tokens = ref([])
const accounts = ref([])

const form = reactive({ name: '', user_id: '' })
const saving = ref(false)
const created = ref(null) // { name, token } sesaat setelah dibuat
const copied = ref(false)

const inputCls =
  'rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/integration-tokens')
    tokens.value = unwrap(data)
    accounts.value = data.service_accounts ?? []
  } catch (err) {
    error.value = apiErrorMessage(err, 'Daftar token gagal dimuat.')
  } finally {
    loading.value = false
  }
}

async function create() {
  saving.value = true
  error.value = ''
  copied.value = false
  try {
    const { data } = await api.post('/integration-tokens', { ...form, abilities: ['finance:read', 'attendance:read'] })
    created.value = { name: data.data.name, token: data.token }
    form.name = ''
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal membuat token.')
  } finally {
    saving.value = false
  }
}

async function copyToken() {
  await navigator.clipboard.writeText(created.value.token)
  copied.value = true
}

async function revoke(t) {
  if (!confirm(`Cabut token "${t.name}"? Sistem yang memakainya akan langsung ditolak.`)) return
  try {
    await api.delete(`/integration-tokens/${t.id}`)
    await load()
  } catch (err) {
    error.value = apiErrorMessage(err, 'Gagal mencabut token.')
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col gap-space-md pb-space-3xl">
    <div class="flex flex-col gap-space-2xs py-space-xl">
      <span class="font-label-sm text-label-sm font-semibold uppercase tracking-widest text-secondary">Sistem</span>
      <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">Token Integrasi</h1>
      <p class="font-body-md text-body-md text-on-surface-variant">
        Dipakai Tim Keuangan untuk membaca invoice, pembayaran, dan absensi trainer lewat API (hanya baca, dibatasi cabang akun layanan).
      </p>
    </div>

    <p v-if="error" class="rounded-xl bg-error-container px-space-md py-space-sm font-body-sm text-body-sm text-on-error-container">{{ error }}</p>

    <div v-if="created" class="flex flex-col gap-space-xs rounded-2xl border border-primary-container bg-primary-fixed p-space-lg">
      <span class="font-label-lg text-label-lg font-bold text-on-surface">Token "{{ created.name }}" dibuat. Salin sekarang: tidak akan ditampilkan lagi.</span>
      <code class="break-all rounded-lg bg-white px-space-sm py-space-xs font-mono text-sm text-on-surface">{{ created.token }}</code>
      <div class="flex gap-space-xs">
        <button class="rounded-xl bg-primary-container px-space-md py-2 font-label-lg text-label-lg text-on-primary" @click="copyToken">{{ copied ? 'Tersalin' : 'Salin' }}</button>
        <button class="rounded-xl border border-outline-variant px-space-md py-2 font-label-lg text-label-lg text-on-surface" @click="created = null">Tutup</button>
      </div>
    </div>

    <div class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
      <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Buat token</h2>
      <div class="grid gap-space-sm sm:grid-cols-2">
        <label class="flex flex-col gap-1"><span :class="labelCls">Nama</span><input v-model="form.name" placeholder="Sistem Keuangan" :class="inputCls" /></label>
        <label class="flex flex-col gap-1">
          <span :class="labelCls">Akun layanan (role Keuangan)</span>
          <select v-model="form.user_id" :class="inputCls">
            <option value="" disabled>Pilih akun…</option>
            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.name }} ({{ a.email }})</option>
          </select>
        </label>
      </div>
      <p v-if="!loading && !accounts.length" class="font-body-sm text-body-sm text-on-surface-variant">Belum ada user Keuangan aktif. Buat dulu di Manajemen User.</p>
      <div>
        <button class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm active:scale-95 disabled:opacity-60" :disabled="saving || !form.name.trim() || !form.user_id" @click="create">
          Buat Token
        </button>
      </div>
    </div>

    <div class="flex flex-col gap-space-xs rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
      <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Token aktif</h2>
      <div v-if="loading" class="h-16 animate-pulse rounded-xl bg-surface-container"></div>
      <p v-else-if="!tokens.length" class="font-body-md text-body-md text-on-surface-variant">Belum ada token integrasi.</p>
      <div v-for="t in tokens" :key="t.id" class="flex flex-wrap items-center justify-between gap-space-sm border-t border-outline-variant/40 py-space-sm first:border-t-0">
        <div class="flex flex-col">
          <span class="font-label-lg text-label-lg font-semibold text-on-surface">{{ t.name }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">{{ t.user.name }} • {{ t.abilities.join(', ') }}</span>
          <span class="font-body-sm text-body-sm text-on-surface-variant">Dibuat {{ formatStamp(t.created_at) }} • Terakhir dipakai {{ t.last_used_at ? formatStamp(t.last_used_at) : 'belum pernah' }}</span>
        </div>
        <button class="rounded-xl border border-error px-space-md py-2 font-label-lg text-label-lg text-error" @click="revoke(t)">Cabut</button>
      </div>
    </div>

    <div class="rounded-2xl bg-surface-container-low p-space-lg font-body-sm text-body-sm text-on-surface-variant">
      <b>Pemakaian:</b> kirim header <code>Authorization: Bearer &lt;token&gt;</code> ke
      <code>GET /api/integration/v1/invoices</code> atau <code>/payments</code>. Filter: <code>status</code>, <code>branch_id</code>,
      <code>updated_since</code> (sinkron inkremental), <code>per_page</code> (maks. 100).
    </div>
  </div>
</template>
