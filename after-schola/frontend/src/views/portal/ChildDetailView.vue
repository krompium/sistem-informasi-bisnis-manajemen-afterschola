<script setup>
// Detail anak (ORT-04, ORT-05): hasil trial, rekomendasi level, penawaran program, dan keputusan Lanjut / Tidak lanjut.
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/lib/api'
import { formatRupiah, formatStamp } from '@/lib/format'
import { errMsg, inputCls, labelCls, toneOf } from './portalCommon'

const route = useRoute()
const child = ref(null)
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const actionError = ref('')
const declining = ref(false)
const reason = ref('')

const SCORE_LABELS = [
  ['enthusiasm', 'Antusiasme'],
  ['understanding', 'Pemahaman'],
  ['focus', 'Fokus'],
  ['collaboration', 'Kerja sama'],
]

const attended = computed(() => child.value?.evaluation?.attendance === 'hadir')
// Keputusan boleh diubah selama pendaftaran belum diajukan untuk verifikasi.
const canDecide = computed(
  () => attended.value && (!child.value.registration || ['draft', 'perlu_revisi'].includes(child.value.registration.status)),
)

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get(`/parent/children/${route.params.id}`)
    child.value = data.data
  } catch (err) {
    loadError.value = err.response?.status === 404 ? 'Data anak tidak ditemukan.' : errMsg(err, 'Gagal memuat data anak.')
  } finally {
    loading.value = false
  }
}

async function decide(decision) {
  saving.value = true
  actionError.value = ''
  try {
    const { data } = await api.post(`/parent/children/${child.value.id}/decision`, {
      decision,
      reason: decision === 'tidak' ? reason.value || undefined : undefined,
    })
    child.value = data.data
    declining.value = false
    reason.value = ''
  } catch (err) {
    actionError.value = errMsg(err, 'Gagal menyimpan keputusan.')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="flex w-full flex-col pb-space-3xl">
    <div class="pt-space-lg">
      <RouterLink to="/portal" class="font-label-lg text-label-lg text-secondary hover:underline">← Kembali ke beranda</RouterLink>
    </div>

    <p v-if="loadError" class="mt-space-md rounded-xl bg-error-container px-space-md py-space-sm font-body-md text-on-error-container">{{ loadError }}</p>
    <div v-else-if="loading" class="mt-space-md h-24 animate-pulse rounded-2xl bg-surface-container"></div>

    <template v-else-if="child">
      <div class="flex flex-wrap items-start justify-between gap-space-sm py-space-lg">
        <div class="flex flex-col gap-space-2xs">
          <h1 class="font-headline-xl text-headline-xl font-bold tracking-tight text-on-surface">{{ child.name }}</h1>
          <p class="font-body-md text-body-md text-on-surface-variant">
            {{ child.program?.name }} • {{ child.branch?.name }} • {{ child.education_level?.toUpperCase() }} • {{ child.origin_school }}
          </p>
        </div>
        <span :class="['rounded-full px-3 py-1 font-label-sm text-label-sm font-semibold', toneOf(child.stage)]">{{ child.stage_label }}</span>
      </div>

      <section class="flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Hasil trial</h2>

        <p v-if="!child.evaluation" class="font-body-md text-body-md text-on-surface-variant">
          Trial dijadwalkan <b>{{ formatStamp(child.trial_starts_at) }}</b>. Hasil akan muncul di sini setelah trial dievaluasi pengajar.
        </p>
        <p v-else-if="!attended" class="font-body-md text-body-md text-on-surface-variant">
          Anak tidak hadir saat trial, jadi belum ada hasil. Silakan hubungi kami untuk menjadwalkan ulang.
        </p>
        <template v-else>
          <div class="grid gap-space-xs sm:grid-cols-2">
            <div v-for="[key, label] in SCORE_LABELS" :key="key" class="flex items-center justify-between rounded-xl bg-surface-container-low px-space-md py-space-sm">
              <span class="font-label-lg text-label-lg text-on-surface">{{ label }}</span>
              <b class="font-label-lg text-label-lg text-on-surface">{{ child.evaluation.scores[key] ?? '-' }} / 5</b>
            </div>
          </div>
          <div v-if="child.evaluation.strengths" class="flex flex-col gap-1">
            <span :class="labelCls">Kelebihan</span>
            <p class="font-body-md text-body-md text-on-surface">{{ child.evaluation.strengths }}</p>
          </div>
          <div v-if="child.evaluation.improvements" class="flex flex-col gap-1">
            <span :class="labelCls">Yang perlu ditingkatkan</span>
            <p class="font-body-md text-body-md text-on-surface">{{ child.evaluation.improvements }}</p>
          </div>
          <div v-if="child.evaluation.recommended_level" class="rounded-xl bg-primary-fixed px-space-md py-space-sm font-body-md text-body-md text-primary">
            Rekomendasi level: <b>{{ child.evaluation.recommended_level }}</b>
          </div>
        </template>
      </section>

      <section v-if="attended" class="mt-space-md flex flex-col gap-space-sm rounded-2xl bg-surface-container-lowest p-space-lg shadow-sm">
        <h2 class="font-headline-sm text-headline-sm font-bold text-on-surface">Penawaran program</h2>
        <p class="font-body-md text-body-md text-on-surface">
          <b>{{ child.program?.name }}</b> di {{ child.branch?.name }} — {{ formatRupiah(child.program?.price) }}
        </p>

        <div v-if="child.decision" class="rounded-xl bg-surface-container-low px-space-md py-space-sm font-body-md text-body-md text-on-surface">
          Keputusan Anda:
          <b>{{ child.decision.decision === 'lanjut' ? 'Melanjutkan program' : child.decision.decision === 'tidak' ? 'Tidak melanjutkan' : 'Masih dipertimbangkan' }}</b>
          <span v-if="child.decision.reason"> — {{ child.decision.reason }}</span>
        </div>

        <template v-if="canDecide">
          <div v-if="!declining" class="flex flex-wrap gap-space-xs">
            <button
              v-if="child.decision?.decision !== 'lanjut'"
              :disabled="saving"
              class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
              @click="decide('lanjut')"
            >
              Lanjutkan program
            </button>
            <button
              v-if="child.decision?.decision !== 'tidak'"
              :disabled="saving"
              class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface transition-all hover:bg-surface-container-low disabled:opacity-60"
              @click="declining = true"
            >
              Tidak melanjutkan
            </button>
          </div>
          <form v-else class="flex flex-col gap-space-xs" @submit.prevent="decide('tidak')">
            <label :class="labelCls" for="decline-reason">Alasan (opsional)</label>
            <textarea id="decline-reason" v-model="reason" rows="3" maxlength="1000" :class="inputCls"></textarea>
            <div class="flex gap-space-xs">
              <button type="submit" :disabled="saving" class="rounded-xl bg-primary-container px-space-md py-2.5 font-label-lg text-label-lg text-on-primary disabled:opacity-60">Konfirmasi</button>
              <button type="button" class="rounded-xl border border-outline-variant px-space-md py-2.5 font-label-lg text-label-lg text-on-surface hover:bg-surface-container-low" @click="declining = false">Batal</button>
            </div>
          </form>
        </template>
        <p v-else-if="child.registration" class="font-body-sm text-body-sm text-on-surface-variant">
          Pendaftaran sudah diajukan, keputusan tidak dapat diubah.
        </p>

        <p v-if="actionError" class="rounded-lg bg-error-container px-space-sm py-2 font-body-sm text-on-error-container">{{ actionError }}</p>
      </section>
    </template>
  </div>
</template>
