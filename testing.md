# testing.md — After Schola Platform

> **Cakupan:** Fase 0 + Fase 1, mengikuti arsitektur di `tdd.md` dan aturan
> di `konvensi.md` §7. Dokumen ini merinci **skenario test konkret**,
> `konvensi.md` §7 cuma memuat prinsip umum — tidak diulang di sini.

---

## 1. Prinsip

- **Feature test (HTTP) adalah default.** Test lewat route asli
  (`$this->postJson('/api/schools', [...])`), bukan memanggil method
  controller/service langsung — supaya middleware, Policy, dan Form Request
  ikut teruji sebagai satu kesatuan.
- **Unit test hanya untuk logika murni yang rumit** — contoh nyata di proyek
  ini: kalkulasi ringkasan kehadiran (FR-5.5), bukan CRUD biasa.
- **Tidak menulis test untuk** getter/setter, accessor tanpa percabangan,
  atau validasi bawaan Laravel yang sudah pasti benar (satu test representatif
  per field cukup — jangan permutasi semua kombinasi "email harus format
  email", "password minimal 8 karakter", dst).

## 2. Setup — 🔸 Satu hal perlu dicek dulu

Semua contoh di dokumen ini ditulis gaya PHPUnit (class-based,
`Illuminate\Foundation\Testing\TestCase`) karena itu default Laravel. **Cek
`composer.json` bagian `require-dev`** — kalau ternyata `pestphp/pest`
terpasang, strukturnya sama persis, tinggal konversi sintaks (function-based,
`it('...', function () {...})`), tidak ada perubahan strategi.

Yang berlaku terlepas dari framework mana:
- `RefreshDatabase` trait wajib di semua Feature test.
- Factory dibuat untuk tiap model utama: `UserFactory` (sudah ada bawaan
  Laravel), `SchoolFactory`, `ClassroomFactory`, `StudentFactory`,
  `ClassSessionFactory`, `StudentAttendanceFactory`, `TrainerAttendanceFactory`,
  `ExpoReportFactory`. Buat yang belum ada sebelum menulis test yang butuh.
- `RoleSeeder` (sudah ada di repo, dikonfirmasi sebelumnya) dijalankan di
  `setUp()` test manapun yang butuh assign role — atau panggil langsung di
  test yang butuh.

## 3. Struktur Folder

```
tests/
├── Feature/Api/
│   ├── AuthTest.php
│   ├── SchoolTest.php
│   ├── StudentTest.php
│   ├── ClassSessionTest.php
│   ├── StudentAttendanceTest.php
│   ├── TrainerAttendanceTest.php
│   ├── ExpoReportTest.php
│   ├── ExportTest.php
│   └── DashboardTest.php
└── Unit/
    └── AttendanceSummaryTest.php   (kalkulasi FR-5.5, kalau logikanya cukup rumit untuk dipisah)
```

---

## 4. Aturan Wajib — Isolasi Trainer (non-negotiable, dari konvensi.md §7)

**Setiap endpoint yang menyentuh data bersekala-sekolah wajib punya minimal
satu test yang membuktikan trainer dari sekolah lain mendapat 404.** Daftar
konkretnya (turunan dari tabel Policy di `tdd.md` §4):

| Resource | Test wajib |
|---|---|
| Schools | Trainer akses `GET /schools/{id}` sekolah yang bukan miliknya → 404 |
| Students | Trainer akses/ubah murid di sekolah lain → 404 |
| ClassSessions | Trainer akses sesi sekolah lain → 404 |
| StudentAttendances | Trainer isi absensi murid di sesi sekolah lain → 404 |
| TrainerAttendances | Trainer check-in untuk sesi sekolah lain → 404 |
| ExpoReports | Trainer akses laporan ekspo sekolah lain → 404 |

Dan tambahan khusus (bukan soal sekolah lain, tapi mekanisme create dengan
foreign key — lihat `tdd.md` §4 catatan "create butuh mekanisme berbeda"):

- Trainer membuat murid dengan `school_id` yang **bukan** sekolahnya → 422
  (ditolak di Form Request, bukan 404 — karena ini bukan lookup resource yang
  sudah ada, tapi validasi input yang menunjuk ke sekolah yang tidak berhak)

---

## 5. Skenario per Modul

### 5.1 Auth (`AuthController` — sudah ada, test menyusul kode existing)

| Skenario | Ekspektasi |
|---|---|
| Login email+password benar | 200, `token` ada, `user` sesuai `UserResource` |
| Login email tidak terdaftar | 422, pesan **sama persis** dengan password salah |
| Login password salah | 422, pesan **sama persis** dengan email tidak terdaftar |
| Login akun `is_active = false` | 422, pesan "Akun dinonaktifkan..." |
| Login tanpa `device_name` | Tetap sukses, token dibuat dengan nama default `web` |
| `POST /logout` lalu pakai token lama | Request berikutnya → 401 |
| `GET /me` tanpa token | 401 |
| `GET /me` dengan token valid | 200, response **dibungkus `data`** (lihat konvensi.md §3.2) |
| `POST /forgot-password` email terdaftar | 200 |
| `POST /forgot-password` email tidak terdaftar | Laravel default tidak membocorkan keberadaan email — cocokkan dengan perilaku `Password::sendResetLink` apa adanya, jangan dipaksa selalu 200 |
| `POST /reset-password` token invalid/expired | 422 |

**Kenapa baris 2 dan 3 penting ditulis eksplisit sebagai satu test yang
membandingkan pesannya:** kode `AuthController::login()` sengaja memakai pesan
identik untuk email-tidak-ada maupun password-salah — ini mencegah
*user enumeration* (orang luar menebak-nebak email mana yang terdaftar lewat
pesan error yang beda). Ini properti keamanan yang gampang rusak tanpa sadar
kalau ada yang "memperbaiki" pesan error jadi lebih spesifik di kemudian hari.
Test-nya bukan cuma "keduanya 422", tapi assert pesannya **sama persis**.

### 5.2 Schools (FR-2)

- Management: CRUD penuh berhasil
- Trainer: `GET /schools` index hanya berisi sekolah di `trainer_school`-nya
- Trainer: `POST/PUT/DELETE /schools` → 403 (bukan soal isolasi, tapi memang
  tidak punya izin sama sekali)
- Trainer: `GET /schools/{id}` sekolah bukan miliknya → 404 (lihat §4)

### 5.3 Students (FR-3)

- Trainer boleh `POST /students` di sekolah yang dipegang (FR-3.4)
- Trainer `POST /students` dengan `school_id` sekolah lain → 422 (lihat §4)
- Import Excel format salah / kolom wajib kosong → 422 dengan error per baris
- Import Excel sukses → jumlah murid bertambah sesuai isi file

### 5.4 ClassSessions (FR-4, FR-5.3)

- Management create sesi berhasil
- Trainer `POST/PUT/DELETE /class-sessions` → 403 (read-only, FR-4.1)
- Trainer `GET` sesi sekolah lain → 404
- `POST /class-sessions/{id}/lock` → `is_locked` jadi `true`
- Setelah locked, `PUT` ke sesi itu → ditolak (kode status sesuai keputusan
  §3.2 `tdd.md` — cocokkan dengan implementasi aktual, dokumentasikan di sini
  setelah endpoint-nya ditulis)

### 5.5 StudentAttendances (FR-5)

- Tandai hadir/tidak per murid dalam satu sesi → tersimpan
- Murid yang sama, sesi yang sama, dikirim dua kali → entry kedua ditolak
  (unique constraint, `tdd.md` §2.2)
- Update setelah `class_session.is_locked = true` → ditolak (FR-5.3)
- Ringkasan jumlah hadir (FR-5.5) sesuai jumlah `is_present = true` yang
  sebenarnya tersimpan

### 5.6 TrainerAttendances (FR-6) — paling banyak skenario, baca `tdd.md` §3

| Skenario | Ekspektasi |
|---|---|
| `mode = onsite`, foto ada, GPS ada | 201 |
| `mode = onsite`, tanpa foto | 422 |
| `mode = onsite`, tanpa GPS (lat/long kosong) | 422 |
| `mode = online`, screenshot ada, tanpa GPS | 201 |
| `mode = online`, tanpa foto/screenshot | 422 |
| `mode = online`, tapi GPS **dikirim** juga | 🔸 **Belum diputuskan** — ditolak eksplisit (422) atau diam-diam diabaikan/di-null-kan di server? Putuskan salah satu sebelum endpoint ini ditulis, lalu test-nya menyesuaikan |
| Trainer check-in untuk sesi milik trainer lain | 403 (`TrainerAttendancePolicy`, bukan soal sekolah — ini soal identitas trainer di sesi itu) |
| Trainer check-in untuk sesi di sekolah lain | 404 |

### 5.7 ExpoReports (FR-7)

- CRUD dasar + isolasi sekolah (lihat §4)
- `rating` di luar rentang 1–5 → 422

### 5.8 Export (FR-8)

- Trainer export rekap → **file yang dihasilkan** cuma berisi baris dari
  sekolahnya (FR-8.4). Ini **tidak cukup dites lewat status HTTP** — perlu
  generate filenya di test, baca isinya (mis. pakai `maatwebsite/excel`
  `Excel::fake()` atau parse hasil PDF), dan assert baris sekolah lain memang
  tidak ada.
- Management export → semua sekolah muncul

### 5.9 Dashboard (FR-9)

- Payload berbeda per role — satu test per role yang memastikan struktur
  field yang dikembalikan sesuai role tsb (Trainer: sekolah dipegang & sesi
  hari ini; Management: total lintas sekolah)

---

## 6. Checklist Belum Final

1. Framework: PHPUnit atau Pest? (§2 — cek `composer.json`)
2. `mode = online` + GPS ikut terkirim: ditolak eksplisit atau diabaikan? (§5.6)
3. Kode status untuk update sesi yang sudah `is_locked` — 422 atau 409? (§5.4,
   terhubung ke keputusan yang sama di `tdd.md` §3)
