# After Schola — Frontend (Vue 3 + Vite)

SPA **Portal Operasional** (Trainer & Management) untuk platform After Schola.
Murni UI; seluruh validasi & otorisasi ada di backend (Laravel API + Sanctum +
spatie/permission), sesuai keputusan **API-first** di PRD agar endpoint yang sama
dapat dipakai ulang oleh mobile app.

> Desain visual mengikuti design system **"After Schola Design System" (Remix)**
> dari Stitch: shell desktop sidebar navy `#0B2A5B` + royal blue `#1D4ED8`,
> heading Plus Jakarta Sans, body Inter. Menu sidebar & konten menyesuaikan peran
> (Trainer / Management). Stitch dipakai sebagai referensi visual; implementasi
> mengikuti dokumentasi proyek.

## Stack

- Vue 3 (`<script setup>`) + Vue Router 4
- Pinia (state auth)
- Axios (klien API, Bearer token)
- Tailwind CSS 3 (token warna/tipografi dari design system Remix)
- Vite 6

## Menjalankan

Pastikan backend Laravel jalan lebih dulu (`php artisan serve` → `http://localhost:8000`).

```bash
cd frontend
npm install
cp .env.example .env   # sesuaikan VITE_API_BASE_URL bila perlu
npm run dev            # SPA di http://localhost:5173
```

### Kredensial demo (dari seeder)

| Role     | Email                     | Password   |
| -------- | ------------------------- | ---------- |
| Trainer  | `trainer@afterschola.id`  | `password` |
| Management | `admin@afterschola.id`  | `password` |

## Struktur

```
src/
├── main.js                        # bootstrap app (Pinia + Router)
├── App.vue                        # root <RouterView>
├── style.css                      # Tailwind + font (Inter/Plus Jakarta Sans) + Material Symbols
├── lib/api.js                     # instance axios + interceptor token
├── stores/auth.js                 # Pinia: login / me / logout + homeRoute per peran
├── router/index.js                # rute nested (AppLayout) + guard auth & redirect per peran
├── layouts/
│   └── AppLayout.vue              # shell desktop: sidebar navy + topbar, nav sadar-peran
├── lib/format.js                  # helper tampilan (tanggal, mode, status pertemuan)
└── views/
    ├── LoginView.vue              # split-screen; POST /api/login → redirect per peran
    ├── TrainerDashboardView.vue   # GET /api/dashboard (respons trainer)
    ├── ManagementDashboardView.vue# GET /api/dashboard (respons management)
    ├── AttendanceSessionsView.vue # picker sesi untuk absensi (Trainer & Management)
    ├── SessionAttendanceView.vue  # absensi murid (Hadir/Tidak) + check-in trainer + kunci
    ├── management/ScheduleView.vue# Kelola Jadwal: CRUD pertemuan (FR-4)
    ├── trainer/ScheduleView.vue    # Jadwal Saya: read-only (FR-4.1)
    └── PlaceholderView.vue        # halaman "segera hadir" untuk modul belum dibangun
```

Peran menentukan tujuan setelah login: **Management → `/management`**, **Trainer → `/trainer`**.

### Modul Jadwal & Absensi (mengikuti PRD)

- **Jadwal (FR-4):** Management membuat/ubah/hapus pertemuan (sekolah + level + trainer +
  tanggal + **jam mulai/selesai** + pertemuan ke- + mode onsite/online). Trainer **read-only**
  di "Jadwal Saya". Jam ditampilkan di seluruh kartu/tabel/kalender & header absensi.
- **Alur buka → mulai → absen:** dari jadwal, tombol **"Buka"** membuka detail pertemuan
  (belum aktif). Di detail, tekan **"Mulai Sesi"** (`POST /sessions/{id}/start` → `started_at`)
  untuk mengaktifkan absensi murid & check-in trainer. Sebelum dimulai statusnya "Belum Dimulai"
  dan input terkunci; setelah dimulai jadi "Sedang Berlangsung".
- **Jadwal Berulang (Management):** tombol "Jadwal Berulang" membuat banyak pertemuan sekaligus
  (mis. **16 pertemuan mingguan**) via `POST /sessions/bulk` — pilih sekolah/level/trainer/mode/jam,
  tanggal mulai, pengulangan (harian/mingguan/2-mingguan/bulanan), jumlah, dan nomor pertemuan awal;
  ada **pratinjau tanggal** sebelum simpan. Tiap pertemuan hasil generate tetap bisa **diubah/dihapus
  terpisah** (edit per baris) tanpa memengaruhi yang lain.
- **Tampilan Kalender** (`components/MonthCalendar.vue`): toggle Tabel/Daftar ↔ Kalender pada
  kedua halaman jadwal; grid bulanan menandai hari ber-sesi (dot onsite/online), navigasi bulan,
  ringkasan **"N pertemuan bulan ini"**, dan klik tanggal → daftar sesi hari itu.
