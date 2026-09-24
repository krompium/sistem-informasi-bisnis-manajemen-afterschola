# Catatan Developer — Project After Schola Portal

## 1. Sebelum Mulai Coding
- Pull branch terbaru dari `main`, jangan basis dari branch lama
- Setup lokal ikuti README existing:
  - Backend: `composer install` → `php artisan migrate --seed` → `php artisan serve`
  - Frontend: `npm install` → `npm run dev`
- Kredensial demo sudah ada di seeder:
  - Management: `admin@afterschola.id` / `password`
  - Trainer: `trainer@afterschola.id` / `password`

## 2. Aturan Git (karena 7 orang di 1 repo)
- Satu branch per modul, format: `feature/modul-<nomor>-<nama-singkat>`
  contoh: `feature/modul-2-pendaftaran-leads`
- Commit kecil & spesifik, jangan gabung beberapa fitur dalam satu PR
- PR wajib direview minimal 1 orang lain sebelum merge ke `main`
- Dilarang push langsung ke `main`

## 3. Batas Modul — Jangan Sentuh di Luar Bagian Sendiri

| Dev | Modul |
|---|---|
| Dev 1 | Manajemen User (lanjutan Fondasi) |
| Dev 2 | Program & Promosi |
| Dev 3 | Pendaftaran Trial & Leads |
| Dev 4 | Evaluasi Trial + Keputusan & Pendaftaran Resmi |
| Dev 5 | Verifikasi & Pembayaran Pendaftaran |
| Dev 6 | Evaluasi Perkembangan + Laporan |
| Dev 7 | HR + Manajemen Proyek |

Kalau butuh field/tabel dari modul lain, koordinasi dulu — jangan ubah migration punya modul orang lain sendirian.

## 4. ⚠️ Jangan Dikerjakan Dulu
- **Modul Absensi & Jadwal** — sudah ada di repo, tapi statusnya masih ditahan.
  Belum diputuskan dipakai sendiri atau diganti API dari tim Salman. Jangan
  tambah fitur di modul ini sampai ada kepastian.
- **Integrasi API Keuangan (tim Salman)** — menunggu API key & dokumentasi
  resmi dari mereka.

## 5. Konvensi Kode
- **Backend**: ikuti pola existing — route di `routes/api.php`, response
  pakai API Resource, validasi pakai FormRequest
- **Role & akses**: pakai middleware/policy dari `spatie/laravel-permission`
  yang sudah ada, jangan bikin sistem role sendiri
- **Frontend**: Vue 3 `<script setup>`, ikuti struktur folder yang sudah ada
  di `frontend/src/views/`
- **Migration**: nama jelas per fitur (`create_leads_table`, bukan
  `create_table1`), snake_case konsisten

## 6. Titik Rawan — Data Siswa
Tabel `students` sudah dipakai modul Absensi. Sebelum Dev 4/Dev 5 bikin
tabel siswa baru untuk alur pendaftaran, **koordinasi dulu ke PM** — apakah
pakai tabel yang sama atau relasi terpisah. Ini keputusan arsitektur, bukan
keputusan masing-masing dev.

## 7. Komunikasi
- Kalau ada blocker (nunggu keputusan, nunggu API, dsb), langsung lapor ke
  PM — jangan ditahan sampai standup
- Update status tiap modul minimal tiap hari kerja
