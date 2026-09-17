# TDD — After Schola Platform

> **Cakupan:** Fase 0 (Platform Shell) + Fase 1 (Modul Absensi) — sesuai
> `prd-absensi.md` §9. Fase 2/3 (HR, Finance, Manajemen Proyek) belum digali,
> skema di §8.1 PRD dicatat untuk referensi saja, bukan untuk dibangun sekarang.
> **Rujukan:** `brd-alur-proses-bisnis.md` (konteks bisnis), `prd-absensi.md`
> (requirement & skema awal §8), `konvensi.md` (aturan penulisan kode & format
> response — dokumen ini TIDAK mengulang isinya, hanya mereferensikan).
> **Dasar:** skema di bawah disusun dari PRD §8 dan dicocokkan dengan
> `php artisan route:list` yang sudah ada di repo — nama tabel/controller di
> sini match dengan controller yang sudah berjalan (`SchoolController`,
> `ClassroomController`, `StudentController`, `ClassSessionController`,
> `StudentAttendanceController`, `TrainerAttendanceController`,
> `ExpoReportController`, `ExportController`, `DashboardController`,
> `UserController`). Tipe kolom persis & nullable belum diverifikasi ke file
> migration asli — tandai 🔸 di tabel manapun kalau ternyata beda.

---

## 1. Arsitektur Tingkat Tinggi

```
Vue 3 SPA (frontend/)                Laravel 11 API (after-schola/)
┌─────────────────────┐              ┌──────────────────────────────┐
│ Pinia store          │  HTTP/JSON   │ routes/api.php                │
│ (auth, dashboard,    │─────────────▶│  → Controller (Api/)          │
│  absensi, dst)       │  Bearer      │    → Form Request (validasi)  │
│                       │  <token>     │    → Policy (otorisasi)       │
│ Axios instance        │◀─────────────│    → Model (Eloquent)         │
│ (lib/api.js)          │   JSON       │    → API Resource (response)  │
└─────────────────────┘              └──────────────┬─────────────────┘
                                                       │
                                                MySQL 8 ─┘
```

Alasan API-first (dari PRD §7): web sekarang adalah konsumen pertama API yang
sama; mobile app menyusul tanpa menulis ulang logika. Konsekuensinya: **semua**
validasi & otorisasi wajib di backend — frontend murni presentasi, tidak pernah
jadi satu-satunya penjaga (lihat konvensi.md §4.1).

---

## 2. Skema Database

### 2.1 Fondasi (Fase 0)

| Tabel | Kolom utama | Catatan |
|---|---|---|
| `users` | id, name, email, password, is_active (bool), timestamps | Role via spatie, bukan kolom di sini |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | — | Bawaan `spatie/laravel-permission`, sudah ada di migration repo |

**Konvensi permission** (dikonfirmasi dari route yang sudah ada —
`permission:manage users`): nama permission string natural, huruf kecil,
spasi — bukan `kebab-case`. Contoh ke depan: `manage users`,
`view all attendance`, `manage schools`.

### 2.2 Modul Absensi (Fase 1)

