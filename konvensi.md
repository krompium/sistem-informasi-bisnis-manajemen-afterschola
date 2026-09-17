# Konvensi — After Schola Platform

> **Cakupan:** seluruh sistem (semua modul, semua fase).
> **Kapan dibaca:** setiap kali menulis atau mengubah kode.
> **Status:** v1. Bagian bertanda 🔸 masih perlu dikonfirmasi ke tim sebelum dikunci.

Dokumen ini mengatur **cara menulis** kode. Untuk *apa yang dibangun* lihat
`prd-<modul>.md`; untuk *desain teknisnya* lihat `tdd.md`.

---

## 1. Struktur Repo

```
sistem-informasi-bisnis-manajemen-afterschola/   ← repo root
└── after-schola/                                ← Laravel 11 (backend, API-only)
    ├── app/Http/Controllers/Api/                ← SEMUA controller di sini
    ├── app/Http/Requests/                       ← validasi
    ├── app/Http/Resources/                      ← format response
    ├── app/Policies/                            ← otorisasi
    ├── app/Models/
    ├── routes/api.php                           ← satu-satunya file route yang dipakai
    ├── resources/views/exports/                 ← HANYA untuk template PDF
    └── frontend/                                ← Vue 3 + Vite (di DALAM after-schola)
```

**Aturan:**
- Backend tidak punya halaman web. `resources/views` hanya berisi template PDF
  (`attendance.blade.php`, `expo.blade.php`). Jangan menambah Blade view untuk UI.
- `routes/web.php` tidak dipakai. Semua endpoint di `routes/api.php`.
- Frontend berada di dalam `after-schola/frontend`, bukan sibling di root repo.

---

## 2. Penamaan

| Objek | Aturan | Contoh |
|---|---|---|
| Tabel | `snake_case`, jamak | `student_attendances` |
| Tabel pivot | `snake_case`, singular, urut alfabet | `trainer_school`, `team_user` |
| Kolom | `snake_case` | `check_in_at`, `is_present` |
| Boolean | awali `is_` / `has_` | `is_present`, `is_locked`, `has_issue` |
| Timestamp | akhiri `_at` | `check_in_at` |
| Foreign key | `<model_singular>_id` | `class_session_id` |
| Model | `PascalCase`, singular | `StudentAttendance` |
| Controller | `PascalCase` + `Controller` | `StudentAttendanceController` |
| Endpoint URL | `kebab-case`, jamak | `/api/expo-reports` |
| Variabel & fungsi | `camelCase` | `markAttendance()` |
| Komponen Vue | `PascalCase` | `AttendanceSheet.vue` |

**Bahasa:** semua identifier (tabel, kolom, model, fungsi, variabel, komentar
kode) dalam **Bahasa Inggris**. Yang boleh Bahasa Indonesia: pesan error yang
tampil ke user, isi dokumen, dan commit message body.

**Jebakan yang sudah diketahui:** tabel pertemuan bernama `class_sessions`,
**bukan** `sessions` — nama itu bentrok dengan tabel bawaan Laravel (session
driver `database`). Pola serupa: hindari `jobs`, `cache`, `failed_jobs`,
`password_reset_tokens`.

---

## 3. Konvensi API

### 3.1 Struktur endpoint
Ikuti resource routing standar Laravel. Aksi di luar CRUD jadi sub-path eksplisit:

```
GET    /api/schools                     index
POST   /api/schools                     store
GET    /api/schools/{school}            show
PUT    /api/schools/{school}            update
DELETE /api/schools/{school}            destroy

POST   /api/class-sessions/{id}/lock    aksi non-CRUD
POST   /api/students/import             aksi non-CRUD
```

### 3.2 Format response — DIKUNCI (dikonfirmasi dari kode, bukan asumsi)
Ada **dua pola yang disengaja**, dikonfirmasi cocok di backend (`AuthController`)
maupun frontend (`stores/auth.js`) yang sudah berjalan:

**Pola 1 — endpoint auth: flat.** `login()` mengembalikan `{ "token": ...,
"user": {...} }` tanpa key `data`. Frontend membaca `data.token` dan `data.user`
langsung. Berlaku untuk: `login`, dan endpoint auth lain yang mengembalikan
lebih dari satu hal sekaligus.

**Pola 2 — endpoint resource: dibungkus `data`.** Return `UserResource`/resource
lain langsung dari controller → Laravel otomatis membungkus jadi
`{ "data": {...} }`. Berlaku untuk `me()` dan semua endpoint CRUD/resource baru
(schools, students, class-sessions, dst).

```json
// Auth (flat)
{ "token": "...", "user": { ... } }

// Resource tunggal (dibungkus)
{ "data": { ... } }

// Koleksi (paginasi bawaan Laravel)
{ "data": [ ... ], "links": { ... }, "meta": { ... } }
```

**Kenapa dikunci begini, bukan diseragamkan:** frontend (`auth.login()`) sudah
membaca `data.token` langsung — menyeragamkan ke `{ "data": {...} }` berarti
baris itu pecah dan perlu perubahan terkoordinasi backend+frontend untuk sesuatu
yang cuma soal konsistensi kosmetik, bukan bug. `fetchMe()` bahkan sudah
menangani kedua pola ini secara eksplisit dengan komentar
(`data.data ?? data`) — tanda pola ini sudah disadari tim, cuma belum ditulis.

**Follow-up kecil (bukan blocker):** setelah §3.2 ini eksplisit dituliskan,
fallback `?? data` di `fetchMe()` sebenarnya sudah tidak perlu lagi (formatnya
sekarang pasti, bukan tebakan) — tapi ini cuma pembersihan kecil, boleh dibiarkan
kalau tidak mengganggu.

Error validasi (422, format bawaan Laravel — jangan diubah):
```json
{ "message": "...", "errors": { "name": ["..."] } }
```

Pesan sukses tanpa data (dikonfirmasi dari `logout`, `forgotPassword`,
`resetPassword`):
```json
{ "message": "Pesan yang bisa dibaca user" }
```

**Pola terkonfirmasi — pakai ValidationException untuk penolakan berbasis
aturan bisnis, bukan cuma validasi input.** `login()` melempar
`ValidationException::withMessages(['email' => ['Email atau password salah.']])`

untuk kredensial salah maupun akun nonaktif — bukan input yang secara teknis
"invalid", tapi ditangani lewat mekanisme yang sama supaya bentuk error di
frontend seragam. Endpoint lain yang menolak karena aturan bisnis (mis. kunci
absensi, kapasitas kelas penuh) sebaiknya ikut pola ini, bukan bikin bentuk
error baru.

### 3.3 Status code
| Kode | Dipakai untuk |
|---|---|
| 200 | GET, PUT, aksi sukses |
| 201 | POST yang membuat resource baru |
| 204 | DELETE sukses |
| 401 | belum login / token invalid |
| 403 | sudah login tapi tidak berhak (kena Policy) |
| 404 | resource tidak ada **atau** bukan milik user (lihat §4.3) |
| 422 | validasi gagal |

### 3.4 Autentikasi
Sanctum token. Publik: `login`, `forgot-password`, `reset-password`. Selain itu
wajib `auth:sanctum`. Endpoint `/users` tambah `permission:manage users`.

---

## 4. Otorisasi & Isolasi Data

Ini bagian paling penting di dokumen ini.

### 4.1 Backend adalah satu-satunya penentu
Menyembunyikan menu atau tombol di frontend **bukan** otorisasi. Setiap endpoint
yang menyentuh data sekolah, kelas, murid, sesi, atau absensi wajib lewat Policy.