- **Isolasi peran (RBAC UI):** router memblok trainer membuka `/management/*` dan sebaliknya
  (redirect ke dashboard perannya) — backend/Policy tetap otoritas final.
- **Modul Management (data master & monitoring):**
  - **Rekap Absensi** — alur 2 langkah: menu Rekap (`management/AttendanceRecapListView.vue`) →
    **daftar sekolah** → klik sekolah → **detail rekap** (`management/AttendanceRecapDetailView.vue`
    memakai `components/SchoolRecap.vue`): matriks kehadiran per level + export Excel/PDF.
    Management **tidak** perlu "Mulai Sesi" — di detail pertemuan (dibuka dari Jadwal) bisa langsung
    lihat/ubah/kunci; gate "Mulai Sesi" hanya untuk trainer.
  - **Sekolah & Murid** (`management/SchoolsAdminView.vue`): daftar sekolah + level + murid, tambah sekolah.
  - **Penugasan Trainer** (`management/TrainerAssignmentView.vue`): tentukan trainer↔sekolah
    (`POST /schools/{id}/trainers`).
  - **Laporan Ekspo** (`views/ExpoReportsView.vue`): **trainer membuat** (termasuk **upload foto
    dokumentasi**, boleh banyak); **management hanya merekap & melihat** (ringkasan + kartu, tanpa
    tombol "Buat Laporan"). Foto tampil sebagai thumbnail di kartu.
  - **Export laporan (Excel & PDF):** rekap absensi **dan** laporan ekspo bisa diunduh.
    - Absensi: `GET /exports/attendance/{excel,pdf}` (layout spreadsheet).
    - Ekspo: `GET /exports/expo/{excel,pdf}` — Excel mengikuti kolom Google Form
      (Timestamp, Tanggal, Sekolah, Tim, Rating, Sesuai Jadwal, Antusiasme, Kendala, Dokumentasi);
      **PDF meng-embed foto** dokumentasi (base64) via `resources/views/exports/expo.blade.php`.
    - Foto ekspo disimpan di `storage/app/public/expo-reports` (kolom `expo_reports.photo_paths`).
- **Sekolah (Trainer)** (`views/trainer/SchoolsView.vue`): daftar sekolah binaan + **rekap
  absensi murid** per level (matriks murid × pertemuan, H/–, baris Jumlah Hadir) dengan filter
  level & periode, lalu **export Excel/PDF**. Rekap layar dari `GET /exports/attendance/recap`
  (JSON), file dari endpoint `.../excel` & `.../pdf` (diunduh via blob; nama file dari
  `Content-Disposition` yang di-expose lewat `config/cors.php`).
- **Notifikasi (Trainer):** lonceng di topbar menandai **pertemuan baru yang dijadwalkan
  Management** — sesi yang belum pernah dilihat trainer (dilacak per-user di `localStorage`),
  dengan badge jumlah + dropdown; "Tandai dibaca" menandai semua terbaca.
- **Live polling (`lib/usePolling.js`):** dashboard (trainer & management), notifikasi topbar, dan
  **Kelola Jadwal** disegarkan otomatis **tiap 1 detik** (anti-overlap, jeda saat tab tersembunyi;
  refresh senyap tanpa skeleton). Jadi pertemuan baru & badge notifikasi muncul hampir real-time.
- **Jam di dashboard:** kartu "Jadwal Hari Ini" (trainer) menampilkan jam sesi (mis. `09:00 – 10:30 WIB`);
  tombolnya "Buka" / "Buka & Check-in" menuju detail pertemuan (bukan langsung absensi).
- **Absensi murid (FR-5):** status **boolean Hadir/Tidak** (sesuai spreadsheet & API `sync`),
  "Tandai Semua Hadir", catatan per murid, ringkasan hadir, dan **kunci pertemuan**.
- **Absensi trainer (FR-6):** check-in per sesi — **onsite** wajib foto + GPS (`navigator.geolocation`),
  **online** cukup screenshot (tanpa GPS). Dikirim sebagai `multipart/form-data`.
- **Gating tombol** dari permission user: `manage sessions`, `input student attendance`,
  `input trainer attendance` (backend/Policy tetap otoritas final).

## Endpoint yang dipakai

- `POST /api/login` → `{ token, user }`
- `GET  /api/me` → profil user aktif · `POST /api/logout`
- `GET  /api/dashboard` → ringkasan; respons berbeda untuk trainer vs management
- `GET/POST/PUT/DELETE /api/sessions` · `POST /api/sessions/{id}/lock` → jadwal pertemuan
- `GET /api/sessions/{id}/student-attendances` · `PUT …/student-attendances` → absensi murid
- `POST /api/sessions/{id}/trainer-attendances` → check-in trainer (multipart)
- `GET /api/schools` · `GET /api/classrooms?school_id=` · `GET /api/users` → data pendukung form jadwal
