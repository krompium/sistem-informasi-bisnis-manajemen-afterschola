# Dokumentasi Pengerjaan — After Schola Platform

Ringkasan seluruh fitur yang telah dikerjakan pada platform operasional & absensi After Schola
(backend Laravel API + frontend Vue 3 SPA), mengikuti PRD & dokumen fitur per modul.

> Modul fokus saat ini: **Fondasi + Absensi** (Trainer & Management). Modul HR/Finance/Developer
> belum dibangun (fase berikutnya).

---

## 1. Tech Stack

| Lapisan | Teknologi |
|---|---|
| Backend / API | Laravel 13 (PHP 8.3+), API-first (`routes/api.php`) |
| Auth | Laravel Sanctum (Bearer token) |
| RBAC | spatie/laravel-permission (5 role) |
| Export Excel | openspout/openspout |
| Export PDF | barryvdh/laravel-dompdf |
| Database | SQLite (dev) |
| Frontend | Vue 3 `<script setup>` + Vue Router + Pinia + Axios |
| Styling | Tailwind CSS 3 (design system Stitch "Remix") |
| Build | Vite |

**Design system (Remix):** shell desktop, sidebar navy `#0B2A5B` + royal blue `#1D4ED8`,
heading *Plus Jakarta Sans*, body *Inter*.

---

## 2. Menjalankan

```bash
# Backend (dari after-schola/)
composer install
php artisan migrate --seed
php artisan storage:link
php artisan serve                 # http://localhost:8000

# Frontend (dari after-schola/frontend/)
npm install
npm run dev                       # http://localhost:5173
```

### Kredensial demo (seeder)
| Role | Email | Password |
|---|---|---|
| Management | `admin@afterschola.id` | `password` |
| Trainer | `trainer@afterschola.id` | `password` |

Login mendeteksi role otomatis → Management diarahkan ke `/management`, Trainer ke `/trainer`.

---

## 3. Fitur yang Selesai

### 3.1 Fondasi Platform
- **Autentikasi** email/password via Sanctum; token disimpan di `localStorage`; interceptor Axios
  menyisipkan Bearer + auto-redirect ke login saat 401.
- **RBAC di UI**: menu sidebar & konten menyesuaikan role; router **memblokir** trainer membuka
  `/management/*` dan sebaliknya (redirect ke dashboard perannya). Backend/Policy tetap otoritas final.
- **Shell aplikasi** (`AppLayout.vue`): sidebar navy + topbar sticky, drawer untuk layar kecil,
  avatar & badge role, pencarian, lonceng notifikasi.
- **Live polling** (`lib/usePolling.js`): dashboard, notifikasi, & Kelola Jadwal disegarkan otomatis
  tiap 1 detik (anti-overlap, jeda saat tab tersembunyi, refresh senyap).
- **Notifikasi Trainer**: lonceng topbar menandai pertemuan baru yang dijadwalkan Management
  (deteksi sesi belum dilihat, dilacak per-user di `localStorage`), badge jumlah + dropdown.

### 3.2 Login
- Halaman split-screen: panel branding navy + form putih, tombol royal blue, toggle password,
  deteksi role otomatis, penanganan error (422/401/koneksi).

### 3.3 Dashboard
- **Trainer**: greeting, banner check-in hari ini, stat (sekolah aktif, pertemuan hari ini, total
  murid), **Jadwal Hari Ini** (kartu sesi + jam + tombol "Buka"), daftar "Sekolah Saya".
- **Management**: header + aksi cepat, 4 stat card, tabel "trainer perlu perhatian" (belum check-in),
  grafik tren + log aktivitas. Live monitoring (auto-refresh).

### 3.4 Jadwal (FR-4) — dikelola Management
- **Kelola Jadwal (Management)**: CRUD pertemuan (sekolah + level + trainer + tanggal + **jam
  mulai/selesai** + pertemuan ke- + mode onsite/online + catatan), filter (sekolah/trainer/tanggal/
  status), stat strip (Bulan Ini/Total/Hari Ini/Perlu Diabsen/Terkunci).
- **Tampilan Kalender** (`MonthCalendar.vue`): toggle Tabel ↔ Kalender, grid bulanan menandai hari
  ber-sesi (dot onsite/online), navigasi bulan, ringkasan "N pertemuan bulan ini", klik tanggal →
  daftar sesi hari itu.
- **Jadwal Berulang**: buat banyak pertemuan sekaligus (mis. 16 pertemuan mingguan) via
  `POST /sessions/bulk` — pengulangan harian/mingguan/2-mingguan/bulanan, jumlah, nomor awal, dengan
  **pratinjau tanggal**. Tiap pertemuan tetap bisa diubah/dihapus terpisah.
- **Jadwal Saya (Trainer)**: read-only, tab (Hari Ini / Akan Datang / Semua) + kalender, tombol "Buka".

### 3.5 Absensi (FR-5 & FR-6)
- **Alur buka → mulai → absen**: dari jadwal tombol "Buka" membuka detail pertemuan; **trainer**
  menekan "Mulai Sesi" (`POST /sessions/{id}/start`) untuk mengaktifkan absensi & check-in. Status:
  Belum Dimulai → Sedang Berlangsung → Terkunci. **Management tidak** perlu "Mulai Sesi" (bisa
  langsung lihat/ubah/kunci untuk koreksi).
