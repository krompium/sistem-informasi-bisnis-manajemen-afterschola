<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

// TODO(nanti): warna & font di bawah TEBAKAN dari screenshot — ganti dengan token asli dari CSS tema WordPress (variabel di <style> bawah).
// TODO(nanti): daftar program & lokasi masih hardcode — ambil dari API (PUB-01/02) setelah Modul 0 (cabang) dan Modul 1 (program) selesai.
// TODO(nanti): salin copy yang belum terbaca dari screenshot (jawaban FAQ, isi poin 2-4 "Mengapa Memilih", alamat footer) dari WordPress.

const stats = [['1000+', 'Pelajar'], ['20+', 'Mitra Sekolah'], ['5+', 'Lokasi']]
const heroIcons = ['code', 'menu_book', 'smart_toy', 'emoji_events', 'mic']

const programs = [
  { icon: 'code', name: 'Coding', desc: 'Belajar dasar-dasar pemrograman hingga membuat aplikasi dan game interaktif.' },
  { icon: 'smart_toy', name: 'Robotik', desc: 'Mempelajari teknologi robotika untuk memecahkan masalah dunia nyata.' },
  { icon: 'brush', name: 'ArtSchola', desc: 'Wadah pembelajaran seni yang dirancang untuk mengasah kreativitas, bakat, dan karakter.' },
  { icon: 'mic', name: 'Public Speaking & Podcast', desc: 'Mengembangkan keterampilan berbicara di depan umum dan menumbuhkan rasa percaya diri lewat media digital.' },
  { icon: 'translate', name: 'Bahasa Inggris', desc: 'Mempelajari kemampuan berbahasa Inggris melalui metode belajar yang menyenangkan.' },
  { icon: 'menu_book', name: 'Tahfiz', desc: 'Membantu siswa menghafal dan memahami Al-Quran dengan metode yang efektif.' },
]

const reasons = [
  {
    title: 'Program Beragam dan Berkualitas',
    body: [
      'Coding: belajar dasar-dasar pemrograman hingga membuat aplikasi dan game interaktif.',
      'Bahasa Inggris: berbicara dan berkomunikasi dengan metode yang menyenangkan.',
      'Robotik: mengeksplorasi teknologi robotika untuk memecahkan masalah dengan kreatif.',
      'Panahan: melatih konsentrasi dan disiplin lewat olahraga panahan tradisional.',
      'Public Speaking & Podcast: mengasah kemampuan berbicara lewat media digital.',
      'Tahfiz: menghafal dan memahami Al-Quran dengan metode yang efektif.',
      'Creativer: mengembangkan kreativitas lewat karya seni dan media.',
    ],
  },
  { title: 'Dukungan untuk Program Indonesia Emas 2045', body: ['Isi poin ini belum disalin dari WordPress.'] },
  { title: 'Pengajar Profesional dan Berpengalaman', body: ['Isi poin ini belum disalin dari WordPress.'] },
  { title: 'Metode Pembelajaran Inovatif', body: ['Isi poin ini belum disalin dari WordPress.'] },
]

const faqs = [
  { q: 'Apa itu AfterSchola?', a: 'AfterSchola adalah lembaga pendidikan nonformal yang menawarkan berbagai program pengembangan keterampilan untuk anak usia dini hingga remaja, termasuk Coding, Bahasa Inggris, Robotik, Brain Club, dan banyak lagi.' },
  { q: 'Program apa saja yang ditawarkan oleh AfterSchola?' },
  { q: 'Apakah kursus ini cocok untuk pemula?' },
  { q: 'Apa yang akan dipelajari di kursus ini?' },
  { q: 'Bagaimana cara mendaftar?' },
  { q: 'Apa metode pembayaran yang tersedia?' },
  { q: 'Apakah AfterSchola menyediakan kelas fleksibel, bukan hanya online?' },
  { q: 'Apa yang harus saya siapkan sebelum kelas?' },
  { q: 'Apakah ada program gratis?' },
]

