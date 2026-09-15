import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Jalankan `fn` berkala (default tiap 1 detik).
 * - Melewati tick saat tab tidak terlihat (hemat request).
 * - Anti-overlap: menunggu tick sebelumnya selesai dulu.
 */
export function usePolling(fn, intervalMs = 1000) {
  let timer = null
  let running = false

  async function tick() {
    if (document.visibilityState !== 'visible' || running) return
    running = true
    try {
      await fn()
    } catch {
      /* diamkan; refresh berkala bersifat non-kritis */
    } finally {
      running = false
    }
  }

  onMounted(() => {
    timer = setInterval(tick, intervalMs)
  })
  onBeforeUnmount(() => {
    if (timer) clearInterval(timer)
  })
}