| Tabel | Kolom | FK & Catatan |
|---|---|---|
| `schools` | id, name, address, pic_name, pic_phone, timestamps | — |
| `trainer_school` | id, user_id, school_id, timestamps | Pivot. FK `user_id`→users, `school_id`→schools. Unique `(user_id, school_id)` |
| `classrooms` | id, school_id, name (string), level (enum: `beginner`,`intermediate`), timestamps | ✅ Dikunci — dua kolom dipertahankan sesuai PRD asli. `level` = nilai normalized untuk logika sistem (filter, validasi); `name` = label tampilan yang bisa disesuaikan per sekolah (mis. sekolah tertentu menyebutnya "Pemula" bukan "Beginner"). Kalau ternyata `name` selalu sama persis dengan `level` dan tidak pernah disesuaikan, kolom ini jadi murni redundan — tapi itu keputusan produk, bukan yang perlu saya paksa dari sisi desain data |
| `students` | id, school_id, classroom_id, name, origin_class, created_by (FK→users, nullable), timestamps | `origin_class` = kelas asal di sekolah (mis. "8A"), bebas teks |
| `class_sessions` | id, school_id, classroom_id, trainer_id (FK→users), mode (enum: `onsite`,`online`), date, meeting_no (tinyint), is_locked (bool, default false), note (nullable), timestamps | **Bukan** `sessions` (bentrok tabel session Laravel) |
| `student_attendances` | id, class_session_id, student_id, is_present (bool), note (nullable), timestamps | Unique `(class_session_id, student_id)` — cegah duplikat |
| `trainer_attendances` | id, class_session_id, trainer_id (FK→users), check_in_at (timestamp), status (enum: `hadir`,`terlambat`,`tidak_hadir`), photo_path (nullable), latitude (decimal nullable), longitude (decimal nullable), note (nullable), timestamps | `latitude`/`longitude` **null** kalau `class_sessions.mode = online` (lihat §3) |
| `expo_reports` | id, school_id, trainer_id (FK→users), date, team_name, rating (tinyint, 1–5), on_schedule (enum: `ya`,`sebagian`,`tidak`), enthusiasm (text), has_issue (bool), issue_note (nullable), doc_url (nullable), timestamps | Berdiri sendiri, tidak terhubung ke `class_sessions` |

### 2.3 Relasi

```
schools ─┬─< trainer_school >─┬─ users (role: trainer)
         │
         ├─< classrooms ─< students
         │
         └─< class_sessions >─ users (trainer_id)
                  │
                  ├─< student_attendances >─ students
                  └─< trainer_attendances >─ users (trainer_id)

schools ─< expo_reports >─ users (trainer_id)
```

---

## 3. Aturan Validasi Kondisional Kunci

Dua aturan dari PRD yang **tidak tertangkap oleh skema kolom saja** — harus
ditegakkan di Form Request/Policy, bukan cuma nullable di migration:

1. **`trainer_attendances`** (FR-6.3): kalau `class_sessions.mode = onsite` →
   `photo_path` dan (`latitude`,`longitude`) **wajib**. Kalau `mode = online` →
   `photo_path` (dipakai untuk screenshot) **wajib**, `latitude`/`longitude`
   **harus null**. Validasi ini butuh baca `class_session.mode` dulu — logisnya
   masuk ke Form Request lewat route model binding sebelum aturan diterapkan.
2. **`class_sessions.is_locked`**: begitu `true`, endpoint update
   `student_attendances` & `trainer_attendances` untuk sesi itu harus ditolak
   (422/409) — bukan cuma disembunyikan di UI (FR-5.3).

---

## 4. Otorisasi — Isolasi Trainer per Sekolah

Konsepnya sudah di `konvensi.md` §4.2 (Policy + query scope). Yang tabel ini
tambahkan: **daftar Policy konkret** yang perlu dibuat, dan **keputusan
desain** untuk menghindari duplikasi logika di 5 model berbeda.

| Policy | Model | Aturan inti |
|---|---|---|
| `SchoolPolicy` | School | Management: semua. Trainer: hanya sekolah di `trainer_school` (read-only, FR-2.3) |
| `StudentPolicy` | Student | Management: semua. Trainer: hanya murid di sekolah yang dipegang; boleh `create` (FR-3.4) |
| `ClassSessionPolicy` | ClassSession | Management: semua. Trainer: hanya sesi di sekolahnya; `update` ditolak kalau `is_locked` |
| `StudentAttendancePolicy` | StudentAttendance | Sama seperti ClassSessionPolicy, turunan dari `class_session.school_id` |
| `TrainerAttendancePolicy` | TrainerAttendance | Trainer hanya boleh check-in untuk dirinya sendiri di sesi sekolahnya |
| `ExpoReportPolicy` | ExpoReport | Management: semua. Trainer: hanya laporan yang ia buat / sekolah yang dipegang |

