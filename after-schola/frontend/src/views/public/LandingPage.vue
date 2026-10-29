<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'

// Gerbang utama publik: tombol coba gratis -> form trial, Masuk -> login.
// TODO(nanti): tautan menu Program/Kelas, Tentang Kami, Kebijakan Privasi, dan Syarat Penggunaan
// masih placeholder (#program / #) karena halamannya belum ada. Kartu program masih statis;
// bisa diganti GET /api/public/programs setelah slice 3 Modul 1.

// Batas pendaftaran kompetisi CROC 2026
const deadline = new Date('2026-11-10T20:00:00+07:00').getTime()
const countdown = reactive({ days: '00', hours: '00', minutes: '00', seconds: '00' })
const tick = ref(false)
let timer = null

const pad = (n) => String(n).padStart(2, '0')

function updateCountdown() {
  const diff = Math.max(0, Math.floor((deadline - Date.now()) / 1000))
  countdown.days = pad(Math.floor(diff / 86400))
  countdown.hours = pad(Math.floor((diff % 86400) / 3600))
  countdown.minutes = pad(Math.floor((diff % 3600) / 60))
  countdown.seconds = pad(diff % 60)
  tick.value = true
  setTimeout(() => (tick.value = false), 200)
}

onMounted(() => {
  document.documentElement.classList.add('scroll-smooth')
  updateCountdown()
  timer = setInterval(updateCountdown, 1000)
})

onBeforeUnmount(() => {
  clearInterval(timer)
  document.documentElement.classList.remove('scroll-smooth')
})

const partners = [
  {"icon": "school", "color": "text-emerald-700", "bob": "partner-bob-odd"},
  {"icon": "local_library", "color": "text-blue-700", "bob": "partner-bob-even"},
  {"icon": "verified", "color": "text-rose-600", "bob": "partner-bob-tri"},
  {"icon": "auto_stories", "color": "text-indigo-700", "bob": "partner-bob-odd"},
  {"icon": "military_tech", "color": "text-amber-600", "bob": "partner-bob-even"},
  {"icon": "history_edu", "color": "text-teal-700", "bob": "partner-bob-tri"},
  {"icon": "menu_book", "color": "text-purple-700", "bob": "partner-bob-odd"},
  {"icon": "workspace_premium", "color": "text-cyan-700", "bob": "partner-bob-even"},
  {"icon": "diversity_3", "color": "text-red-600", "bob": "partner-bob-tri"},
  {"icon": "emoji_events", "color": "text-green-700", "bob": "partner-bob-odd"},
  {"icon": "star", "color": "text-orange-600", "bob": "partner-bob-even"},
  {"icon": "shield", "color": "text-blue-900", "bob": "partner-bob-tri"},
  {"icon": "psychology", "color": "text-lime-700", "bob": "partner-bob-odd"},
  {"icon": "lightbulb", "color": "text-yellow-600", "bob": "partner-bob-even"},
  {"icon": "child_care", "color": "text-sky-700", "bob": "partner-bob-tri"},
  {"icon": "family_restroom", "color": "text-pink-600", "bob": "partner-bob-odd"},
]

const programs = [
  {"icon": "developer_mode", "tone": "bg-blue-50 text-blue-600", "wiggle": "animate-idle-wiggle", "title": "Coding", "desc": "Belajar dasar-dasar pemrograman hingga menciptakan aplikasi dan game interaktif."},
  {"icon": "smart_toy", "tone": "bg-emerald-50 text-emerald-600", "wiggle": "animate-idle-wiggle-delay-1", "title": "Robotik", "desc": "Mengeksplorasi teknologi robotika untuk mempersiapkan siswa dalam era digital."},
  {"icon": "brush", "tone": "bg-indigo-50 text-indigo-600", "wiggle": "animate-idle-wiggle-delay-2", "title": "ArtSchola", "desc": "Wadah pembelajaran seni yang dirancang untuk mengembangkan kreativitas, bakat, dan keterampilan."},
  {"icon": "podcasts", "tone": "bg-purple-50 text-purple-600", "wiggle": "animate-idle-wiggle", "title": "Public Speaking & Podcast", "desc": "Mengasah kemampuan berbicara di depan umum dan menyampaikan ide kreatif melalui media digital."},
  {"icon": "translate", "tone": "bg-amber-50 text-amber-600", "wiggle": "animate-idle-wiggle-delay-1", "title": "Bahasa Inggris", "desc": "Mengasah kemampuan berbahasa Inggris melalui metode belajar yang menyenangkan."},
  {"icon": "menu_book", "tone": "bg-teal-50 text-teal-600", "wiggle": "animate-idle-wiggle-delay-2", "title": "Tahfiz", "desc": "Membantu siswa mendalami dan menghafal Al-Quran dengan metode yang efektif."},
]

const faqs = [
  {"q": "Apa itu AfterSchola?", "a": "AfterSchola adalah lembaga pendidikan nonformal yang menawarkan berbagai program pengembangan keterampilan untuk anak-anak dan remaja, termasuk Coding, Bahasa Inggris, Robotik, Brain Club, dan banyak lagi."},
  {"q": "Program apa saja yang ditawarkan oleh AfterSchola?", "a": "AfterSchola menawarkan program seperti Coding, Bahasa Inggris, Robotik, Panahan, Tahfiz, Public Speaking, Podcast, dan Creativor."},
  {"q": "Apakah kursus ini cocok untuk pemula?", "a": "Ya, kursus ini dirancang khusus untuk pemula, tanpa memerlukan pengalaman coding sebelumnya."},
  {"q": "Apa yang akan dipelajari di kursus ini?", "a": "Kamu akan mempelajari dasar-dasar coding, membuat animasi, dan game sederhana menggunakan Scratch serta logika komputasi bertahap."},
  {"q": "Bagaimana cara mendaftar?", "a": "Anda dapat mendaftar melalui website resmi AfterSchola atau menghubungi tim kami melalui WhatsApp untuk panduan pendaftaran secara langsung."},
  {"q": "Apa metode pembayaran yang tersedia?", "a": "Kami menerima transfer bank, e-wallet (OVO, Gopay), dan kartu kredit."},
  {"q": "Apakah AfterSchola memiliki lokasi fisik atau hanya online?", "a": "AfterSchola memiliki beberapa lokasi fisik di Bandung dan sekitarnya serta juga menyediakan kelas online yang dapat diakses dari mana saja."},
  {"q": "Apa yang harus saya siapkan sebelum kelas?", "a": "Laptop/PC, koneksi internet stabil, dan aplikasi Zoom (untuk kelas online)."},
  {"q": "Apakah ada program gratis?", "a": "Ya! Kami menyediakan sesi trial gratis untuk pertemuan pertama sehingga anak dapat mencoba pengalaman serunya terlebih dahulu."},
]
</script>

