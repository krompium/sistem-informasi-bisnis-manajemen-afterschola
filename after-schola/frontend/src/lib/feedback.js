// Toast dan dialog konfirmasi bersama. Pengganti alert()/confirm() bawaan browser
// (dialog bawaan memblokir halaman, tampilannya tidak konsisten, dan sulit dipakai di HP).
// Dipasang sekali lewat <FeedbackHost /> di App.vue.
import { reactive } from 'vue'

export const feedback = reactive({ toasts: [], dialog: null })
let seq = 0

export function dismissToast(id) {
  feedback.toasts = feedback.toasts.filter((t) => t.id !== id)
}

function show(message, type) {
  const id = ++seq
  feedback.toasts.push({ id, message: String(message), type })
  setTimeout(() => dismissToast(id), type === 'error' ? 7000 : 4000)
}

export const toast = {
  error: (message) => show(message, 'error'),
  success: (message) => show(message, 'success'),
}

// Mengembalikan Promise<boolean>: true bila user menekan tombol konfirmasi.
export function confirmDialog(message, { title = 'Yakin?', confirmText = 'Ya, lanjut', danger = false } = {}) {
  feedback.dialog?.resolve(false) // dialog sebelumnya yang belum dijawab dianggap batal
  return new Promise((resolve) => {
    feedback.dialog = { message, title, confirmText, danger, resolve }
  })
}

export function answerDialog(ok) {
  const d = feedback.dialog
  feedback.dialog = null
  d?.resolve(ok)
}
