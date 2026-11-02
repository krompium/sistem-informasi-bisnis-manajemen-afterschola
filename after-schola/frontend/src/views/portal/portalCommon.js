// Bantuan tampilan Portal Orang Tua: label tindakan dan warna status.
export const ACTION_LABELS = {
  lihat_hasil: 'Lihat hasil trial',
  daftar: 'Lengkapi pendaftaran',
  perbaiki_data: 'Perbaiki data',
  bayar: 'Bayar invoice',
}

export const STAGE_TONE = {
  menunggu_keputusan: 'bg-secondary-container text-on-secondary-container',
  siap_daftar: 'bg-secondary-container text-on-secondary-container',
  perlu_revisi: 'bg-error-container text-on-error-container',
  menunggu_bayar: 'bg-error-container text-on-error-container',
  aktif: 'bg-primary-fixed text-primary',
  tidak_lanjut: 'bg-surface-container text-on-surface-variant',
  tidak_hadir: 'bg-surface-container text-on-surface-variant',
}

export const toneOf = (stage) => STAGE_TONE[stage] || 'bg-surface-container-high text-on-surface'

export const inputCls =
  'w-full rounded-lg border border-outline-variant bg-white px-space-sm py-2.5 font-body-md text-body-md text-on-surface focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary/15'
export const labelCls = 'font-label-md text-label-md font-semibold text-on-surface-variant'

export const errMsg = (err, fallback) => {
  const errors = err.response?.data?.errors
  return (errors && Object.values(errors).flat()[0]) || err.response?.data?.message || fallback
}

// Tujuan tombol tindakan tiap anak. Satu tempat supaya dasbor dan detail konsisten.
export function actionTo(child) {
  if (['daftar', 'perbaiki_data'].includes(child.next_action)) return `/portal/anak/${child.id}/pendaftaran`
  if (child.next_action === 'bayar') return '/portal/tagihan'
  return `/portal/anak/${child.id}`
}
