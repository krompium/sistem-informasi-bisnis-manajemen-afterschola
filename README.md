# Afterschola Platform

Platform operasional internal After Schola — **satu web untuk semua tim**, dengan dashboard & fitur berbeda per role. Modul pertama: **Absensi digital** (murid & trainer) untuk menggantikan absensi kertas dan Google Form.

Arsitektur **API-first** (Laravel API + Sanctum) supaya siap dikonsumsi web sekarang dan mobile app nanti tanpa menulis ulang logika.

---

## Daftar Isi
- [Fitur](#fitur)
- [Arsitektur](#arsitektur)
- [Tech Stack](#tech-stack)
- [Prasyarat](#prasyarat)
- [Instalasi](#instalasi)
- [Konfigurasi `.env`](#konfigurasi-env)
- [Struktur Database](#struktur-database)
- [Role & Hak Akses](#role--hak-akses)
- [Struktur Folder](#struktur-folder)
- [Menjalankan](#menjalankan)
- [Status Pengembangan](#status-pengembangan)
- [Catatan Teknis](#catatan-teknis)

---

## Fitur

Platform terdiri dari beberapa modul di bawah satu login. Prioritas: `[MVP]` sekarang, `[v2]`/`[v3]` menyusul.

### Modul Absensi `[MVP]`
- **Data master**: sekolah (nama, alamat, PIC), level (Beginner/Intermediate), penugasan trainer↔sekolah — dikelola **Management**.
- **Murid**: dikelola Management; **trainer boleh menambah murid** di sekolah yang dipegang. Import via Excel.
- **Jadwal & pertemuan**: dibuat Management (sekolah + level + trainer + tanggal + pertemuan ke-berapa + mode onsite/online); trainer read-only.
- **Absensi murid**: centang **Hadir / Tidak** per pertemuan, ringkasan jumlah hadir, kunci pertemuan.
- **Absensi trainer**: check-in per pertemuan — onsite (foto + GPS), online (screenshot).
- **Laporan Expo/Free-Trial**: form laporan pengganti Google Form (rating, sesuai jadwal, antusiasme, kendala, dokumentasi).
- **Export**: rekap absensi ke Excel & PDF, meniru layout spreadsheet.
- **Dashboard**: trainer (sekolah & pertemuan hari ini), Management (rekap lintas sekolah).

### Modul lain (menyusul)
- **Management** `[v2]` — oversight lintas modul, kelola user & role, laporan agregat.
- **HR** `[v2]` — data karyawan/trainer, cuti/izin, basis kehadiran untuk payroll.
- **Finance** `[v2]` — payroll dari absensi trainer, invoice sekolah, kas.
- **Developer / Manajemen Proyek** `[v3]` — team, proyek, task board (kanban + progres %).

Rincian fitur lengkap: lihat `docs/Fitur-per-Modul-After-Schola.md`.

---

## Arsitektur

```
                 ┌─────────────────────┐
   Web (SPA)  ── │   Laravel API        │ ── Mobile App (nanti)
   Vue 3         │   Auth: Sanctum      │    Flutter / RN
                 │   RBAC: spatie       │
                 └──────────┬───────────┘
                            │
                      MySQL / MariaDB
```

- Semua fungsi lewat **REST API** (JSON). Web jadi konsumen pertama; mobile menyusul memakai API yang sama.
- Validasi & otorisasi (Policy) **di backend** — data trainer per sekolah tak bisa di-bypass dari klien.

---

## Tech Stack

| Lapisan | Teknologi |
|---|---|
| Backend / API | Laravel 11 (PHP 8.2+) |
| Auth | Laravel Sanctum (token) |
| RBAC | spatie/laravel-permission |
| Export Excel | maatwebsite/excel |
| Export PDF | barryvdh/laravel-dompdf |
| Database | MySQL 8 / MariaDB |
| Frontend Web | Vue 3 + Vite *(repo terpisah / folder `frontend`)* |
| Mobile | Flutter / React Native *(nanti)* |

---

## Prasyarat

- PHP >= 8.2 + ekstensi umum Laravel (`mbstring`, `pdo`, `openssl`, `bcmath`, `gd`, dll)
- Composer 2.x
- MySQL 8 / MariaDB
- Node.js 18+ & npm *(untuk frontend SPA)*

---

## Instalasi

### 1. Backend (Laravel API)

```bash
# 1. Buat project
composer create-project laravel/laravel after-schola
cd after-schola

# 2. Install paket
composer require laravel/sanctum spatie/laravel-permission maatwebsite/excel barryvdh/laravel-dompdf

# 3. Publish konfigurasi
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# 4. Salin starter (migration, model, seeder) dari paket ini ke project
cp -r after-schola-starter/database/migrations/*  database/migrations/
cp -r after-schola-starter/app/Models/*           app/Models/       # timpa User.php
cp -r after-schola-starter/database/seeders/*      database/seeders/

# 5. Setup env & database (lihat bagian Konfigurasi), lalu:
php artisan migrate
php artisan db:seed --class=RoleSeeder

# 6. Link storage (untuk foto/screenshot bukti kehadiran)
php artisan storage:link

# 7. Jalankan
php artisan serve
```

### 2. Frontend (Vue SPA) — *saat sudah dibuat*

```bash
cd frontend
npm install
npm run dev
```

---

## Konfigurasi `.env`

```env
APP_NAME="After Schola"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=after_schola
DB_USERNAME=root
DB_PASSWORD=

# Sanctum (sesuaikan domain frontend)
SANCTUM_STATEFUL_DOMAINS=localhost:5173
FRONTEND_URL=http://localhost:5173
FILESYSTEM_DISK=public
```

---

## Struktur Database

| Tabel | Fungsi |
|---|---|
| `users` | Semua user (+ `is_active`); role via spatie |
| `schools` | Data sekolah (nama, alamat, PIC) |
| `trainer_school` | Pivot penugasan trainer ↔ sekolah |
| `classrooms` | **Level** per sekolah (Beginner/Intermediate) |
| `students` | Murid (+ `origin_class` = kelas asal, mis. `8A`) |
| `class_sessions` | Pertemuan (sekolah + level + trainer + tanggal + `meeting_no` + mode) |
| `student_attendances` | Absensi murid — `is_present` (Hadir/Tidak) per pertemuan |
| `trainer_attendances` | Absensi trainer — status, `check_in_at`, `photo_path`, `latitude/longitude` |
| `expo_reports` | Laporan Expo/Free-Trial (ganti Google Form) |
| tabel spatie | `roles`, `permissions`, `model_has_roles`, dll |

> Detail kolom & relasi: lihat `docs/PRD-Schola-Platform-Absensi.md` (Bab 8).

---

## Role & Hak Akses

5 role: **Management, Finance, HR, Trainer, Developer**.

| Modul | Management | Finance | HR | Trainer | Developer |
|---|:-:|:-:|:-:|:-:|:-:|
| Absensi | lihat semua | lihat | lihat | **input** (sekolahnya) | ❌ |
| Keuangan/Payroll | lihat | **kelola** | ➖ | slip sendiri | ❌ |
| SDM/HR | lihat | ➖ | **kelola** | ajukan cuti | ajukan cuti |
| Manajemen Proyek | lihat | ❌ | ❌ | ❌ | **kelola** |
| Kelola user & role | **kelola** | ❌ | ➖ | ❌ | ❌ |

**Isolasi data:** trainer hanya mengakses murid, level, dan absensi dari sekolah yang ditugaskan padanya — divalidasi lewat Policy di backend.

---

## Struktur Folder

```
after-schola/
├── app/
│   ├── Models/            # School, Classroom, Student, ClassSession,
│   │                      # StudentAttendance, TrainerAttendance, ExpoReport, User
│   ├── Http/
│   │   ├── Controllers/Api/   # (menyusul) endpoint per modul
│   │   ├── Requests/          # (menyusul) validasi
│   │   ├── Resources/         # (menyusul) API Resource
│   │   └── Policies/          # (menyusul) isolasi akses per sekolah
├── database/
│   ├── migrations/        # 9 tabel modul absensi + platform
│   └── seeders/           # RoleSeeder
├── routes/
│   └── api.php            # endpoint API
├── frontend/             # (menyusul) Vue 3 SPA
└── docs/                 # PRD & rincian fitur
```

---

## Menjalankan

```bash
php artisan serve          # API di http://localhost:8000
# (frontend) npm run dev   # SPA di http://localhost:5173
```

Login awal: buat user via seeder/tinker lalu assign role, contoh:

```bash
php artisan tinker
>>> $u = App\Models\User::factory()->create(['name'=>'Admin','email'=>'admin@afterschola.id','password'=>bcrypt('password')]);
>>> $u->assignRole('management');
```

---

## Status Pengembangan

| Fase | Isi | Status |
|---|---|:-:|
| **Fase 0** | Fondasi: auth, RBAC 5 role, dashboard per role | 🔨 migration/model siap |
| **Fase 1** | Modul Absensi lengkap (murid, trainer, expo, export) | 🔨 skema siap, API menyusul |
| Fase 2 | HR + Finance | ⏳ |
| Fase 3 | Manajemen Proyek (Developer) | ⏳ |
| Fase 4 | Jadwal berulang, analitik, mobile | ⏳ |

Sudah tersedia di repo ini: **migration + model Eloquent + RoleSeeder** (Fase 0 & skema Fase 1).
Menyusul: Sanctum auth endpoint, Policy isolasi sekolah, Controller/API Resource, export Excel/PDF, frontend SPA.

---

## Catatan Teknis

- Tabel pertemuan dinamai **`class_sessions`**, bukan `sessions`, agar tidak bentrok dengan tabel bawaan Laravel (session driver database).
- **"Kelas" = level** (Beginner/Intermediate). Kelas asal siswa disimpan di `students.origin_class`.
- **Absensi murid boolean** (`is_present`) mengikuti format TRUE/FALSE di spreadsheet asli.
- **`photo_path`** pada absensi trainer menyimpan foto (onsite) atau screenshot (online); GPS hanya untuk onsite.
- Semua otorisasi ditegakkan di backend (Policy), bukan hanya disembunyikan di UI.

---

_Dokumen terkait: `docs/PRD-Schola-Platform-Absensi.md`, `docs/Fitur-per-Modul-After-Schola.md`._
