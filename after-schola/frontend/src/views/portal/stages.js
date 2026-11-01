// Tahap anak di portal orang tua (dari API: stage). Dipakai dashboard dan halaman detail.
const amber = 'bg-[#FEF3C7] text-[#92400E]'
const blue = 'bg-[#DBEAFE] text-[#1E40AF]'
const green = 'bg-[#D1FAE5] text-[#065F46]'
const grey = 'bg-surface-container text-on-surface-variant'
const red = 'bg-error-container text-on-error-container'

export const STAGES = {
  trial_terjadwal: { label: 'Trial terjadwal', cls: blue, action: 'Lihat jadwal trial' },
  tidak_hadir: { label: 'Tidak hadir trial', cls: grey, action: 'Lihat detail' },
  hasil_trial: { label: 'Hasil trial siap', cls: green, action: 'Lihat hasil & putuskan' },
  tidak_lanjut: { label: 'Tidak melanjutkan', cls: grey, action: 'Lihat detail' },
  isi_pendaftaran: { label: 'Lengkapi pendaftaran', cls: amber, action: 'Isi pendaftaran' },
  perlu_revisi: { label: 'Perlu revisi data', cls: red, action: 'Perbaiki data' },
  menunggu_verifikasi: { label: 'Menunggu verifikasi', cls: blue, action: 'Lihat status' },
  menunggu_bayar: { label: 'Menunggu pembayaran', cls: amber, action: 'Lihat invoice' },
  menunggu_konfirmasi: { label: 'Pembayaran diperiksa', cls: blue, action: 'Lihat status' },
  lunas: { label: 'Lunas', cls: green, action: 'Lihat detail' },
}

export const LEVELS = { sd: 'SD', smp: 'SMP', sma: 'SMA' }