### 4.2 Aturan isolasi trainer
Trainer hanya boleh mengakses data dari sekolah yang ditugaskan padanya via
pivot `trainer_school`. Berlaku untuk **membaca, membuat, mengubah, menghapus,
dan mengekspor** — bukan hanya membaca.

Pola yang dipakai:
```php
// Policy — untuk akses satu resource
public function view(User $user, Student $student): bool
{
    if ($user->hasRole('Management')) return true;
    return $user->schools()->whereKey($student->school_id)->exists();
}

// Query scope — untuk listing, agar data sekolah lain tidak pernah ikut terambil
Student::query()
    ->when($user->hasRole('Trainer'), fn ($q) =>
        $q->whereIn('school_id', $user->schools()->select('id'))
    );
```

Listing **tidak boleh** mengandalkan Policy saja — Policy dipanggil per-model,
sementara listing mengembalikan banyak model. Selalu filter di query.

### 4.3 Kebocoran informasi
Kalau trainer mengakses resource milik sekolah lain, kembalikan **404**, bukan
403. 403 memberi tahu bahwa resource itu ada.

### 4.4 Yang tidak pernah boleh disederhanakan
Validasi input, otorisasi, penanganan error, dan keamanan upload file. Aturan
"cari solusi paling malas" di Custom Instructions **tidak berlaku** untuk empat
hal ini.

---

## 5. Konvensi Kode Backend

### 5.1 Tanggung jawab tiap layer
| Layer | Isi | Tidak boleh berisi |
|---|---|---|
| `routes/api.php` | pemetaan URL → controller + middleware | logika |
| Controller | orkestrasi: otorisasi → ambil data → kembalikan Resource | query kompleks, aturan bisnis panjang |
| Form Request | validasi + `authorize()` | query data |
| Policy | keputusan boleh/tidak | perubahan data |
| Model | relasi, cast, scope, accessor | logika lintas-modul |
| API Resource | bentuk output JSON | query (hati-hati N+1) |

**Service/Repository/Action class:** jangan dibuat untuk satu pemakaian. Baru
buat kalau logika yang sama dipakai di ≥2 tempat, atau satu method controller
sudah lewat ~40 baris.

### 5.2 Validasi
Pakai Form Request terpisah untuk endpoint CRUD/resource (schools, students,
class-sessions, dst).