const footerCols = [
  ['Layanan', ['Kelas Kursus', 'Karir', 'Magang/PKL']],
  ['Lokasi Kami', ['Jatinangor', 'Ciarantem Wetan', 'Ranggamalela']],
  ['Pusat Bantuan', ['Tentang Kami', 'Kebijakan Privasi', 'Syarat Pengguna', 'FAQ', 'Hubungi Kami']],
]

// Hitung mundur penutupan pendaftaran CROC 2026. Jam 20.00 WIB hanya tebakan dari screenshot.
const deadline = new Date('2026-11-10T20:00:00+07:00').getTime()
const now = ref(Date.now())
let timer
onMounted(() => { timer = setInterval(() => (now.value = Date.now()), 1000) })
onUnmounted(() => clearInterval(timer))
const countdown = computed(() => {
  const s = Math.max(0, Math.floor((deadline - now.value) / 1000))
  return [['Hari', Math.floor(s / 86400)], ['Jam', Math.floor(s / 3600) % 24], ['Menit', Math.floor(s / 60) % 60], ['Detik', s % 60]]
})
</script>

<template>
  <div class="landing bg-white text-slate-800">
    <header class="sticky top-0 z-30 bg-[var(--navy)] text-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between px-5 py-3">
        <!-- TODO(nanti): ganti teks dengan logo dari wp-content/uploads -->
        <span class="text-xl font-extrabold tracking-tight">AfterSchola</span>
        <router-link to="/login" class="rounded-full border border-white/40 px-4 py-2 text-sm font-semibold">Masuk</router-link>
      </div>
    </header>

    <!-- Hero -->
    <section class="bg-gradient-to-b from-[var(--navy)] to-[#0e3a7d] text-white">
      <div class="mx-auto grid max-w-5xl gap-10 px-5 py-12 md:grid-cols-2 md:items-center md:py-20">
        <div>
          <p class="inline-block rounded-full bg-white/10 px-4 py-1.5 text-xs">Kelas coding, robotik &amp; bahasa Inggris untuk anak</p>
          <h1 class="mt-5 text-3xl font-extrabold leading-tight md:text-5xl">
            Belajar Hari Ini, <span class="text-[var(--sun)]">Berkarya</span> untuk Indonesia Emas 2045
          </h1>
          <p class="mt-4 max-w-md text-white/80">
            AfterSchola adalah mitra terpercaya yang membantu anak-anak Indonesia mengembangkan potensi terbaik mereka lewat kelas yang seru dan bermanfaat.
          </p>
          <div class="mt-6 flex flex-wrap gap-3">
            <router-link to="/daftar-trial" class="rounded-full bg-[var(--sun)] px-6 py-3 font-bold text-[var(--navy)]">Coba Kelas Gratis</router-link>
            <a href="#program" class="rounded-full border border-white/50 px-6 py-3 font-semibold">Lihat Program</a>
          </div>
          <div class="mt-8 flex gap-8">
            <div v-for="[n, label] in stats" :key="label">
              <p class="text-2xl font-extrabold">{{ n }}</p>
              <p class="text-xs text-white/70">{{ label }}</p>
            </div>
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-3" aria-hidden="true">
          <span
            v-for="(icon, k) in heroIcons"
            :key="icon"
            class="grid h-24 w-24 place-items-center [clip-path:polygon(50%_0,100%_25%,100%_75%,50%_100%,0_75%,0_25%)]"
            :class="k % 2 ? 'bg-white text-[var(--navy)]' : 'bg-[var(--sun)] text-[var(--navy)]'"
          >
            <span class="material-symbols-outlined text-4xl">{{ icon }}</span>
          </span>
        </div>
      </div>
    </section>

    <!-- Kompetisi -->
    <section class="bg-slate-50 px-5 py-12">
      <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-2 md:items-center">
        <!-- TODO(nanti): ganti dengan poster asli dari wp-content/uploads -->
        <div class="grid aspect-[3/4] place-items-center rounded-2xl bg-gradient-to-br from-[var(--navy)] to-[#1d4ed8] text-center text-white">
          <div>
            <p class="text-sm">Pendaftaran dibuka</p>
            <p class="text-5xl font-extrabold">CROC</p>
            <p class="text-sm">Coding Robotic Competition</p>
          </div>
        </div>
        <div>
          <p class="text-xs font-bold tracking-wide text-[var(--teal)]">KOMPETISI NASIONAL</p>
          <h2 class="mt-1 text-3xl font-extrabold text-[var(--navy)]">CROC 2026</h2>
          <p class="font-semibold">Coding Robotic Competition 2026</p>
          <p class="mt-3 text-slate-600">Coding Robotic Competition tingkat nasional hadir untuk kamu yang siap mengembangkan coding, robotik, kreativitas, dan strategi!</p>
          <div class="mt-5 rounded-xl bg-white p-4 shadow-sm">
            <p class="text-xs text-slate-500">Pendaftaran ditutup dalam (7 Sep – 10 Nov 2026)</p>
            <div class="mt-2 flex gap-3">
              <div v-for="[label, v] in countdown" :key="label" class="min-w-14 rounded-lg bg-[var(--navy)] px-2 py-2 text-center text-white">
                <p class="text-xl font-extrabold tabular-nums">{{ String(v).padStart(2, '0') }}</p>
                <p class="text-[10px]">{{ label }}</p>
              </div>
            </div>
          </div>
          <dl class="mt-4 grid grid-cols-3 gap-2 text-sm">
            <div><dt class="text-xs text-slate-500">Tanggal</dt><dd class="font-bold">14 November 2026</dd></div>
            <div><dt class="text-xs text-slate-500">Lokasi</dt><dd class="font-bold">Bandung</dd></div>
            <div><dt class="text-xs text-slate-500">Biaya</dt><dd class="font-bold">Rp200.000/peserta</dd></div>
          </dl>
          <ul class="mt-4 flex flex-wrap gap-2 text-xs">
            <li class="rounded-full bg-[var(--teal)]/10 px-3 py-1">Sudah termasuk makan siang</li>
            <li class="rounded-full bg-[var(--teal)]/10 px-3 py-1">Hadiah &amp; uang pembinaan</li>
            <li class="rounded-full bg-[var(--teal)]/10 px-3 py-1">Sertifikat untuk peserta</li>
          </ul>
          <!-- TODO(nanti): arahkan tombol ke halaman/form lomba CROC (di luar alur trial) -->
          <div class="mt-5 flex flex-wrap gap-2">
            <a href="#" class="rounded-full bg-[var(--teal)] px-5 py-3 text-sm font-bold text-white">Lihat Detail Lomba</a>
            <a href="#" class="rounded-full border border-[var(--navy)] px-5 py-3 text-sm font-semibold text-[var(--navy)]">Daftar Coding</a>
            <a href="#" class="rounded-full border border-[var(--navy)] px-5 py-3 text-sm font-semibold text-[var(--navy)]">Daftar Robotik</a>
          </div>
        </div>
      </div>
    </section>

    <!-- Perkenalan -->
    <section class="px-5 py-12 text-center">
      <h2 class="text-3xl font-extrabold text-[var(--navy)]">Hi, Kami AfterSchola!</h2>
      <p class="mx-auto mt-3 max-w-2xl text-slate-600">
        Kami hadir untuk membantu anak mengembangkan keterampilan dan bakat mereka melalui kegiatan seru dan bermanfaat. Dari public speaking hingga coding, kami menawarkan program yang dirancang khusus untuk mendukung kreativitas dan kepercayaan diri anak.
      </p>
      <div class="mx-auto mt-8 grid max-w-3xl grid-cols-3 gap-4">
        <div v-for="([icon, n, label]) in [['school', '1000+', 'Siswa Aktif Terdaftar'], ['handshake', '20+', 'Mitra Sekolah'], ['location_on', '5+', 'Lokasi']]" :key="label">
          <span class="material-symbols-outlined grid h-16 w-16 place-items-center rounded-full bg-[var(--sun)]/30 text-3xl text-[var(--navy)] mx-auto">{{ icon }}</span>
          <p class="mt-2 text-2xl font-extrabold text-[var(--navy)]">{{ n }}</p>
          <p class="text-xs text-slate-600">{{ label }}</p>
        </div>
      </div>
    </section>

    <!-- Mitra sekolah -->
    <section class="bg-[var(--sun)] px-5 py-12 text-[var(--navy)]">
      <div class="mx-auto max-w-5xl text-center">
        <h2 class="text-3xl font-extrabold">Mitra Sekolah Kami</h2>
        <p class="mx-auto mt-3 max-w-2xl">
          Di AfterSchola, kami bangga dapat bekerja sama dengan berbagai sekolah yang memiliki visi yang sama dalam mengembangkan potensi anak-anak. Bersama mitra sekolah, kami membantu anak-anak menemukan minat dan bakatnya melalui program-program inovatif dan menyenangkan.
        </p>
        <!-- TODO(nanti): ganti lingkaran placeholder dengan logo mitra dari wp-content/uploads -->
        <div class="mt-8 grid grid-cols-4 justify-items-center gap-4 md:grid-cols-6">
          <span v-for="n in 12" :key="n" class="material-symbols-outlined grid h-16 w-16 place-items-center rounded-full bg-white text-3xl">school</span>
        </div>
      </div>
    </section>

    <!-- Mengapa memilih -->
    <section class="px-5 py-12">
      <div class="mx-auto max-w-3xl">
        <p class="text-center text-xs font-bold tracking-wide text-[var(--teal)]">ALASAN TEPAT UNTUK PENDIDIKAN BERKUALITAS</p>
        <h2 class="mt-1 text-center text-3xl font-extrabold text-[var(--navy)]">Mengapa Memilih AfterSchola?</h2>
        <div class="mt-6 space-y-3">
          <details v-for="(r, i) in reasons" :key="r.title" name="why" :open="i === 0" class="group overflow-hidden rounded-xl border border-slate-200">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-4 py-3 font-bold [&::-webkit-details-marker]:hidden" :class="'group-open:bg-[var(--navy)] group-open:text-white'">
              <span>{{ i + 1 }}. {{ r.title }}</span>
              <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
            </summary>
            <ul class="list-disc space-y-1 px-8 py-4 text-sm text-slate-600">
              <li v-for="line in r.body" :key="line">{{ line }}</li>
            </ul>
          </details>
        </div>
      </div>
    </section>

    <!-- Program -->
    <section id="program" class="bg-slate-50 px-5 py-12">
      <div class="mx-auto max-w-5xl">
        <p class="text-center text-xs font-bold tracking-wide text-[var(--teal)]">PILIHAN PROGRAM</p>
        <h2 class="mt-1 text-center text-3xl font-extrabold text-[var(--navy)]">Program Kursus AfterSchola</h2>
        <p class="mx-auto mt-3 max-w-xl text-center text-slate-600">AfterSchola menawarkan berbagai kursus yang dirancang untuk mengembangkan keterampilan siswa di berbagai bidang, seperti coding, robotik, seni, public speaking, bahasa, dan Tahfiz.</p>
        <div class="mt-8 grid grid-cols-2 gap-4 md:grid-cols-3">
          <article v-for="p in programs" :key="p.name" class="rounded-2xl border border-slate-200 bg-white p-4">
            <span class="material-symbols-outlined text-3xl text-[var(--teal)]">{{ p.icon }}</span>
            <h3 class="mt-2 font-bold text-[var(--navy)]">{{ p.name }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ p.desc }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- Tempat belajar -->
    <section class="px-5 py-12">
      <div class="mx-auto max-w-5xl">
        <p class="text-xs font-bold tracking-wide text-[var(--teal)]">TEMPAT</p>
        <h2 class="mt-1 text-3xl font-extrabold text-[var(--navy)]">Dimana Pembelajaran Dilakukan</h2>
        <p class="mt-3 max-w-2xl text-slate-600">AfterSchola menyediakan kelas online dan offline untuk memastikan setiap siswa dapat belajar sesuai kebutuhan mereka, baik dari rumah maupun langsung di lokasi kami.</p>
        <div class="mt-5 space-y-4">
          <div>
            <p class="flex items-center gap-2 font-bold text-[var(--navy)]"><span class="material-symbols-outlined text-[var(--teal)]">verified_user</span>Offline Class</p>
            <p class="text-sm text-slate-600">Belajar langsung di lokasi kami dengan fasilitas lengkap dan bimbingan tatap muka dari pengajar profesional.</p>
          </div>
          <div>
            <p class="flex items-center gap-2 font-bold text-[var(--navy)]"><span class="material-symbols-outlined text-[var(--teal)]">laptop_mac</span>Online Class</p>
            <p class="text-sm text-slate-600">Menikmati pembelajaran lebih jauh melalui platform digital dengan akses mudah dari mana saja.</p>
          </div>
        </div>
        <!-- TODO(nanti): ganti placeholder dengan 4 foto kelas dari wp-content/uploads -->
        <div class="mt-8 grid grid-cols-2 gap-3">
          <div v-for="n in 4" :key="n" class="grid aspect-[3/4] place-items-center rounded-2xl bg-gradient-to-br from-[var(--sun)]/40 to-[var(--teal)]/30 text-[var(--navy)]">
            <span class="material-symbols-outlined text-5xl">photo_camera</span>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="bg-gradient-to-br from-[var(--navy)] to-[#0e5a73] px-5 py-14 text-center text-white">
      <h2 class="text-3xl font-extrabold">Siap Bertumbuh Bersama AfterSchola?</h2>
      <router-link to="/daftar-trial" class="mt-6 inline-block rounded-full bg-[var(--sun)] px-8 py-3 font-bold text-[var(--navy)]">Daftar Sekarang</router-link>
    </section>

    <!-- FAQ -->
    <section class="px-5 py-12">
      <div class="mx-auto max-w-3xl">
        <h2 class="text-center text-3xl font-extrabold text-[var(--navy)]">Pertanyaan Sering Ditanyakan</h2>
        <div class="mt-6 divide-y divide-slate-200 rounded-xl border border-slate-200">
          <details v-for="(f, i) in faqs" :key="f.q" name="faq" :open="i === 0" class="group">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold text-[var(--navy)] [&::-webkit-details-marker]:hidden">
              {{ f.q }}
              <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
            </summary>
            <p class="px-4 pb-4 text-sm text-slate-600">{{ f.a ?? 'Hubungi kami untuk informasi lebih lanjut.' }}</p>
          </details>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[var(--navy)] px-5 py-10 text-white">
      <div class="mx-auto grid max-w-5xl gap-8 md:grid-cols-4">
        <div>
          <p class="text-xl font-extrabold">AfterSchola</p>
          <p class="mt-3 text-sm font-semibold">Office Center</p>
          <!-- TODO(nanti): salin alamat lengkap dari footer WordPress -->
          <p class="text-sm text-white/70">[alamat Office Center]</p>
        </div>
        <nav v-for="[title, links] in footerCols" :key="title" :aria-label="title">
          <p class="font-bold">{{ title }}</p>
          <ul class="mt-3 space-y-2 text-sm text-white/70">
            <li v-for="l in links" :key="l">{{ l }}</li>
          </ul>
        </nav>
      </div>
      <p class="mx-auto mt-8 max-w-5xl border-t border-white/10 pt-4 text-xs text-white/60">© 2026 AfterSchola</p>
    </footer>

    <!-- TODO(nanti): isi nomor WhatsApp asli -->
    <a href="https://wa.me/" target="_blank" rel="noopener" aria-label="Hubungi lewat WhatsApp" class="fixed bottom-5 right-5 z-40 grid h-12 w-12 place-items-center rounded-full bg-[#25d366] text-white shadow-lg">
      <span class="material-symbols-outlined">chat</span>
    </a>
  </div>
</template>

<style scoped>
/* Semua warna tebakan ada di sini: ganti sekali, berlaku di seluruh halaman. */
.landing {
  --navy: #0b2a5b;
  --sun: #f8b21b;
  --teal: #13a58c;
}
@media (prefers-reduced-motion: reduce) {
  * { transition: none !important; }
}
</style>