<template>
  <div class="bg-white text-slate-800 font-landing antialiased selection:bg-brandSun selection:text-brandNavy">
    <!-- BEGIN: NavigationBar -->
    <header class="sticky top-0 z-50 bg-brandDeepNavy text-white shadow-md border-b border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
    <!-- Logo Container -->
    <a aria-label="Beranda AfterSchola" class="flex items-center gap-2 group transition-transform hover:scale-105" href="#">
    <img alt="Logo AfterSchola" class="h-10 sm:h-12 w-auto object-contain brightness-0 invert" src="https://lh3.googleusercontent.com/aida/AEtjO1UxSKBkKJ1MNN3I94yhMlMnI9BgdsPcuGS0AtwRuNwc4u7YXZhZQpG4igiFjdD6IxOn2hSTJIXT8mkb2QED3JFuYn_3gVwKRR-7qsCGvnTISlbfMlRu94DzEqGy_Xq9HN3bYQS13dBLaVXCYdjUZHj8xICg-wwHhCiMhi4zU-Xl1K8XbAcI6PwdTuHzgnCs9zoAAEDn9tl8xYwhbzSTaCAHF3gYP2NhzYNof4-9Y0cXlA"/>
    </a>
    <!-- Desktop Menu Items -->
    <nav class="hidden md:flex items-center space-x-8 text-sm font-medium">
    <a class="text-white/90 hover:text-brandSun transition-colors" href="#kompetisi">Kompetisi</a>
    <!-- Dropdown: Program -->
    <div class="relative group">
    <button aria-haspopup="true" class="flex items-center gap-1 text-white/90 hover:text-brandSun transition-colors py-2 focus:outline-none" type="button">
    <span>Program</span>
    <span class="material-symbols-outlined text-lg leading-none transition-transform group-hover:rotate-180">expand_more</span>
    </button>
    <div class="absolute left-0 mt-2 w-48 rounded-xl bg-white text-slate-800 shadow-xl py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 border border-slate-100">
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Program Offline</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Program Online</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Ekskul Sekolah</a>
    </div>
    </div>
    <!-- Dropdown: Kelas -->
    <div class="relative group">
    <button aria-haspopup="true" class="flex items-center gap-1 text-white/90 hover:text-brandSun transition-colors py-2 focus:outline-none" type="button">
    <span>Kelas</span>
    <span class="material-symbols-outlined text-lg leading-none transition-transform group-hover:rotate-180">expand_more</span>
    </button>
    <div class="absolute left-0 mt-2 w-52 rounded-xl bg-white text-slate-800 shadow-xl py-2 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 border border-slate-100">
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Coding</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Creativor</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Public Speaking</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Bahasa Inggris</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Tahfidz Quran</a>
    <a class="block px-4 py-2 text-sm hover:bg-slate-50 hover:text-brandNavy transition-colors" href="#program">Panahan (soon)</a>
    </div>
    </div>
    </nav>
    <!-- CTA Button -->
    <div class="flex items-center gap-4">
    <RouterLink
    :to="{ name: 'login' }"
    class="inline-flex items-center gap-1.5 text-white/90 hover:text-brandSun text-sm font-semibold transition-colors"
    >
    <span class="material-symbols-outlined text-lg">login</span>
    <span>Masuk</span>
    </RouterLink>
    <RouterLink :to="{ name: 'public.trial-registration' }" class="inline-flex items-center gap-2 bg-brandGreen hover:bg-emerald-600 text-white font-semibold text-sm px-6 py-2.5 rounded-full shadow-lg shadow-brandGreen/25 btn-bouncy">
    <span>Coba Gratis</span>
    </RouterLink>
    </div>
    </div>
    </header>
    <!-- END: NavigationBar -->
    <main>
    <!-- BEGIN: HeroSection -->
    <section class="relative bg-gradient-to-b from-brandDeepNavy via-brandNavy to-[#0b3c7b] text-white pt-10 pb-20 md:py-24 overflow-hidden">
    <!-- Background decoration dots, stars, and shapes -->
    <div aria-hidden="true" class="absolute inset-0 pointer-events-none overflow-hidden">
    <div class="absolute top-10 left-10 w-96 h-96 rounded-full bg-brandSun/15 blur-3xl"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 rounded-full bg-brandTeal/20 blur-3xl"></div>
    <div class="absolute top-1/3 left-1/2 -translate-x-1/2 w-64 h-64 rounded-full bg-white/5 blur-2xl"></div>
    <!-- Ambient Playful Floating Doodles & Sparkles -->
    <span class="material-symbols-outlined absolute top-12 left-[12%] text-brandSun text-3xl animate-twinkle-pulse select-none">hotel_class</span>
    <span class="material-symbols-outlined absolute bottom-24 left-[7%] text-cyan-300/40 text-4xl animate-twinkle-delay select-none">bubble_chart</span>
    <span class="material-symbols-outlined absolute top-20 right-[15%] text-amber-300 text-3xl animate-twinkle-pulse-delay select-none">auto_awesome</span>
    <span class="material-symbols-outlined absolute bottom-14 right-[28%] text-brandTeal/60 text-2xl animate-twinkle-delay select-none">star</span>
    <span class="material-symbols-outlined absolute top-1/2 left-[3%] text-amber-200/35 text-2xl animate-twinkle select-none">sparkles</span>
    <!-- Floating Mini Rocket & Paper Plane Doodles -->
    <div class="absolute top-1/4 left-[4%] hidden sm:flex items-center gap-1 opacity-70 animate-floating-slow select-none">
    <span class="material-symbols-outlined text-amber-300 text-3xl rotate-45">rocket_launch</span>
    <span class="w-1.5 h-1.5 rounded-full bg-amber-400/80 animate-ping"></span>
    </div>
    <div class="absolute bottom-24 right-[8%] hidden md:flex items-center opacity-60 animate-floating-slow-delay select-none">
    <span class="material-symbols-outlined text-cyan-300 text-3xl -rotate-12">send</span>
    </div>
    <!-- Floating Colorful Bubbles -->
    <div class="absolute top-1/3 left-[28%] w-8 h-8 rounded-full bg-gradient-to-tr from-brandSun/20 to-amber-200/30 backdrop-blur-sm border border-brandSun/30 animate-bubble-drift"></div>
    <div class="absolute bottom-1/4 left-[45%] w-6 h-6 rounded-full bg-gradient-to-tr from-cyan-400/20 to-teal-200/30 backdrop-blur-sm border border-cyan-300/30 animate-bubble-drift-delay"></div>
    <div class="absolute top-16 right-[38%] w-5 h-5 rounded-full bg-gradient-to-tr from-purple-400/20 to-pink-200/20 backdrop-blur-sm border border-purple-300/30 animate-bubble-drift"></div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="grid lg:grid-cols-12 gap-12 items-center">
    <!-- Hero Left Column Content -->
    <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
    <!-- Program pill with gentle ambient breathe glow -->
    <div class="inline-flex items-center gap-2 bg-white/10 backdrop-blur-md px-4 py-1.5 rounded-full border border-white/15 text-xs sm:text-sm font-medium text-slate-200 shadow-sm animate-badge-breathe transition-colors">
    <span class="w-2 h-2 rounded-full bg-brandSun animate-ping"></span>
    <span>Kelas coding, robotik &amp; bahasa Inggris untuk anak</span>
    </div>
    <!-- Main Heading with Warm Shimmering "Berkarya" -->
    <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.15]">
                  Belajar Hari Ini, <br class="hidden sm:inline"/>
    <span class="inline-block text-brandSun drop-shadow-sm underline decoration-brandSun/40 underline-offset-8 animate-text-shimmer">Berkarya</span> untuk Indonesia Emas 2045
                </h1>
    <!-- Subtitle Description -->
    <p class="text-base sm:text-lg text-slate-200/90 max-w-xl mx-auto lg:mx-0 font-normal leading-relaxed">
                  AfterSchola adalah mitra terpercaya yang membantu anak-anak Indonesia mengembangkan potensi terbaik mereka lewat kelas yang seru dan bermanfaat.
                </p>
    <!-- Buttons -->
    <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
    <RouterLink :to="{ name: 'public.trial-registration' }" class="inline-flex items-center gap-2 bg-brandSun hover:bg-brandSunDark text-brandDeepNavy font-bold px-7 py-3.5 rounded-full shadow-lg shadow-brandSun/30 btn-bouncy group">
    <span>Coba Kelas Gratis</span>
    <span class="material-symbols-outlined text-lg transition-transform group-hover:translate-x-1">arrow_forward</span>
    </RouterLink>
    <a class="inline-flex items-center gap-2 bg-transparent hover:bg-white/10 text-white font-semibold px-7 py-3.5 rounded-full border border-white/40 transition-all hover:scale-105 active:scale-95" href="#program">
    <span>Lihat Program</span>
    </a>
    </div>
    <!-- Stats Bar -->
    <div class="pt-8 grid grid-cols-3 gap-4 border-t border-white/15 max-w-md mx-auto lg:mx-0 text-center lg:text-left">
    <div class="group cursor-default">
    <p class="text-2xl sm:text-3xl font-extrabold text-white group-hover:text-brandSun transition-colors">1000+</p>
    <p class="text-xs sm:text-sm text-slate-300">Pelajar</p>
    </div>
    <div class="group cursor-default">
    <p class="text-2xl sm:text-3xl font-extrabold text-white group-hover:text-brandSun transition-colors">20+</p>
    <p class="text-xs sm:text-sm text-slate-300">Mitra Sekolah</p>
    </div>
    <div class="group cursor-default">
    <p class="text-2xl sm:text-3xl font-extrabold text-white group-hover:text-brandSun transition-colors">5+</p>
    <p class="text-xs sm:text-sm text-slate-300">Lokasi</p>
    </div>
    </div>
    </div>
    <!-- Hero Right Column Visual Graphic -->
    <div class="lg:col-span-5 flex flex-col items-center justify-center relative">
    <!-- Floating top date banner badge -->
    <div class="mb-4 bg-white/95 backdrop-blur shadow-lg rounded-xl px-4 py-2 border border-slate-200 text-brandNavy flex items-center gap-3 animate-float-playful hover:rotate-0 transition-transform">
    <span class="text-xs font-bold text-slate-500">14 November 2026</span>
    <span class="h-3 w-px bg-slate-300"></span>
    <span class="text-xs font-extrabold text-brandNavy flex items-center gap-1">
    <span class="material-symbols-outlined text-xs text-amber-500 animate-pulse">emoji_events</span>
      Kompetisi CROC 2026
    </span>
    </div>
    <!-- Hexagonal Collage Representation with Organic Staggered Floating Wave -->
    <div class="relative w-72 sm:w-80 h-72 sm:h-80 flex items-center justify-center">
    <!-- Center Hexagon (1000+ Pelajar) -->
    <div class="hexagon w-36 h-40 bg-gradient-to-br from-brandSun to-amber-500 text-brandNavy flex flex-col items-center justify-center shadow-2xl z-20 transform hover:scale-110 transition-transform cursor-pointer animate-float-center">
    <span class="text-xs font-bold uppercase tracking-wider text-brandNavy/80">Lebih</span>
    <span class="text-2xl font-black">1000+</span>
    <span class="text-xs font-semibold">Pelajar</span>
    </div>
    <!-- Top Left: Coding Hexagon -->
    <div class="hexagon absolute -top-2 left-6 w-24 h-28 bg-[#1e4e8c] text-white flex items-center justify-center z-10 border border-white/20 shadow-md hover:scale-110 hover:bg-blue-600 transition-all cursor-pointer animate-float-wave-1">
    <span class="material-symbols-outlined text-3xl font-bold">code</span>
    </div>
    <!-- Top Right: Robotik Hexagon -->
    <div class="hexagon absolute -top-2 right-6 w-24 h-28 bg-brandDeepNavy text-cyan-400 flex items-center justify-center z-10 border border-white/20 shadow-md hover:scale-110 hover:text-cyan-300 transition-all cursor-pointer animate-float-wave-2">
    <span class="material-symbols-outlined text-3xl">smart_toy</span>
    </div>
    <!-- Mid Left: Book Hexagon -->
    <div class="hexagon absolute top-24 -left-4 w-24 h-28 bg-[#163868] text-amber-200 flex items-center justify-center z-10 border border-white/20 shadow-md hover:scale-110 hover:bg-[#204a88] transition-all cursor-pointer animate-float-wave-3">
    <span class="material-symbols-outlined text-3xl">menu_book</span>
    </div>
    <!-- Mid Right: Mic / Public Speaking Hexagon -->
    <div class="hexagon absolute top-24 -right-4 w-24 h-28 bg-[#0b3366] text-white flex items-center justify-center z-10 border border-white/20 shadow-md hover:scale-110 hover:bg-indigo-700 transition-all cursor-pointer animate-float-wave-4">
    <span class="material-symbols-outlined text-3xl">mic</span>
    </div>
    <!-- Bottom Center Left: Trophy Hexagon -->
    <div class="hexagon absolute -bottom-4 left-10 w-24 h-28 bg-brandSun/90 text-brandDeepNavy flex items-center justify-center z-10 shadow-md hover:scale-110 hover:bg-brandSun transition-all cursor-pointer animate-float-wave-2">
    <span class="material-symbols-outlined text-3xl">emoji_events</span>
    </div>
    <!-- Bottom Center Right: Rocket Hexagon -->
    <div class="hexagon absolute -bottom-4 right-10 w-24 h-28 bg-emerald-600 text-white flex items-center justify-center z-10 shadow-md hover:scale-110 hover:bg-emerald-500 transition-all cursor-pointer animate-float-wave-1">
    <span class="material-symbols-outlined text-3xl">rocket_launch</span>
    </div>
    </div>
    <!-- Floating pill bottom check with soft breathing pulse -->
    <div class="mt-8 inline-flex items-center gap-2 bg-white text-brandNavy font-semibold text-xs px-5 py-2 rounded-full shadow-lg animate-badge-breathe-light">
    <span class="w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center text-[10px] font-bold">✓</span>
    <span>Kelas gratis tersedia</span>
    </div>
    </div>
    </div>
    </div>
    </section>
    <!-- END: HeroSection -->
    <!-- BEGIN: CompetitionSection -->
    <section class="py-16 bg-slate-50 relative overflow-hidden" id="kompetisi">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <!-- Big Competition Card -->
    <div class="bg-brandDeepNavy rounded-3xl p-6 sm:p-10 lg:p-12 text-white shadow-2xl border border-white/10 relative">
    <div class="grid lg:grid-cols-12 gap-8 items-center">
    <!-- Left Poster Banner Card -->
    <div class="lg:col-span-5 flex justify-center">
    <div class="w-full max-w-sm rounded-2xl bg-gradient-to-b from-[#1b3e75] to-[#0d2244] border-2 border-amber-400/40 p-6 text-center shadow-xl relative overflow-hidden animate-float-playful">
    <div class="absolute -right-12 -top-12 w-32 h-32 bg-amber-400/20 rounded-full blur-xl pointer-events-none"></div>
    <span class="inline-flex items-center gap-1.5 bg-amber-400/20 text-brandSun text-xs font-semibold px-3 py-1 rounded-full border border-brandSun/30 mb-3 animate-pulse">
    <span class="w-2 h-2 rounded-full bg-brandSun animate-ping"></span>
      PENDAFTARAN DIBUKA
    </span>
    <h3 class="text-4xl sm:text-5xl font-black text-white tracking-wider my-2">CROC</h3>
    <p class="text-brandSun font-bold tracking-wide text-sm">Coding Robotic Competition</p>
    <div class="my-6 py-4 border-y border-white/10">
    <span class="material-symbols-outlined text-6xl text-cyan-400 mb-2 inline-block transition-transform hover:rotate-12 cursor-pointer">precision_manufacturing</span>
    <p class="text-xs text-slate-300">Tingkat Nasional 2026</p>
    </div>
    <div class="bg-black/30 rounded-xl p-3 text-xs space-y-1 hover:bg-black/40 transition-colors">
    <p class="text-slate-300">Total Hadiah &amp; Beasiswa</p>
    <p class="text-lg font-bold text-amber-400">Jutaan Rupiah</p>
    </div>
    </div>
    </div>
    <!-- Right Competition Details -->
    <div class="lg:col-span-7 space-y-5">
    <div>
    <p class="text-xs font-extrabold tracking-widest text-cyan-400 uppercase">KOMPETISI NASIONAL</p>
    <h2 class="text-3xl sm:text-4xl font-extrabold text-white mt-1">CROC 2026</h2>
    <p class="text-slate-300 font-semibold text-base mt-1">Coding Robotic Competition 2026</p>
    </div>
    <p class="text-slate-300 text-sm leading-relaxed">
                    Coding Robotic Competition tingkat nasional hadir untuk kamu yang siap mengasah kemampuan coding, robotik, kreativitas, dan strategi!
                  </p>
    <!-- Countdown Box with Subtle Breathing Border Glow -->
    <div class="bg-[#0b2246] rounded-2xl p-4 border border-white/10 relative overflow-hidden shadow-inner">
    <div class="flex items-center justify-between mb-3">
    <p class="text-xs text-slate-400 font-medium">Pendaftaran ditutup dalam (7 Sep – 10 Nov 2026)</p>
    <span class="material-symbols-outlined text-amber-400/60 text-sm animate-spin" style="animation-duration: 12s;">hourglass_top</span>
    </div>
    <div class="grid grid-cols-4 gap-2 sm:gap-3 text-center">
    <div class="bg-brandDeepNavy rounded-xl py-2 px-1 border border-white/10 transform hover:scale-105 transition-transform animate-timer-glow">
    <span class="block text-2xl font-black text-amber-400 font-mono">{{ countdown.days }}</span>
    <span class="text-[10px] sm:text-xs text-slate-400 uppercase">Hari</span>
    </div>
    <div class="bg-brandDeepNavy rounded-xl py-2 px-1 border border-white/10 transform hover:scale-105 transition-transform animate-timer-glow-delay-1">
    <span class="block text-2xl font-black text-amber-400 font-mono">{{ countdown.hours }}</span>
    <span class="text-[10px] sm:text-xs text-slate-400 uppercase">Jam</span>
    </div>
    <div class="bg-brandDeepNavy rounded-xl py-2 px-1 border border-white/10 transform hover:scale-105 transition-transform animate-timer-glow-delay-2">
    <span class="block text-2xl font-black text-amber-400 font-mono">{{ countdown.minutes }}</span>
    <span class="text-[10px] sm:text-xs text-slate-400 uppercase">Menit</span>
    </div>
    <div class="bg-brandDeepNavy rounded-xl py-2 px-1 border border-white/10 transform hover:scale-105 transition-transform animate-timer-glow-delay-3">
    <span
    class="block text-2xl font-black text-amber-400 font-mono"
    :style="{ transform: tick ? 'scale(1.1)' : 'scale(1)' }"
    >{{ countdown.seconds }}</span>
    <span class="text-[10px] sm:text-xs text-slate-400 uppercase">Detik</span>
    </div>
    </div>
    </div>
    <!-- Meta specs: Tanggal, Lokasi, Biaya -->
    <div class="grid grid-cols-3 gap-3 text-xs sm:text-sm pt-1">
    <div>
    <span class="block text-slate-400 text-xs">Tanggal</span>
    <span class="font-bold text-white">14 November 2026</span>
    </div>
    <div>
    <span class="block text-slate-400 text-xs">Lokasi</span>
    <span class="font-bold text-white">Bandung</span>
    </div>
    <div>
    <span class="block text-slate-400 text-xs">Biaya</span>
    <span class="font-bold text-white">Rp200.000/peserta</span>
    </div>
    </div>
    <!-- Perks Badges -->
    <div class="flex flex-wrap gap-2 pt-1 text-xs">
    <span class="bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 px-3 py-1.5 rounded-full flex items-center gap-1.5 hover:bg-cyan-500/25 transition-colors cursor-default">
    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                      Sudah termasuk makan siang
                    </span>
    <span class="bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 px-3 py-1.5 rounded-full flex items-center gap-1.5 hover:bg-cyan-500/25 transition-colors cursor-default">
    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                      Hadiah &amp; uang pembinaan
                    </span>
    <span class="bg-cyan-500/15 text-cyan-300 border border-cyan-500/30 px-3 py-1.5 rounded-full flex items-center gap-1.5 hover:bg-cyan-500/25 transition-colors cursor-default">
    <span class="w-1.5 h-1.5 rounded-full bg-cyan-400"></span>
                      Sertifikat untuk peserta
                    </span>
    </div>
    <!-- CTA Links -->
    <div class="flex flex-wrap gap-3 pt-2">
    <a class="bg-brandTeal hover:bg-teal-600 text-white font-bold text-xs sm:text-sm px-5 py-3 rounded-full transition-all shadow btn-bouncy" href="#kompetisi">
                      Lihat Detail Lomba
                    </a>
    <a class="bg-white/10 hover:bg-white/20 border border-white/30 text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-full transition-all btn-bouncy" href="https://bit.ly/CodingCROC2026" rel="noopener" target="_blank">
                      Daftar Coding
                    </a>
    <a class="bg-white/10 hover:bg-white/20 border border-white/30 text-white font-semibold text-xs sm:text-sm px-5 py-3 rounded-full transition-all btn-bouncy" href="https://bit.ly/PendaftaranCROC2026" rel="noopener" target="_blank">
                      Daftar Robotik
                    </a>
    </div>
    </div>
    </div>
    </div>
    </div>
    </section>
    <!-- END: CompetitionSection -->
    <!-- BEGIN: AboutSection -->
    <section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl sm:text-4xl font-extrabold text-brandNavy">Hi, Kami AfterSchola !</h2>
    <p class="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed max-w-2xl mx-auto">
              Kami hadir untuk membantu anak mengembangkan keterampilan dan bakat mereka melalui kegiatan seru dan bermanfaat. Dari public speaking hingga coding, kami menawarkan program-program yang dirancang khusus untuk meningkatkan kepercayaan diri, kreativitas, dan keterampilan anak Anda.
            </p>
    <!-- Visual 3 Badges with Illustration style -->
    <div class="mt-12 grid grid-cols-1 sm:grid-cols-3 gap-8">
    <!-- Student item -->
    <div class="flex flex-col items-center p-4 group cursor-pointer">
    <div class="w-24 h-24 rounded-full bg-amber-100/70 border-2 border-brandSun flex items-center justify-center text-brandNavy mb-3 shadow-inner partner-badge animate-idle-bounce">
    <span class="material-symbols-outlined text-5xl text-brandNavy transition-transform group-hover:scale-110">face</span>
    </div>
    <p class="text-2xl font-black text-brandNavy group-hover:text-brandSunDark transition-colors">1,000 +</p>
    <p class="text-xs font-semibold text-slate-600 mt-1">Siswa Aktif Terdaftar</p>
    </div>
    <!-- School partner item -->
    <div class="flex flex-col items-center p-4 group cursor-pointer">
    <div class="w-24 h-24 rounded-full bg-amber-100/70 border-2 border-brandSun flex items-center justify-center text-brandNavy mb-3 shadow-inner partner-badge animate-idle-bounce-delay-1">
    <span class="material-symbols-outlined text-5xl text-brandNavy transition-transform group-hover:scale-110">handshake</span>
    </div>
    <p class="text-2xl font-black text-brandNavy group-hover:text-brandSunDark transition-colors">20 +</p>
    <p class="text-xs font-semibold text-slate-600 mt-1">Mitra Sekolah</p>
    </div>
    <!-- Location item -->
    <div class="flex flex-col items-center p-4 group cursor-pointer">
    <div class="w-24 h-24 rounded-full bg-amber-100/70 border-2 border-brandSun flex items-center justify-center text-brandNavy mb-3 shadow-inner partner-badge animate-idle-bounce-delay-2">
    <span class="material-symbols-outlined text-5xl text-brandNavy transition-transform group-hover:scale-110">location_on</span>
    </div>
    <p class="text-2xl font-black text-brandNavy group-hover:text-brandSunDark transition-colors">5 +</p>
    <p class="text-xs font-semibold text-slate-600 mt-1">Lokasi</p>
    </div>
    </div>
    </div>
    </section>
    <!-- END: AboutSection -->
    <!-- BEGIN: PartnerSchoolsSection -->
    <section class="py-16 bg-gradient-to-r from-brandSun via-amber-400 to-brandSun text-brandNavy shadow-inner">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <h2 class="text-3xl sm:text-4xl font-extrabold text-brandDeepNavy">Mitra Sekolah Kami</h2>
    <p class="mt-3 text-sm sm:text-base font-medium max-w-3xl mx-auto text-brandDeepNavy/90 leading-relaxed">
              Di After Schola, kami bangga dapat bekerja sama dengan berbagai sekolah yang memiliki visi yang sama dalam mengembangkan potensi anak-anak. Bersama mitra sekolah, kami membantu anak-anak menemukan minat dan bakatnya melalui program-program inovatif dan menyenangkan.
            </p>
    <!-- Partner Badges Grid with Continuous Gentle Staggered Bobbing Animation -->
    <div class="mt-10 grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-4 sm:gap-6 items-center justify-items-center">
    <div
    v-for="(p, i) in partners"
    :key="i"
    class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white shadow-md flex items-center justify-center p-2 partner-badge cursor-pointer"
    :class="p.bob"
    >
    <span class="material-symbols-outlined text-3xl" :class="p.color">{{ p.icon }}</span>
    </div>
    </div>
    </div>
    </section>
    <!-- END: PartnerSchoolsSection -->
    <!-- BEGIN: WhyChooseUsSection -->
    <section class="py-20 bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-10">
    <p class="text-xs font-extrabold uppercase tracking-widest text-brandTeal">ALASAN TEPAT UNTUK PENDIDIKAN BERKUALITAS</p>
    <h2 class="text-3xl sm:text-4xl font-extrabold text-brandNavy mt-2">Mengapa Memilih AfterSchola?</h2>
    <p class="text-slate-600 text-sm sm:text-base mt-2">
    <strong class="text-brandNavy">Program Beragam dan Berkualitas</strong> AfterSchola menawarkan berbagai kursus yang dirancang untuk mengembangkan keterampilan siswa di berbagai bidang.
              </p>
    </div>
    <!-- Accordion Items -->
    <div class="space-y-3" id="why-accordion">
    <!-- Accordion 1 (Open by default) -->
    <details class="group rounded-2xl overflow-hidden border border-slate-200 shadow-sm transition-all duration-300" open="">
    <summary class="flex items-center justify-between px-6 py-4 bg-brandAccordion hover:bg-brandAccordion/90 text-white cursor-pointer select-none font-bold text-sm sm:text-base">
    <span>1. Program Beragam dan Berkualitas</span>
    <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
    </summary>
    <div class="p-6 bg-slate-50 text-slate-700 text-sm leading-relaxed border-t border-slate-200">
    <ul class="space-y-2 list-disc list-inside">
    <li><strong class="text-brandNavy">Coding:</strong> Belajar dasar-dasar pemrograman hingga menciptakan aplikasi dan game interaktif.</li>
    <li><strong class="text-brandNavy">Bahasa Inggris:</strong> Mengasah kemampuan berbahasa Inggris melalui metode belajar yang menyenangkan.</li>
    <li><strong class="text-brandNavy">Robotik:</strong> Mengeksplorasi teknologi robotika untuk mempersiapkan siswa dalam era digital.</li>
    <li><strong class="text-brandNavy">Panahan:</strong> Melatih konsentrasi dan disiplin melalui olahraga tradisional yang menantang.</li>
    <li><strong class="text-brandNavy">Tahfiz:</strong> Membantu siswa mendalami dan menghafal Al-Quran dengan metode yang efektif.</li>
    <li><strong class="text-brandNavy">Public Speaking &amp; Podcast:</strong> Mengasah kemampuan berbicara di depan umum dan menyampaikan ide kreatif melalui media digital.</li>
    <li><strong class="text-brandNavy">Creativor:</strong> Mengembangkan kreativitas dan inovasi siswa melalui berbagai proyek menarik.</li>
    </ul>
    </div>
    </details>
    <!-- Accordion 2 -->
    <details class="group rounded-2xl overflow-hidden border border-slate-200 shadow-sm transition-all duration-300">
    <summary class="flex items-center justify-between px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white cursor-pointer select-none font-bold text-sm sm:text-base">
    <span>2. Dukungan untuk Program Indonesia Emas 2045</span>
    <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
    </summary>
    <div class="p-6 bg-slate-50 text-slate-700 text-sm leading-relaxed border-t border-slate-200">
    <p>AfterSchola mendukung visi besar Indonesia Emas 2045 dengan membantu siswa membangun keterampilan abad ke-21, seperti berpikir kritis, kreativitas, dan kolaborasi.</p>
    </div>
    </details>
    <!-- Accordion 3 -->
    <details class="group rounded-2xl overflow-hidden border border-slate-200 shadow-sm transition-all duration-300">
    <summary class="flex items-center justify-between px-6 py-4 bg-brandAccordion hover:bg-brandAccordion/90 text-white cursor-pointer select-none font-bold text-sm sm:text-base">
    <span>3. Pengajar Profesional dan Berpengalaman</span>
    <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
    </summary>
    <div class="p-6 bg-slate-50 text-slate-700 text-sm leading-relaxed border-t border-slate-200">
    <p>Tim pengajar kami adalah para profesional yang berdedikasi untuk memberikan pengalaman belajar terbaik bagi siswa dengan pendekatan yang ramah dan interaktif.</p>
    </div>
    </details>
    <!-- Accordion 4 -->
    <details class="group rounded-2xl overflow-hidden border border-slate-200 shadow-sm transition-all duration-300">
    <summary class="flex items-center justify-between px-6 py-4 bg-brandAccordion hover:bg-brandAccordion/90 text-white cursor-pointer select-none font-bold text-sm sm:text-base">
    <span>4. Metode Pembelajaran Inovatif</span>
    <span class="material-symbols-outlined transition-transform duration-300 group-open:rotate-180">expand_more</span>
    </summary>
    <div class="p-6 bg-slate-50 text-slate-700 text-sm leading-relaxed border-t border-slate-200">
    <p>Kami menggunakan metode pembelajaran berbasis otak (Brain-Based Learning) yang sudah terbukti efektif membantu siswa memahami materi dengan lebih mudah dan menyenangkan.</p>
    </div>
    </details>
    </div>
    </div>
    </section>
    <!-- END: WhyChooseUsSection -->
    <!-- BEGIN: ProgramsSection -->
    <section class="py-20 bg-gradient-to-b from-brandSun to-amber-500 text-slate-900 relative overflow-hidden" id="program">
    <!-- Fun background doodle elements -->
    <div aria-hidden="true" class="absolute inset-0 pointer-events-none select-none">
    <span class="material-symbols-outlined absolute top-8 left-8 text-white/20 text-4xl animate-twinkle">auto_awesome</span>
    <span class="material-symbols-outlined absolute bottom-8 right-8 text-brandDeepNavy/10 text-5xl animate-twinkle-delay">star</span>
    <span class="material-symbols-outlined absolute top-1/2 right-4 text-white/20 text-3xl animate-floating-slow">bubble_chart</span>
    </div>
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
    <div class="text-center mb-12">
    <p class="text-xs font-extrabold uppercase tracking-widest text-brandDeepNavy">PILIHAN PROGRAM</p>
    <h2 class="text-3xl sm:text-4xl font-extrabold text-brandDeepNavy mt-1">Program Kursus AfterSchola</h2>
    <p class="text-brandDeepNavy font-medium text-sm sm:text-base max-w-2xl mx-auto mt-2">
                AfterSchola menawarkan berbagai kursus yang dirancang untuk mengembangkan keterampilan siswa di berbagai bidang, seperti:
              </p>
    </div>
    <!-- Kartu program (data di <script setup>) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <div
    v-for="p in programs"
    :key="p.title"
    class="bg-white rounded-3xl p-6 shadow-md program-card-hover flex flex-col text-center items-center cursor-pointer relative overflow-hidden"
    >
    <div
    class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4 program-icon"
    :class="[p.tone, p.wiggle]"
    >
    <span class="material-symbols-outlined text-3xl">{{ p.icon }}</span>
    </div>
    <h3 class="text-lg font-bold text-brandNavy">{{ p.title }}</h3>
    <p class="text-slate-600 text-xs sm:text-sm mt-2 leading-relaxed">{{ p.desc }}</p>
    </div>
    </div>
    </div>
    </section>
    <!-- END: ProgramsSection -->
    <!-- BEGIN: LearningLocationsSection -->
    <section class="py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid lg:grid-cols-12 gap-12 items-center">
    <!-- Text Explanation Column -->
    <div class="lg:col-span-6 space-y-6">
    <div>
    <p class="text-xs font-extrabold uppercase tracking-widest text-brandTeal">TEMPAT</p>
    <h2 class="text-3xl sm:text-4xl font-extrabold text-brandNavy mt-1">
                    Dimana Pembelajaran Dilakukan
                  </h2>
    </div>
    <p class="text-slate-600 text-sm sm:text-base leading-relaxed">
                  AfterSchola menyediakan kelas online dan offline untuk menjangkau lebih banyak siswa di berbagai lokasi. Dengan pendekatan fleksibel ini, kami memastikan setiap siswa dapat belajar sesuai kebutuhan mereka, baik dari rumah maupun langsung di lokasi kami.
                </p>
    <div class="space-y-4 pt-2">
    <!-- Offline Class Item -->
    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200/80 hover:border-brandTeal/50 hover:bg-slate-50/80 transition-all">
    <div class="flex items-center gap-2 font-bold text-brandNavy text-base">
    <span class="material-symbols-outlined text-brandTeal">check_circle</span>
    <span>Offline Kelas</span>
    </div>
    <p class="text-slate-600 text-xs sm:text-sm mt-1.5 leading-relaxed">
                      Belajar langsung di lokasi kami dengan fasilitas lengkap dan bimbingan tatap muka dari pengajar berpengalaman.
                    </p>
    </div>
    <!-- Online Class Item -->
    <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200/80 hover:border-brandTeal/50 hover:bg-slate-50/80 transition-all">
    <div class="flex items-center gap-2 font-bold text-brandNavy text-base">
    <span class="material-symbols-outlined text-brandTeal">laptop_chromebook</span>
    <span>Online Kelas</span>
    </div>
    <p class="text-slate-600 text-xs sm:text-sm mt-1.5 leading-relaxed">
                      Mengikuti pembelajaran jarak jauh melalui platform digital dengan akses mudah dari rumah.
                    </p>
    </div>
    </div>
    </div>
    <!-- Photo Collage Grid (Students studying) -->
    <div class="lg:col-span-6 grid grid-cols-2 gap-4">
    <div class="space-y-4">
    <!-- Top Left Image Placeholder -->
    <div class="overflow-hidden rounded-2xl shadow-md group">
    <img alt="Anak-anak sedang belajar bersama globe" class="w-full h-48 sm:h-56 object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCB2ashPmopwUv9iG-WzhD82_jX_zYSB1AHQCZTrG6UjfyRinwAS8QVrGt-slbYE0o2NGFl4QD5Yj8q9z4eBM9EcFPqZyQXhxFxR-Jo_QkezNVnPPhokN84XP3KXxlKhmY4MuDpZoxeUPilkXNj81NQGD1RDLEuZbYBzj2D7aGoAR7K9IZ9zETW19iQufvWsRrs5IdtACSu-8Dl5tgDy9KWhGh6mMcQdwhFeAIhBNSB"/>
    </div>
    <!-- Bottom Left Image Placeholder -->
    <div class="overflow-hidden rounded-2xl shadow-md group">
    <img alt="Bimbingan tatap muka pengajar profesional" class="w-full h-48 sm:h-56 object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCxr7qwF1iSU7syEa8httUj1sa8hJWySjiy1iZ1nbeyk9OYakd8bD6gcVR6Y_iCvbuHRYyxKuthaVDI75Vryd2WgVbxprrSPNnczg6WKrpHiA8LfewLDodmHFBNjxHAauVBmvI6zYbkm_g_ujIvH5JHYYfA9PXOoymFnH4VupBogSkGBMI65fzdx8w_M3OjPy4nd2Gbe9EV4bxcqq3GjJOiGkcbCqLyJmaJXkBiv3Da"/>
    </div>
    </div>
    <div class="space-y-4 pt-6">
    <!-- Right Tall Photo Placeholder -->
    <div class="overflow-hidden rounded-2xl shadow-md group">
    <img alt="Aktivitas siswa di kelas robotik dan coding" class="w-full h-80 sm:h-96 object-cover group-hover:scale-105 transition-transform duration-500" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCLvSRZgCFjWDqtjoUP6A5wGbnu951QG0CiNLNLg7AiZ11yChwbY0ioUVA3SXr7MhAeMl3KBLZtww8zHKJmmO5kFKRU9QHTyT66NlJO2mGGtvs8pH2ONTl_BQ69K1JGDfAXjNVH3pqi7o-guRcRMcuSz88IZQ6ysh9t5JmuW3nv6ac5X6D2SXUt9e_8E5XCEFn63_Qkc1WzPuVRLTouVD6Ti8VCVrWwUWJOSkz_s3L0"/>
    </div>
    </div>
    </div>
    </div>
    </div>
    </section>
    <!-- END: LearningLocationsSection -->
    <!-- BEGIN: CallToActionBanner -->
    <section class="py-16 px-4 bg-white">
    <div class="max-w-5xl mx-auto rounded-3xl bg-gradient-to-r from-[#205295] via-[#103b70] to-[#0a2647] p-8 sm:p-14 text-center text-white shadow-2xl relative overflow-hidden">
    <!-- Subtle moon and stars graphic simulation with ambient looping sparkles -->
    <div class="absolute -top-10 -left-10 w-40 h-40 bg-white/5 rounded-full blur-2xl pointer-events-none"></div>
    <div class="absolute top-6 left-12 text-amber-300 animate-twinkle-pulse select-none">
    <span class="material-symbols-outlined text-4xl">star</span>
    </div>
    <div class="absolute bottom-6 right-16 text-amber-300 animate-twinkle-pulse-delay select-none">
    <span class="material-symbols-outlined text-3xl">auto_awesome</span>
    </div>
    <div class="absolute top-10 right-1/4 text-cyan-200/50 animate-twinkle-delay select-none">
    <span class="material-symbols-outlined text-2xl">hotel_class</span>
    </div>
    <div class="absolute bottom-10 left-1/4 text-amber-200/50 animate-twinkle select-none">
    <span class="material-symbols-outlined text-xl">sparkles</span>
    </div>
    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight relative z-10">
              Siap Bertumbuh Bersama <br/>
    <span class="text-white">AfterSchola?</span>
    </h2>
    <div class="mt-8 relative z-10">
    <RouterLink :to="{ name: 'public.trial-registration' }" class="inline-flex items-center gap-2 bg-brandSun hover:bg-brandSunDark text-brandDeepNavy font-extrabold text-base sm:text-lg px-9 py-3.5 rounded-full shadow-xl shadow-brandSun/30 btn-bouncy animate-cta-glow">
    <span>Daftar Sekarang</span>
    </RouterLink>
    </div>
    </div>
    </section>
    <!-- END: CallToActionBanner -->
    <!-- BEGIN: FAQSection -->
    <section id="faq" class="py-20 bg-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
    <h2 class="text-3xl sm:text-4xl font-extrabold text-brandNavy">
                Pertanyaan <br class="sm:hidden"/>
                Sering Ditanyakan
              </h2>
    </div>
    <!-- FAQ List with Native Semantic Details Tag -->
    <div class="space-y-3">
    <details
    v-for="(f, i) in faqs"
    :key="f.q"
    :open="i === 0"
    class="group bg-white rounded-2xl p-5 border border-slate-200 shadow-sm transition-all duration-300"
    >
    <summary class="flex justify-between items-center font-bold text-brandNavy cursor-pointer text-sm sm:text-base select-none">
    <span>{{ f.q }}</span>
    <span class="material-symbols-outlined text-slate-400 group-open:rotate-180 transition-transform duration-300">expand_more</span>
    </summary>
    <p class="mt-3 text-slate-600 text-xs sm:text-sm leading-relaxed border-t border-slate-100 pt-3">{{ f.a }}</p>
    </details>
    </div>
    </div>
    </section>
    <!-- END: FAQSection -->
    </main>
    <!-- BEGIN: SiteFooter -->
    <footer class="bg-brandDeepNavy text-white pt-16 pb-12 border-t border-white/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-10">
    <!-- Office Center info (takes 2 cols on lg) -->
    <div class="lg:col-span-2 space-y-4">
    <a aria-label="AfterSchola Home" class="inline-block transition-transform hover:scale-105" href="#">
    <img alt="Logo AfterSchola" class="h-10 sm:h-12 w-auto object-contain brightness-0 invert" src="https://lh3.googleusercontent.com/aida/AEtjO1UxSKBkKJ1MNN3I94yhMlMnI9BgdsPcuGS0AtwRuNwc4u7YXZhZQpG4igiFjdD6IxOn2hSTJIXT8mkb2QED3JFuYn_3gVwKRR-7qsCGvnTISlbfMlRu94DzEqGy_Xq9HN3bYQS13dBLaVXCYdjUZHj8xICg-wwHhCiMhi4zU-Xl1K8XbAcI6PwdTuHzgnCs9zoAAEDn9tl8xYwhbzSTaCAHF3gYP2NhzYNof4-9Y0cXlA"/>
    </a>
    <div class="pt-2">
    <h4 class="text-base font-bold text-brandSun">Office Center</h4>
    <p class="text-xs sm:text-sm text-slate-300 mt-2 leading-relaxed max-w-sm">
                  Jl. Cisaranten Wetan No.167 A, Cisaranten Wetan, Kec. Cinambo, Kota Bandung, Jawa Barat 40294
                </p>
    </div>
    </div>
    <!-- Col 1: Layanan -->
    <div>
    <h4 class="text-sm font-bold uppercase tracking-wider text-brandSun mb-4">Layanan</h4>
    <ul class="space-y-2 text-xs sm:text-sm text-slate-300">
    <li><a class="hover:text-white transition-colors" href="https://wa.me/6282338089915?text=Hallo%20After%20Schola%2C%20Minta%20info%20lebih%20lanjut%20untuk%20program%20after%20schola">Kelas Gratis</a></li>
    <li><a class="hover:text-white transition-colors" href="https://bit.ly/KarirAfterSchola2025" rel="noopener" target="_blank">Karir</a></li>
    <li><a class="hover:text-white transition-colors" href="https://bit.ly/MagangAfterSchola2025" rel="noopener" target="_blank">Magang/PKL</a></li>
    </ul>
    </div>
    <!-- Col 2: Lokasi Kami -->
    <div>
    <h4 class="text-sm font-bold uppercase tracking-wider text-brandSun mb-4">Lokasi Kami</h4>
    <ul class="space-y-2 text-xs sm:text-sm text-slate-300">
    <li><a class="hover:text-white transition-colors" href="https://maps.app.goo.gl/LAMs3LomhroY9Gh17" rel="noopener" target="_blank">Jatinangor</a></li>
    <li><a class="hover:text-white transition-colors" href="https://maps.app.goo.gl/RgbDRMkydvaCYJsQ8" rel="noopener" target="_blank">Cisaranten Wetan</a></li>
    <li><a class="hover:text-white transition-colors" href="https://maps.app.goo.gl/mAeYz3zYGavUuirTA" rel="noopener" target="_blank">Ranggamalela</a></li>
    </ul>
    </div>
    <!-- Col 3: Pusat Bantuan -->
    <div>
    <h4 class="text-sm font-bold uppercase tracking-wider text-brandSun mb-4">Pusat Bantuan</h4>
    <ul class="space-y-2 text-xs sm:text-sm text-slate-300">
    <li><a class="hover:text-white transition-colors" href="#">Tentang Kami</a></li>
    <li><a class="hover:text-white transition-colors" href="#">Kebijakan Privasi</a></li>
    <li><a class="hover:text-white transition-colors" href="#">Syarat Penggunaan</a></li>
    <li><a class="hover:text-white transition-colors" href="#faq">FAQ</a></li>
    <li><a class="hover:text-white transition-colors" href="https://wa.me/6282338089915?text=Hallo%20After%20Schola%2C%20Minta%20info%20lebih%20lanjut%20untuk%20program%20after%20schola">Hubungi Kami</a></li>
    </ul>
    </div>
    </div>
    <!-- Footer Bottom Copyright & Socials -->
    <div class="mt-12 pt-6 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-400 gap-4">
    <p>Copyright © 2026 AfterSchola. All Rights Reserved.</p>
    <div class="flex items-center space-x-4">
    <a aria-label="Facebook" class="hover:text-white transition-transform hover:scale-125" href="https://web.facebook.com/afterschola" rel="noopener" target="_blank">
    <span class="material-symbols-outlined text-lg">public</span>
    </a>
    <a aria-label="Instagram" class="hover:text-white transition-transform hover:scale-125" href="https://www.instagram.com/afterschola/" rel="noopener" target="_blank">
    <span class="material-symbols-outlined text-lg">photo_camera</span>
    </a>
    <a aria-label="YouTube" class="hover:text-white transition-transform hover:scale-125" href="https://www.youtube.com/@AfterSchola" rel="noopener" target="_blank">
    <span class="material-symbols-outlined text-lg">smart_display</span>
    </a>
    <a aria-label="TikTok" class="hover:text-white transition-transform hover:scale-125" href="https://www.tiktok.com/@after_schola" rel="noopener" target="_blank">
    <span class="material-symbols-outlined text-lg">music_note</span>
    </a>
    </div>
    </div>
    </div>
    </footer>
    <!-- END: SiteFooter -->
    <!-- BEGIN: FloatingWhatsAppButton -->
    <div class="fixed bottom-6 right-6 z-50 flex items-center group">
    <div class="hidden sm:block mr-3 bg-white text-slate-800 text-xs font-semibold py-2 px-3 rounded-xl shadow-lg border border-slate-100 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
          Ada yang ingin ditanyakan? 👋
        </div>
    <a aria-label="Hubungi kami melalui WhatsApp" class="w-14 h-14 bg-[#25d366] hover:bg-[#20ba59] text-white rounded-full flex items-center justify-center shadow-2xl animate-bounce-gentle btn-bouncy" href="https://wa.me/6282338089915?text=Halo%20AfterSchola,%20saya%20ingin%20bertanya." rel="noopener" target="_blank">
    <span class="material-symbols-outlined text-3xl">chat</span>
    </a>
    </div>
    <!-- END: FloatingWhatsAppButton -->
  </div>
