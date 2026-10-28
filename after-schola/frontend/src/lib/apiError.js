// Ubah error axios menjadi satu kalimat untuk ditampilkan ke user.
// Backend memakai 422 untuk validasi dan penolakan aturan bisnis (konvensi §3.2).
export function apiErrorMessage(err, fallback) {
  const res = err?.response
  if (res?.status === 422) {
    return Object.values(res.data?.errors ?? {}).flat().join(' ') || 'Data tidak valid.'
  }
  if (res?.status === 403) return 'Anda tidak berwenang melakukan aksi ini.'
  if (res?.status === 404) return 'Data tidak ditemukan.'
  return fallback
}