**Dikonfirmasi dari `AuthController`:** endpoint auth (`login`, `forgotPassword`,
`resetPassword`) memakai `$request->validate()` inline meski field-nya lebih
dari dua (login: 3 field, resetPassword: 4 field) — dan ini tetap dipakai
sebagai konvensi, bukan dianggap pelanggaran. Alasannya: endpoint auth berdiri
sendiri, validasinya tidak dipakai ulang di tempat lain, jadi FormRequest
terpisah cuma nambah file tanpa manfaat (sesuai prinsip "jangan bikin abstraksi
untuk satu pemakaian" di Custom Instructions).

Aturannya jadi: **FormRequest untuk endpoint CRUD/resource, validate() inline
boleh untuk endpoint auth atau endpoint berdiri sendiri lain yang tidak
dipakai ulang** — bukan soal jumlah field.

### 5.3 Upload file (foto & screenshot absensi trainer)
- Validasi wajib: `image`, `mimes:jpeg,jpg,png`, `max:5120` (5 MB) 🔸
- Simpan dengan nama hasil generate (`Str::uuid()`), **jangan** pakai nama asli
  dari client
- Disimpan di disk `public`, kolom DB menyimpan path relatif — bukan URL penuh
- Sesi `onsite`: foto + `latitude`/`longitude` wajib
- Sesi `online`: screenshot wajib, koordinat `null`

### 5.4 Mencegah N+1
Setiap endpoint listing wajib `with()` relasi yang dipakai API Resource-nya.
Kalau menambah field relasi di Resource, cek eager loading di controller.

### 5.5 Migration
- Satu migration satu tujuan; jangan menumpuk banyak perubahan tak berkaitan
- Selalu definisikan foreign key + `onDelete` yang eksplisit
- Enum ditulis sebagai `enum` di migration dan `cast` ke PHP enum di Model
- **Jangan** menjalankan `vendor:publish` untuk Sanctum atau spatie di repo ini —
  migration-nya sudah ada, dan publish akan membuat duplikat

---

## 6. Konvensi Frontend (Vue 3 + Vite)

- Composition API + `<script setup>`. Jangan campur Options API.
- State server (data dari API) jangan disalin ke store global tanpa alasan;
  ambil di komponen yang memakainya.
- Satu lokasi terpusat untuk instance HTTP client + penyisipan header
  `Authorization: Bearer <token>`. Jangan menulis `fetch`/axios mentah di
  komponen.
- Komponen yang cuma dipakai satu halaman letakkan berdampingan dengan halaman
  itu, bukan di folder `components/` global.
- **Mobile-first (NFR-1):** trainer mengabsen dari HP di dalam kelas. Setiap
  layar trainer harus diuji di lebar 360px sebelum dianggap selesai.
- **Tailwind** (dikonfirmasi dari `LoginView.vue`) — jangan tambah UI library baru
  tanpa persetujuan.

---

## 7. Testing

Detail strategi ada di `testing.md`. Yang wajib diketahui saat menulis kode:

- **Proyek ini pakai TDD** untuk endpoint/logika bisnis baru — test ditulis
  sebelum implementasi. Alur lengkapnya (red-green-refactor) ada di Custom
  Instructions bagian "Cara kerja per sesi", bukan di sini. Kode existing
  (ditulis sebelum aturan ini) tidak retroaktif ditulis ulang testnya.
- Feature test (HTTP) adalah default. Unit test hanya untuk logika murni yang
  rumit (mis. kalkulasi rekap).
- **Setiap endpoint yang menyentuh data sekolah wajib punya minimal 1 test yang
  membuktikan trainer dari sekolah lain mendapat 404/403.** Ini non-negotiable.
- Jangan menulis test untuk getter, setter, atau kode tanpa percabangan.
- Pakai `RefreshDatabase`, factory, dan seeder role — jangan bergantung pada
  data hasil migrate manual.

---

## 8. Git 🔸

- Branch kerja per orang/fitur; branch `sabil` sudah dipakai rekan tim
- Format commit: `<tipe>: <ringkasan imperatif>`
  ```
  feat: add school CRUD endpoints
  fix: prevent trainer accessing other school students
  refactor: extract attendance summary query
  docs: update konvensi.md
  test: add trainer isolation cases for students endpoint
  chore: bump php requirement
  ```
- Jangan commit `.env`, `storage/app/public/*`, `vendor/`, `node_modules/`
- Jangan commit hasil `vendor:publish`

---

## 9. Environment Lokal

| Item | Versi / catatan |
|---|---|
| PHP | 8.4.x — ekstensi `zip` wajib aktif (dibutuhkan openspout untuk export Excel) |
| Server lokal | Laragon (Windows) |
| Database | MySQL 8 |
| Node | untuk `frontend/` |

**Setup dari hasil clone** (bukan bootstrap dari nol):
```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
```
Jangan menjalankan `vendor:publish` — README menjelaskan langkah bootstrap dari
nol, bukan langkah clone, dan menjalankannya menimbulkan migration duplikat.

---

## 10. Yang Perlu Dikonfirmasi 🔸

Sebelum dokumen ini dikunci jadi v1 final:

1. ~~Format response API (§3.2)~~ ✅ dikonfirmasi dari `AuthController` +
   `stores/auth.js` — dua pola disengaja, lihat §3.2.
2. Batas ukuran & mime upload foto absensi (§5.3).
3. Strategi branch & apakah ada aturan PR/review di tim (§8).
4. ~~CSS framework (§6)~~ ✅ Tailwind, dikonfirmasi dari `LoginView.vue`.

