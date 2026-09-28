<script setup>
// Popup "Hot Issue": pengumuman/isu mendesak dari Management yang wajib dilihat
// setiap user setelah login, di semua role dashboard. Ditutup per-issue,
// per-user (tersimpan di backend lewat POST /hot-issues/{id}/dismiss) — jadi
// begitu ditutup, tidak akan muncul lagi untuk user tsb sampai dibuat ulang.
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'
import { formatStamp, unwrap } from '@/lib/format'

const issues = ref([])
const index = ref(0)
const dismissing = ref(false)
const loaded = ref(false)

const open = computed(() => loaded.value && issues.value.length > 0)
const current = computed(() => issues.value[index.value])
const remaining = computed(() => issues.value.length)

const severityMeta = {
  critical: { label: 'Mendesak', icon: 'error', cls: 'bg-error-container text-on-error-container', dot: 'bg-error' },
  warning: { label: 'Perlu Perhatian', icon: 'warning', cls: 'bg-[#FEF3C7] text-[#92400E]', dot: 'bg-[#F59E0B]' },
  info: { label: 'Info', icon: 'campaign', cls: 'bg-secondary-container text-on-secondary-container', dot: 'bg-secondary' },
}
function meta(severity) {
  return severityMeta[severity] || severityMeta.info
}

async function load() {
  try {
    const { data } = await api.get('/hot-issues/active')
    issues.value = unwrap(data)
    index.value = 0
  } catch {
    /* popup non-kritis; diamkan bila gagal memuat */
  } finally {
    loaded.value = true
  }
}

async function dismissCurrent() {
  if (!current.value) return
  dismissing.value = true
  try {
    await api.post(`/hot-issues/${current.value.id}/dismiss`)
    issues.value = issues.value.filter((i) => i.id !== current.value.id)
    if (index.value >= issues.value.length) index.value = Math.max(0, issues.value.length - 1)
  } catch {
    /* biarkan tetap tampil bila gagal — user bisa coba lagi */
  } finally {
    dismissing.value = false
  }
}

function next() {
  if (index.value < issues.value.length - 1) index.value += 1
}
function prev() {
  if (index.value > 0) index.value -= 1
}

onMounted(load)
</script>

<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[60] flex items-center justify-center bg-[#0B2A5B]/50 p-4 backdrop-blur-sm"
  >
    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-surface-container-lowest shadow-xl animate-fade-in">
      <div class="flex items-center justify-between border-b border-surface-container p-space-lg">
        <span class="inline-flex items-center gap-space-xs rounded-full px-2.5 py-1 font-label-sm text-label-sm font-semibold" :class="meta(current?.severity).cls">
          <span class="material-symbols-outlined text-[16px]">{{ meta(current?.severity).icon }}</span>
          {{ meta(current?.severity).label }}
        </span>
        <span v-if="remaining > 1" class="font-label-sm text-label-sm text-on-surface-variant">
          {{ index + 1 }} / {{ remaining }}
        </span>
      </div>

      <div class="flex flex-col gap-space-sm p-space-lg">
        <h2 class="font-headline-md text-headline-md font-bold text-on-surface">{{ current?.title }}</h2>
        <p class="whitespace-pre-line font-body-md text-body-md text-on-surface-variant">{{ current?.message }}</p>
        <span class="font-label-sm text-label-sm text-outline">
          {{ current?.created_by ? `Dari ${current.created_by} • ` : '' }}{{ formatStamp(current?.created_at) }}
        </span>
      </div>

      <div class="flex items-center justify-between gap-space-sm border-t border-surface-container p-space-lg">
        <button
          v-if="remaining > 1"
          class="inline-flex items-center gap-1 rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface-variant transition-all hover:bg-surface-container-low disabled:opacity-40"
          :disabled="index === 0"
          @click="prev"
        >
          <span class="material-symbols-outlined text-[18px]">chevron_left</span>
          Sebelumnya
        </button>
        <div v-else></div>

        <div class="flex items-center gap-space-sm">
          <button
            v-if="remaining > 1 && index < remaining - 1"
            class="rounded-xl px-space-md py-2.5 font-label-lg text-label-lg text-on-surface-variant transition-all hover:bg-surface-container-low"
            @click="next"
          >
            Nanti
          </button>
          <button
            class="inline-flex items-center gap-1 rounded-xl bg-primary-container px-space-lg py-2.5 font-label-lg text-label-lg text-on-primary shadow-sm transition-all hover:opacity-95 active:scale-95 disabled:opacity-60"
            :disabled="dismissing"
            @click="dismissCurrent"
          >
            <span class="material-symbols-outlined text-[18px]">done</span>
            Sudah dibaca
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