**Keputusan desain — logika isolasi dipakai berulang di 5 model, jadi ini
lolos ambang "≥2 pemakaian" di Custom Instructions untuk diekstrak.** Diusulkan
satu trait kecil:

```php
// app/Models/Concerns/BelongsToTrainerSchool.php
trait BelongsToTrainerSchool
{
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('Management')) {
            return $query;
        }

        return $query->whereHas('school', fn ($q) =>
            $q->whereIn('id', $user->schools()->select('schools.id'))
        );
    }
}
```

Dipakai di controller index (`Student::query()->visibleTo($request->user())`),
bukan cuma di Policy — supaya listing ikut ter-filter (lihat alasannya di
konvensi.md §4.2, Policy tidak menjaga listing). Model yang butuh relasi
`school()` langsung (School sendiri) atau tidak langsung (lewat
`class_session`) perlu sedikit penyesuaian implementasi trait ini, tapi
kontraknya (`scopeVisibleTo`) sama untuk semua.

**Catatan penting — `create` butuh mekanisme berbeda dari `view`/`update`/
`delete`.** Policy method seperti `view(User $user, Student $student)` bekerja
di atas record yang SUDAH ada — tidak berlaku untuk `create`, karena belum ada
`Student` untuk dicek. Untuk endpoint create yang menerima `school_id` dari
body request (mis. `POST /students`, FR-3.4 trainer boleh menambah murid),
validasi "apakah `school_id` ini benar-benar sekolah yang dipegang trainer
ybs" harus ditaruh di **Form Request** (custom rule atau `after()` hook),
bukan di Policy. Kalau lolos dari FormRequest ini, baru `StudentPolicy::create`
dicek (izin umum: apakah role ini boleh membuat Student sama sekali).

---

## 5. Peta Endpoint

Endpoint di bawah sudah terdaftar di `routes/api.php` (dikonfirmasi via
`route:list`) — tabel ini memetakannya ke FR yang relevan, bukan mengusulkan
baru.

| Endpoint | FR terkait | Otorisasi |
|---|---|---|
| `POST /login`, `/logout`, `/me`, `/forgot-password`, `/reset-password` | FR-1 | Publik (login, forgot, reset) / auth:sanctum (me, logout) |
| `CRUD /schools` | FR-2.1 | `SchoolPolicy` |
| `CRUD /classrooms` | FR-3.1 | Turunan `SchoolPolicy` (via school_id) |
| `CRUD /students`, `POST /students/import` | FR-3.2–3.4 | `StudentPolicy` |
| `CRUD /class-sessions`, aksi `lock`, `start`, `bulk` | FR-4, FR-5.3 | `ClassSessionPolicy` |
| `student-attendances` (dalam konteks sesi) | FR-5 | `StudentAttendancePolicy` |
| `trainer-attendances` | FR-6 | `TrainerAttendancePolicy` |
| `CRUD /expo-reports` | FR-7 | `ExpoReportPolicy` |
| `GET /exports/*` (excel/pdf, attendance & expo) | FR-8 | Sama dengan resource yang diekspor + FR-8.4 (trainer hanya datanya) |
| `GET /dashboard` | FR-9 | Payload beda per role, ditentukan di `DashboardController` |
| `CRUD /users` | Fondasi | `permission:manage users` |

Format response tiap endpoint ikut `konvensi.md` §3.2 — tidak diulang di sini.

---

## 6. Upload Foto/Screenshot Absensi Trainer

Alur teknis (melengkapi konvensi.md §5.3):

1. Klien kirim `multipart/form-data` ke endpoint trainer-attendance (bukan
   base64 di JSON — lebih berat & tidak perlu untuk file ≤5MB).
2. Form Request validasi kondisional sesuai §3 di atas.
3. Simpan via `Storage::disk('public')->putFile('trainer-attendance', $file)`
   dengan nama hasil `Str::uuid()` (konvensi.md §5.3) — path relatif yang
   dihasilkan disimpan ke `photo_path`.
4. API Resource mengembalikan URL lengkap (`Storage::url($this->photo_path)`),
   bukan path mentah — supaya frontend tidak perlu tahu struktur disk.