</template>

<style scoped>
    .hexagon {
      clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
    }
    details > summary::-webkit-details-marker {
      display: none;
    }
    .custom-scroll::-webkit-scrollbar {
      height: 6px;
    }
    .custom-scroll::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 9999px;
    }

    /* === Playful & Fun Ambient Loop Animations === */
    @keyframes float-playful {
      0%, 100% {
        transform: translateY(0px) rotate(0deg);
      }
      50% {
        transform: translateY(-8px) rotate(1deg);
      }
    }

    @keyframes float-reverse {
      0%, 100% {
        transform: translateY(0px) rotate(0deg);
      }
      50% {
        transform: translateY(7px) rotate(-1.5deg);
      }
    }

    @keyframes float-wave-1 {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-7px) rotate(-2deg); }
    }

    @keyframes float-wave-2 {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(-9px) rotate(2.5deg); }
    }

    @keyframes float-wave-3 {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(6px) rotate(-1.5deg); }
    }

    @keyframes float-wave-4 {
      0%, 100% { transform: translateY(0px) rotate(0deg); }
      50% { transform: translateY(8px) rotate(2deg); }
    }

    @keyframes float-center {
      0%, 100% { transform: scale(1) translateY(0px); }
      50% { transform: scale(1.04) translateY(-4px); }
    }

    @keyframes gentle-pulse {
      0%, 100% { transform: scale(1); opacity: 0.85; }
      50% { transform: scale(1.15); opacity: 1; }
    }

    @keyframes twinkle {
      0%, 100% { opacity: 0.25; transform: scale(0.85) rotate(0deg); }
      50% { opacity: 1; transform: scale(1.2) rotate(15deg); }
    }

    @keyframes twinkle-pulse {
      0%, 100% { opacity: 0.3; transform: scale(0.8) rotate(0deg); filter: drop-shadow(0 0 2px rgba(248,178,27,0.3)); }
      50% { opacity: 1; transform: scale(1.25) rotate(20deg); filter: drop-shadow(0 0 10px rgba(248,178,27,0.8)); }
    }

    @keyframes floating-slow {
      0%, 100% { transform: translateY(0) rotate(0deg); }
      50% { transform: translateY(-12px) rotate(3deg); }
    }

    @keyframes bubble-drift {
      0%, 100% { transform: translateY(0) translateX(0) scale(1); }
      33% { transform: translateY(-10px) translateX(6px) scale(1.05); }
      66% { transform: translateY(-5px) translateX(-6px) scale(0.96); }
    }

    @keyframes text-glow-pulse {
      0%, 100% {
        text-shadow: 0 0 10px rgba(248, 178, 27, 0.4), 0 0 20px rgba(248, 178, 27, 0.2);
        filter: brightness(1);
      }
      50% {
        text-shadow: 0 0 20px rgba(248, 178, 27, 0.85), 0 0 35px rgba(248, 178, 27, 0.5);
        filter: brightness(1.12);
      }
    }

    @keyframes badge-breathe {
      0%, 100% {
        box-shadow: 0 0 0 0 rgba(248, 178, 27, 0);
        border-color: rgba(255, 255, 255, 0.18);
      }
      50% {
        box-shadow: 0 0 16px 2px rgba(248, 178, 27, 0.35);
        border-color: rgba(248, 178, 27, 0.6);
      }
    }

    @keyframes badge-breathe-light {
      0%, 100% {
        box-shadow: 0 4px 15px -3px rgba(16, 185, 129, 0.2);
        transform: translateY(0);
      }
      50% {
        box-shadow: 0 6px 20px 2px rgba(16, 185, 129, 0.45);
        transform: translateY(-3px);
      }
    }

    @keyframes cta-button-glow {
      0%, 100% {
        box-shadow: 0 0 0 0 rgba(248, 178, 27, 0.4), 0 10px 25px -5px rgba(248, 178, 27, 0.4);
        transform: scale(1);
      }
      50% {
        box-shadow: 0 0 24px 6px rgba(248, 178, 27, 0.6), 0 15px 30px -5px rgba(248, 178, 27, 0.55);
        transform: scale(1.03);
      }
    }

    @keyframes timer-glow {
      0%, 100% {
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: inset 0 0 0 0 rgba(248, 178, 27, 0);
      }
      50% {
        border-color: rgba(248, 178, 27, 0.45);
        box-shadow: inset 0 0 12px 1px rgba(248, 178, 27, 0.15), 0 0 10px rgba(248, 178, 27, 0.2);
      }
    }

    @keyframes idle-wiggle {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(-6deg) scale(1.04); }
      75% { transform: rotate(6deg) scale(1.04); }
    }

    @keyframes idle-bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }

    @keyframes partner-bob-1 {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }
    @keyframes partner-bob-2 {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-7px); }
    }
    @keyframes partner-bob-3 {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }

    @keyframes icon-wiggle {
      0%, 100% { transform: rotate(0deg); }
      25% { transform: rotate(-12deg) scale(1.12); }
      75% { transform: rotate(12deg) scale(1.12); }
    }

    @keyframes bounce-fun {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-7px); }
    }

    /* Animation utility classes */
    .animate-float-playful {
      animation: float-playful 5s ease-in-out infinite;
    }
    .animate-float-reverse {
      animation: float-reverse 6s ease-in-out infinite;
    }
    .animate-float-wave-1 {
      animation: float-wave-1 4.5s ease-in-out infinite;
    }
    .animate-float-wave-2 {
      animation: float-wave-2 5s ease-in-out 0.8s infinite;
    }
    .animate-float-wave-3 {
      animation: float-wave-3 4.2s ease-in-out 1.5s infinite;
    }
    .animate-float-wave-4 {
      animation: float-wave-4 5.2s ease-in-out 2.2s infinite;
    }
    .animate-float-center {
      animation: float-center 4s ease-in-out infinite;
    }
    .animate-twinkle {
      animation: twinkle 3.5s ease-in-out infinite;
    }
    .animate-twinkle-delay {
      animation: twinkle 4s ease-in-out 1.8s infinite;
    }
    .animate-twinkle-pulse {
      animation: twinkle-pulse 3s ease-in-out infinite;
    }
    .animate-twinkle-pulse-delay {
      animation: twinkle-pulse 3.5s ease-in-out 1.4s infinite;
    }
    .animate-floating-slow {
      animation: floating-slow 5s ease-in-out infinite;
    }
    .animate-floating-slow-delay {
      animation: floating-slow 5.5s ease-in-out 2.5s infinite;
    }
    .animate-bubble-drift {
      animation: bubble-drift 6s ease-in-out infinite;
    }
    .animate-bubble-drift-delay {
      animation: bubble-drift 7s ease-in-out 2s infinite;
    }
    .animate-text-shimmer {
      animation: text-glow-pulse 3.2s ease-in-out infinite;
    }
    .animate-badge-breathe {
      animation: badge-breathe 3.5s ease-in-out infinite;
    }
    .animate-badge-breathe-light {
      animation: badge-breathe-light 3s ease-in-out infinite;
    }
    .animate-cta-glow {
      animation: cta-button-glow 3s ease-in-out infinite;
    }
    .animate-timer-glow {
      animation: timer-glow 3s ease-in-out infinite;
    }
    .animate-timer-glow-delay-1 {
      animation: timer-glow 3s ease-in-out 0.3s infinite;
    }
    .animate-timer-glow-delay-2 {
      animation: timer-glow 3s ease-in-out 0.6s infinite;
    }
    .animate-timer-glow-delay-3 {
      animation: timer-glow 3s ease-in-out 0.9s infinite;
    }

    .animate-bounce-gentle {
      animation: bounce-fun 2.4s ease-in-out infinite;
    }

    /* Continuous idle micro-animations for program cards & partner badges */
    .animate-idle-wiggle {
      animation: idle-wiggle 4s ease-in-out infinite;
    }
    .animate-idle-wiggle-delay-1 {
      animation: idle-wiggle 4.2s ease-in-out 0.7s infinite;
    }
    .animate-idle-wiggle-delay-2 {
      animation: idle-wiggle 3.8s ease-in-out 1.4s infinite;
    }
    .animate-idle-bounce {
      animation: idle-bounce 3.2s ease-in-out infinite;
    }
    .animate-idle-bounce-delay-1 {
      animation: idle-bounce 3.5s ease-in-out 0.8s infinite;
    }
    .animate-idle-bounce-delay-2 {
      animation: idle-bounce 3.3s ease-in-out 1.6s infinite;
    }

    .partner-bob-odd {
      animation: partner-bob-1 3.5s ease-in-out infinite;
    }
    .partner-bob-even {
      animation: partner-bob-2 4s ease-in-out 0.6s infinite;
    }
    .partner-bob-tri {
      animation: partner-bob-3 3.8s ease-in-out 1.2s infinite;
    }

    /* Bouncy jelly pop-up for CTAs */
    .btn-bouncy {
      transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .btn-bouncy:hover {
      transform: scale(1.06) translateY(-2px);
    }
    .btn-bouncy:active {
      transform: scale(0.96) translateY(1px);
    }

    /* Program card hover effect */
    .program-card-hover {
      transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .program-card-hover:hover {
      transform: translateY(-8px) rotate(-1deg) scale(1.02);
      box-shadow: 0 20px 25px -5px rgba(248, 178, 27, 0.25), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }
    .program-card-hover:nth-child(even):hover {
      transform: translateY(-8px) rotate(1deg) scale(1.02);
    }
    .program-card-hover:hover .program-icon {
      animation: icon-wiggle 0.7s ease-in-out;
    }

    /* School partner badges hover */
    .partner-badge {
      transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .partner-badge:hover {
      transform: translateY(-6px) scale(1.15) rotate(4deg);
      box-shadow: 0 10px 20px -3px rgba(4, 23, 53, 0.2);
    }

    /* Smooth accordion expandable transitions */
    details summary {
      transition: background-color 0.25s ease;
    }
    details[open] summary ~ * {
      animation: accordion-slide-down 0.35s ease-out;
    }
    @keyframes accordion-slide-down {
      from {
        opacity: 0;
        transform: translateY(-8px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Reduced motion accessibility */
    @media (prefers-reduced-motion: reduce) {
      *, ::before, ::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
      }
    }
  
</style>
