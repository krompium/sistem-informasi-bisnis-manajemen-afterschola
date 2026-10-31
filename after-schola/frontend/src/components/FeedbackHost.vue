<script setup>
// Menampilkan toast dan dialog konfirmasi dari lib/feedback.js.
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { answerDialog, dismissToast, feedback } from '@/lib/feedback'

const cancelBtn = ref(null)

// Fokus awal ke "Batal" supaya tombol Enter tidak langsung menghapus sesuatu.
watch(
  () => feedback.dialog,
  async (d) => {
    if (!d) return
    await nextTick()
    cancelBtn.value?.focus()
  },
)

function onKey(e) {
  if (e.key === 'Escape' && feedback.dialog) answerDialog(false)
}
onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <!-- Toast -->
  <div class="pointer-events-none fixed inset-x-4 top-4 z-[100] flex flex-col gap-2 sm:left-auto sm:right-4 sm:w-96">
    <div
      v-for="t in feedback.toasts"
      :key="t.id"
      :role="t.type === 'error' ? 'alert' : 'status'"
      class="pointer-events-auto flex items-start gap-3 rounded-2xl border-l-4 bg-white p-4 shadow-xl"
      :class="t.type === 'error' ? 'border-red-500' : 'border-emerald-500'"
    >
      <span
        class="material-symbols-outlined mt-0.5"
        :class="t.type === 'error' ? 'text-red-500' : 'text-emerald-500'"
        aria-hidden="true"
      >{{ t.type === 'error' ? 'error' : 'check_circle' }}</span>
      <p class="flex-1 text-base text-slate-800">{{ t.message }}</p>
      <button
        type="button"
        class="-mr-2 -mt-2 flex min-h-[44px] min-w-[44px] items-center justify-center rounded-full text-slate-400 hover:bg-slate-100"
        aria-label="Tutup pesan"
        @click="dismissToast(t.id)"
      >
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
      </button>
    </div>
  </div>

  <!-- Dialog konfirmasi -->
  <div
    v-if="feedback.dialog"
    class="fixed inset-0 z-[110] flex items-end justify-center bg-black/50 p-4 sm:items-center"
    @click.self="answerDialog(false)"
  >
    <div
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="feedback-title"
      aria-describedby="feedback-message"
      class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl"
    >
      <div
        class="mb-3 flex h-12 w-12 items-center justify-center rounded-2xl"
        :class="feedback.dialog.danger ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-700'"
      >
        <span class="material-symbols-outlined" aria-hidden="true">{{ feedback.dialog.danger ? 'delete' : 'help' }}</span>
      </div>
      <h2 id="feedback-title" class="text-xl font-bold text-slate-900">{{ feedback.dialog.title }}</h2>
      <p id="feedback-message" class="mt-2 whitespace-pre-line text-base text-slate-600">{{ feedback.dialog.message }}</p>
      <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          ref="cancelBtn"
          type="button"
          class="min-h-[48px] rounded-full border border-slate-300 px-6 text-base font-semibold text-slate-700 hover:bg-slate-50"
          @click="answerDialog(false)"
        >
          Batal
        </button>
        <button
          type="button"
          class="min-h-[48px] rounded-full px-6 text-base font-semibold text-white"
          :class="feedback.dialog.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-[#1D4ED8] hover:bg-blue-700'"
          @click="answerDialog(true)"
        >
          {{ feedback.dialog.confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>