- **Absensi murid**: toggle **Hadir / Tidak** (boolean, sesuai spreadsheet), "Tandai Semua Hadir",
  catatan per murid, ring progress, simpan (bulk `PUT /sessions/{id}/student-attendances`).
- **Kunci pertemuan** (`POST /sessions/{id}/lock`): setelah dikunci read-only; management/trainer
  pengampu bisa membuka kunci.
- **Check-in Trainer** (FR-6): status Hadir/Terlambat/Tidak Hadir; **onsite** wajib foto + GPS
  (`navigator.geolocation`), **online** cukup screenshot; kirim `multipart/form-data`.

### 3.6 Rekap Absensi & Export
- **Rekap Absensi (Management)** — alur 2 langkah: menu Rekap → **daftar sekolah** → klik sekolah →
  **detail rekap** (matriks murid × pertemuan, baris "Jumlah Hadir") + filter level/periode.
- **Sekolah Saya (Trainer)**: daftar sekolah binaan + rekap absensi murid.
- **Export**: `GET /exports/attendance/{excel,pdf}` — layout meniru spreadsheet (nama sekolah, header
  nomor pertemuan + tanggal, sel ✓ untuk hadir, baris Jumlah Hadir, styling hijau + border).

### 3.7 Data Master (Management)
- **Sekolah & Murid**: daftar sekolah (alamat, PIC, level, jumlah murid) + murid per level, tambah sekolah.
- **Penugasan Trainer**: tentukan trainer memegang sekolah apa saja (`POST /schools/{id}/trainers`).

### 3.8 Laporan Ekspo / Free-Trial (FR-7)
- **Trainer membuat** laporan (tanggal, sekolah, tim, rating 1–5, sesuai jadwal, antusiasme, kendala +
  penjelasan, **upload foto dokumentasi** [banyak], link dokumentasi).
- **Management merekap & melihat** (ringkasan: total, sekolah, rata-rata rating, kendala) — tanpa
  tombol buat. Foto tampil sebagai thumbnail.
- **Export**: `GET /exports/expo/{excel,pdf}` — Excel mengikuti kolom Google Form; **PDF meng-embed
  foto** dokumentasi.

---

## 4. Endpoint API Utama

```
POST   /api/login | /api/logout | GET /api/me
GET    /api/dashboard                          # ringkasan per role
# Sekolah / level / murid
GET/POST/PUT/DELETE /api/schools               # + POST /api/schools/{id}/trainers
GET/POST/PUT/DELETE /api/classrooms
GET/POST/PUT/DELETE /api/students              # + POST /api/students/import
# Jadwal / pertemuan
GET/POST/PUT/DELETE /api/sessions
POST   /api/sessions/bulk                      # jadwal berulang
POST   /api/sessions/{id}/start                # mulai sesi
POST   /api/sessions/{id}/lock                 # kunci/buka
# Absensi
GET/PUT /api/sessions/{id}/student-attendances
GET/POST /api/sessions/{id}/trainer-attendances
# Laporan ekspo
GET/POST/PUT/DELETE /api/expo-reports
# Export
GET    /api/exports/attendance/{recap,excel,pdf}
GET    /api/exports/expo/{excel,pdf}
```

---

## 5. Struktur Frontend Penting

```
frontend/src/
├── layouts/AppLayout.vue            # shell sidebar+topbar, nav sadar-role, notifikasi
├── components/
│   ├── MonthCalendar.vue            # kalender bulanan pertemuan
│   └── SchoolRecap.vue              # panel rekap absensi 1 sekolah + export
├── lib/{api,format,usePolling}.js
├── stores/auth.js                   # login/me/logout, permission getter, homeRoute
├── router/index.js                  # rute nested + guard auth & isolasi role
└── views/
    ├── LoginView.vue
    ├── TrainerDashboardView.vue | ManagementDashboardView.vue
    ├── AttendanceSessionsView.vue | SessionAttendanceView.vue
    ├── ExpoReportsView.vue          # dipakai trainer (buat) & management (rekap)
    ├── trainer/{ScheduleView,SchoolsView}.vue
    └── management/{ScheduleView,TrainerAssignmentView,SchoolsAdminView,
                    AttendanceRecapListView,AttendanceRecapDetailView}.vue
```

---

## 6. Catatan Teknis & Perbaikan Bug
- **`whenCounted()` (Laravel 13)** tidak meng-emit count → `students_count`/`present_count` hilang;
  diganti `whenNotNull($this->..._count)` di resource terkait.
- **`toLocaleDateString` tidak mendukung `timeStyle`** → dibuat helper `formatStamp` (tanggal+jam).
- **CORS**: `Content-Disposition` di-expose (`config/cors.php`) agar nama file export terbaca frontend.
- **`class_sessions`** ditambah kolom `start_time`, `end_time`, `started_at` (migration terpisah).
- **`expo_reports`** ditambah kolom `photo_paths` (JSON) untuk foto dokumentasi.

---

## 7. Belum Dikerjakan (Fase Berikutnya)
- **Manajemen User** (Management buat/nonaktifkan user + atur role) — masih placeholder.
- Modul **HR**, **Finance**, **Developer/Manajemen Proyek** (`[v2]`/`[v3]` di PRD).
- Jadwal berulang lanjutan (deteksi bentrok), notifikasi push, mode offline/PWA, mobile app.