5. 🔸 **Belum diputuskan:** apakah foto perlu dikompres/resize di server
   (mis. via `intervention/image`) sebelum disimpan. Untuk MVP dengan volume
   rendah, kemungkinan tidak perlu — foto HP modern beberapa MB saja masih
   wajar untuk direct upload. Revisit kalau storage jadi masalah.

---

## 7. Export (NFR-5: rekap ratusan baris tidak boleh timeout)

- `maatwebsite/excel`: pakai `FromQuery` + `WithChunkReading` (bukan
  `FromCollection`) supaya query di-chunk, bukan ditarik semua ke memory
  sekaligus.
- `barryvdh/laravel-dompdf`: untuk PDF rekap kelas/sekolah — volume di MVP
  (satu sekolah, satu periode) kecil, generate sinkron saat request masih aman.
- **Queue untuk export besar (disebut opsional di PRD §7) TIDAK diperlukan di
  Fase 1** — volume data (ratusan baris per sekolah) masih di bawah ambang
  yang butuh background job. Revisit kalau export lintas-sekolah-semua-periode
  jadi kebutuhan nyata (kemungkinan besar itu baru muncul di Fase 2, laporan
  Finance/Management).

---

## 8. Kebutuhan Non-Fungsional — Implementasi

| NFR | Pendekatan teknis |
|---|---|
| NFR-1 Mobile-first | Tailwind responsive, wajib diuji di 360px (konvensi.md §6) |
| NFR-2 Tahan koneksi lambat | Payload JSON ringan, hindari eager load berlebihan (konvensi.md §5.4); offline penuh eksplisit di luar scope MVP (PRD §9 Fase 4) |
| NFR-3 Isolasi data | Policy + `scopeVisibleTo` (§4 dokumen ini) |
| NFR-4 Audit trail | ✅ **Diputuskan** (lihat §9) — dipindah ke backlog v2, tidak dibangun di Fase 1 |
| NFR-5 Export cepat | Chunked query, lihat §7 |
| NFR-6 Skalabel role baru | Sudah terpenuhi struktural — `spatie/laravel-permission` berarti role baru = seeder, bukan migration skema |

---

## 9. Resolved: NFR-4 (Audit Trail) vs Fitur-per-Modul

Ini **belum termasuk** 6 kontradiksi yang sudah diperbaiki di `prd-absensi.md`
v1.1 — baru ketemu saat menyusun `tdd.md`, karena butuh membaca dua dokumen
bersebelahan:

- `prd-absensi.md` §6 sempat mencantumkan **NFR-4 Audit trail** sebagai
  kebutuhan non-fungsional tanpa penanda fase — tersirat berlaku sejak MVP.
- `Fitur-per-Modul-After-Schola.md`, bagian Fondasi Platform, menandai **audit
  trail sebagai `[v2]`**.

**Keputusan:** ikut `Fitur-per-Modul-After-Schola.md` — audit trail masuk
backlog v2, **tidak dibangun di Fase 1**. NFR-4 sudah dihapus dari `prd-absensi.md`
(v1.2, lihat changelog di header dokumen itu). Konsekuensi teknis: tabel-tabel
Fase 1 (`student_attendances`, `trainer_attendances`, dst) **tidak perlu**
kolom pelacak perubahan (`updated_by`, log terpisah) untuk sekarang — cukup
`timestamps` bawaan Eloquent yang sudah ada di skema §2.

---

## 10. Checklist Belum Final

1. ✅ ~~NFR-4 Audit trail~~ — diputuskan v2 (§9).
2. ✅ ~~Skema `classrooms`~~ — dua kolom (`name`, `level`) dipertahankan (§2.2).
3. Kompresi/resize foto sebelum simpan — perlu atau tidak? (§6)
4. Batas ukuran & mime upload foto (konvensi.md §5.3, masih 🔸)
5. Strategi branch & aturan PR/review (konvensi.md §8, masih 🔸)

