<script setup>
// Dashboard Manajemen Bisnis — halaman depan setelah login Management.
// 5 kartu ringkasan per divisi bisnis; tiap kartu masuk ke modul terkait.
// Kartu yang modulnya belum ada (Keuangan & Invoice, Pendaftaran) ditandai
// "Segera Hadir", bukan diisi angka karangan.
import { computed, onMounted, ref } from 'vue'
import api from '@/lib/api'

const loading = ref(true)
const totals = ref({ schools: 0, students: 0, trainers: 0 })
const todaySessions = ref(0)
// Absensi karyawan belum ada pencatatannya, jadi sengaja tetap 0 (bukan dari data lain).
// Ganti dengan data asli kalau fitur absensi karyawan sudah dibuat.
const totalEmployees = 0

async function loadStats() {
  loading.value = true
  try {
    const dashRes = await api.get('/dashboard')
    totals.value = dashRes.data.totals ?? { schools: 0, students: 0, trainers: 0 }
    todaySessions.value = dashRes.data.today?.sessions ?? 0
  } catch (e) {
    // Non-fatal — kartu tetap tampil, cuma angkanya nol/kosong.
  } finally {
    loading.value = false
  }
}

onMounted(loadStats)

// Warna ikon per kartu (hex langsung supaya pasti tampil, tidak bergantung token tema).
const tones = {
  blue: 'bg-[#E5EDFF] text-[#1D4ED8]',
  green: 'bg-[#DCFCE7] text-[#15803D]',
  violet: 'bg-[#EDE9FE] text-[#6D28D9]',
  amber: 'bg-[#FEF3C7] text-[#B45309]',
  teal: 'bg-[#CCFBF1] text-[#0F766E]',
}

// 4 kartu sederhana (angka tunggal atau "Segera Hadir"). Kartu Absensi dibuat terpisah di template.
const cards = computed(() => [
  {
    key: 'sekolah',
    to: '/management/sekolah-murid',
    label: 'Data Sekolah',
    icon: 'school',
    tone: 'blue',
    value: totals.value.schools,
    desc: 'Sekolah Mitra Keseluruhan',
  },
  {
    key: 'keuangan',
    to: '/management/keuangan',
    label: 'Data Keuangan & Invoice',
    icon: 'payments',
    tone: 'green',
    soon: true,
    desc: 'Modul verifikasi & pembayaran masih disiapkan',
  },
  {
    key: 'ekskul',
    to: '/management/ekstrakurikuler',
    label: 'Data Manajemen Ekstrakurikuler',
    icon: 'event_note',
    tone: 'violet',
    value: todaySessions.value,
    desc: 'Sesi untuk hari ini',
  },
  {
    key: 'pendaftaran',
    to: '/management/keputusan-pendaftaran',
    label: 'Data Pendaftaran',
    icon: 'how_to_reg',
    tone: 'amber',
    soon: true,
    desc: 'Jumlah siswa terdaftar menyusul',
  },
])

const absensiStats = computed(() => [
  { label: 'Trainer', value: totals.value.trainers },
  { label: 'Siswa', value: totals.value.students },
  { label: 'Karyawan', value: totalEmployees },
])

// Gaya bersama kartu: border jelas + bayangan halus supaya kelihatan di atas latar terang.
const cardBase =
  'group flex min-h-[190px] flex-col rounded-2xl border border-[#D5DCEB] bg-white p-6 shadow-[0_1px_3px_rgba(11,42,91,0.08),0_6px_16px_-6px_rgba(11,42,91,0.10)] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#9DB3E8] hover:shadow-[0_10px_28px_-8px_rgba(11,42,91,0.22)]'
const labelCls = 'text-[12px] font-bold uppercase leading-snug tracking-wider text-[#5B6577]'
const numberCls = 'text-[38px] font-extrabold leading-none tracking-tight text-[#0B1C30]'
const descCls = 'text-[14px] leading-snug text-[#5B6577]'
const footerCls = 'mt-auto flex items-center justify-between border-t border-[#E8EDF6] pt-4'
</script>

<template>
  <div class="mx-auto flex w-full max-w-[1200px] flex-col pb-space-3xl">
    <!-- Judul halaman -->
    <div class="flex flex-col gap-1.5 py-space-lg">
      <h1 class="text-[28px] font-bold leading-tight tracking-tight text-[#0B1C30]">Dashboard Manajemen Bisnis</h1>
      <p class="text-[15px] text-[#5B6577]">Ringkasan seluruh divisi After Schola. Pilih salah satu kartu untuk masuk ke datanya.</p>
    </div>

    <!-- Skeleton: bentuknya sama dengan layout asli -->
    <div v-if="loading" class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
      <div v-for="i in 4" :key="i" class="min-h-[190px] animate-pulse rounded-2xl border border-[#D5DCEB] bg-white"></div>
      <div class="min-h-[190px] animate-pulse rounded-2xl border border-[#D5DCEB] bg-white md:col-span-2"></div>
    </div>

    <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
      <!-- 4 kartu sederhana -->
      <RouterLink v-for="c in cards" :key="c.key" :to="c.to" :class="cardBase">
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="tones[c.tone]">
            <span class="material-symbols-outlined text-[22px]">{{ c.icon }}</span>
          </span>
          <span :class="labelCls" class="pt-0.5">{{ c.label }}</span>
        </div>

        <div class="mt-4 flex flex-col gap-1.5">
          <span v-if="c.soon" class="inline-flex w-fit items-center rounded-full bg-[#EEF2FF] px-3 py-1 text-[12px] font-semibold text-[#3730A3]">Segera Hadir</span>
          <span v-else :class="numberCls">{{ c.value }}</span>
          <span :class="descCls">{{ c.desc }}</span>
        </div>

        <div :class="footerCls" class="mt-5">
          <span class="text-[14px] font-semibold text-primary">Lihat detail</span>
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#EEF2FF] text-primary transition-colors group-hover:bg-primary group-hover:text-on-primary">
            <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5">arrow_forward</span>
          </span>
        </div>
      </RouterLink>

      <!-- Data Absensi (melebar 2 kolom, 3 angka dibagi rata dengan garis pemisah) -->
      <RouterLink to="/management/absensi" :class="[cardBase, 'md:col-span-2']">
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="tones.teal">
            <span class="material-symbols-outlined text-[22px]">fact_check</span>
          </span>
          <span :class="labelCls" class="pt-0.5">Data Absensi</span>
        </div>

        <div class="mt-4 grid grid-cols-3 divide-x divide-[#E8EDF6]">
          <div v-for="s in absensiStats" :key="s.label" class="flex flex-col gap-1.5 px-5 first:pl-0">
            <span :class="numberCls">{{ s.value }}</span>
            <span :class="descCls">{{ s.label }}</span>
          </div>
        </div>

        <div :class="footerCls" class="mt-5">
          <span class="text-[14px] font-semibold text-primary">Lihat detail</span>
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#EEF2FF] text-primary transition-colors group-hover:bg-primary group-hover:text-on-primary">
            <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5">arrow_forward</span>
          </span>
        </div>
      </RouterLink>
    </div>
  </div>
</template>