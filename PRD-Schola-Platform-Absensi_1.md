# PRD — Platform Operasional & Absensi Digital (After Schola)

> **Status:** Draft v1 (untuk didiskusikan)
> **Pemilik:** Sabil
> **Tanggal:** 2 September 2026
> **Ruang lingkup dokumen:** MVP + arah pengembangan berikutnya

---

## 1. Ringkasan Produk

### 1.1 Latar Belakang / Masalah
Saat ini absensi murid maupun trainer masih berbasis kertas. Ini menimbulkan masalah:
- Rekap manual, lambat, dan rawan hilang/rusak.
- Sulit dibagikan ke pihak sekolah atau manajemen.
- Tidak ada data terpusat untuk memantau kehadiran trainer (relevan untuk penggajian/evaluasi).
- Satu trainer menangani beberapa sekolah, sehingga rekap per-sekolah tersebar.

### 1.2 Solusi
Satu web app terpusat untuk **seluruh tim**, di mana:
- Platform bersifat **multi-modul**: tiap departemen (Management, Finance, HR, Trainer, Developer) punya modul & dashboard sendiri di bawah **satu login**. **Absensi adalah modul pertama & prioritas**; modul lain menyusul bertahap.
- Setiap user login dengan **role** masing-masing → **dashboard & fitur berbeda per role**.
- **Trainer** cukup login → pilih sekolah yang ia pegang → absen murid → (opsional) absen dirinya sendiri.
- Semua absensi tersimpan digital dan bisa **diekspor ke Excel & PDF** untuk dicetak, diunduh, atau dikirim.

### 1.3 Tujuan (Goals) MVP
| # | Tujuan | Metrik keberhasilan |
|---|--------|---------------------|
| G1 | Menghapus absensi kertas | 100% sesi absensi dilakukan lewat aplikasi |
| G2 | Trainer bisa absen murah & cepat di lapangan | ≤ 30 detik untuk absen 1 kelas dari HP |
| G3 | Rekap bisa diekspor | Export Excel & PDF berfungsi per kelas/sekolah/periode |
| G4 | Satu platform, banyak role | Minimal 3 role dengan dashboard berbeda berjalan |
| G5 | Data absensi trainer terekam | Kehadiran trainer tercatat lengkap dengan waktu |

### 1.4 Bukan Tujuan (Non-Goals) untuk MVP
- Fitur manajemen proyek (task board, gantt, kanban) — **di luar scope**.
- Penggajian otomatis / integrasi payroll (data absensi trainer disiapkan, tapi kalkulasi gaji belum).
- Aplikasi mobile native (web responsif dulu; native menyusul jika perlu).
- Login untuk murid / orang tua.
- Pembayaran/billing.

---

## 2. Pengguna & Peran (Roles)

Platform adalah **payung untuk semua tim Schola** — tiap departemen punya modul & dashboard sendiri. Ada **5 role**:

| Role | Departemen | Modul utama (usulan — **perlu dikonfirmasi**) |
|------|-----------|---------|
| **Management** | Manajemen | Oversight semua modul, KPI, kelola user & role (peran admin tertinggi) |
| **Finance** | Keuangan | Payroll (berbasis absensi trainer), invoice ke sekolah, pemasukan/pengeluaran |
| **HR** | SDM | Data karyawan & trainer, rekrutmen, cuti/izin, basis kehadiran untuk payroll |
| **Trainer** | Operasional lapangan | Absensi murid & absensi diri, per sekolah yang dipegang |
| **Developer** | Tim Teknis | **Manajemen proyek**: beberapa team dev, tiap team punya dashboard task, progres task, & proyek yang sedang berjalan |

> Isi kolom "Modul utama" masih **usulan saya**. Tolong konfirmasi/koreksi tugas tiap role di Open Questions.

---

## 3. Matriks Akses per Modul (usulan)

Level tinggi — akses **modul** per role. Permission detail di dalam tiap modul disusun saat modul itu dikerjakan.

| Modul | Management | Finance | HR | Trainer | Developer |
|-------|:-:|:-:|:-:|:-:|:-:|
| Absensi (murid + trainer) | lihat semua | lihat (payroll) | lihat (payroll) | **input** (sekolahnya) | ❌ |
| Keuangan / Payroll | lihat | **kelola** | ➖ | lihat slip sendiri | ❌ |
| SDM / HR (karyawan, cuti) | lihat | ➖ | **kelola** | ajukan cuti/izin | ajukan cuti/izin |
| Manajemen Proyek | lihat | ❌ | ❌ | ❌ | **kelola** |
| Kelola user & role | **kelola** | ❌ | ➖ | ❌ | ❌ |

Legend: **kelola** = CRUD penuh · lihat = read-only · ➖ terbatas/kondisional · ❌ tidak ada akses

**Detail permission modul Absensi (MVP):**

| Kapabilitas | Management | Trainer |
|-------------|:-:|:-:|
| Kelola sekolah & kelas | ✅ | ➖ (lihat) |
| Tambah / kelola murid | ✅ | ✅ (tambah, di sekolah yg dipegang) |
| Assign trainer ↔ sekolah | ✅ | ❌ |
| Absen murid | ✅ | ✅ (hanya sekolah yg dipegang) |
| Absen diri sendiri | ➖ | ✅ |
| Lihat semua absensi | ✅ | ❌ (hanya sekolahnya) |
| Export Excel/PDF | ✅ (semua) | ➖ (hanya kelasnya) |

**Aturan isolasi data penting:** Trainer **hanya** boleh mengakses murid, kelas, dan absensi dari sekolah yang ditugaskan padanya (relasi `trainer_school`). Divalidasi di backend (policy/gate), bukan sekadar disembunyikan di UI.

---

## 4. Alur Utama (User Flows)

### 4.1 Alur Trainer (paling penting — dipakai tiap hari)
1. Trainer buka web (di HP) → **login**.
2. Sistem deteksi role = Trainer → tampilkan **dashboard trainer**.
3. Dashboard menampilkan **daftar sekolah yang ia pegang** (dari `trainer_school`).
4. Trainer **pilih sekolah** → muncul daftar **kelas** di sekolah itu.
5. Pilih kelas + tanggal sesi → muncul **daftar murid**.
6. Tandai status tiap murid: **Hadir / Izin / Sakit / Alpa**.
7. (Opsional) Trainer **absen dirinya sendiri** untuk sesi tersebut (check-in, jam otomatis).
8. Simpan → data tercatat, bisa langsung **export PDF/Excel** rekap kelas hari itu.

### 4.2 Alur Admin/Koordinator
1. Login → dashboard admin (statistik agregat).
2. Kelola: data sekolah, murid & kelas, tugaskan trainer ke sekolah, atur jadwal sesi.
3. Pantau kehadiran murid & trainer lintas sekolah.
4. Export rekap per sekolah / per periode untuk dikirim ke klien sekolah.

### 4.3 Alur Login & Routing per Role
- Satu halaman login untuk semua.
- Setelah autentikasi, sistem membaca role → redirect ke dashboard yang sesuai → menu & fitur dirender kondisional berdasarkan permission.

---

## 5. Kebutuhan Fungsional (Functional Requirements)

### FR-1 Autentikasi & Otorisasi
- FR-1.1 Login email/password (dukungan reset password).
- FR-1.2 Role-based access control (RBAC) — 3 role di MVP.
- FR-1.3 Dashboard & menu dirender kondisional per role.
- FR-1.4 Backend memvalidasi setiap akses data ke policy (bukan hanya UI).

### FR-2 Manajemen Sekolah & Penugasan Trainer
- FR-2.1 CRUD sekolah (nama, alamat, PIC, kontak).
- FR-2.2 Assign satu trainer ke banyak sekolah, dan satu sekolah bisa punya banyak trainer (many-to-many).
- FR-2.3 Trainer hanya melihat sekolah yang ditugaskan.

### FR-3 Manajemen Murid & Kelas
- FR-3.1 "Kelas" di sistem = **level** (Beginner / Intermediate) per sekolah — bukan kelas sekolah. Dalam satu sesi, siswa dari berbagai kelas asal **bercampur**, dikelompokkan per level.
- FR-3.2 CRUD murid: nama, **kelas asal di sekolah** (mis. `8A`, `7 Ibnu Sina` — sesuai kolom KELAS di spreadsheet), level, sekolah.
- FR-3.3 Import murid via Excel (agar cepat saat onboarding sekolah baru).
- FR-3.4 **Trainer boleh menambah murid** pada sekolah/level yang ia pegang — karena sifatnya eskul, murid bisa bergabung saat program sudah berjalan.

### FR-4 Jadwal (dikelola Admin/Management)
- FR-4.1 **Admin/Management** yang membuat jadwal; **trainer hanya melihat & menjalankan** (read-only di dashboard/kalendernya).
- FR-4.2 Sesi/pertemuan: sekolah + kelas + trainer + tanggal + jam + **mode (onsite/online)**.
- FR-4.3 Dukung sesi berulang (mis. tiap Senin) — boleh dijadikan fase 2 jika mepet.
- FR-4.4 **Jadwal ngajar** = sesi per level (Beginner/Intermediate). **Ekspo/Free-Trial** ditangani sebagai **form laporan** terpisah (lihat FR-7), bukan sesi absensi murid.

### FR-5 Absensi Murid
- FR-5.1 Absensi **per pertemuan** (tiap pertemuan punya tanggal). Status **Hadir / Tidak Hadir** (boolean `is_present`) — sama persis dengan spreadsheet.
- FR-5.2 UI cukup centang hadir per murid; default belum-hadir.
- FR-5.3 Bisa edit selama pertemuan belum "dikunci".
- FR-5.4 Catatan opsional per murid.
- FR-5.5 Ringkasan otomatis: jumlah hadir per pertemuan (seperti baris "JUMLAH SISWA YANG HADIR" di spreadsheet).

### FR-6 Absensi Trainer
- FR-6.1 Trainer check-in **per sesi ngajar**, jam tercatat otomatis. (Menggantikan Google Form yang dipakai sekarang.)
- FR-6.2 Status: Hadir / Terlambat / Tidak Hadir.
- FR-6.3 **Bukti kehadiran (kondisional per mode sesi):**
  - Sesi **onsite** → **foto wajib** (seperti sekarang) + **GPS** (lat/long saat check-in).
  - Sesi **online** → tanpa GPS; **cukup screenshot** (disimpan di kolom foto yang sama). Timestamp tetap tercatat.
- FR-6.4 Foto+GPS **masuk MVP** (revisi dari rekomendasi awal saya), karena ini pain point utama yang mau didigitalkan dari Google Form.

### FR-7 Laporan Ekspo / Free-Trial
Menggantikan Google Form yang dipakai sekarang (form terpisah, per acara ekspo):
- FR-7.1 Trainer mengisi laporan ekspo: tanggal pelaksanaan, sekolah, nama tim/pengisi, rating pelaksanaan (1–5), apakah sesuai jadwal (Ya/Sebagian/Tidak), antusiasme siswa, ada kendala? + penjelasan, link/upload dokumentasi.
- FR-7.2 Admin/Management bisa melihat & merekap semua laporan ekspo.
- FR-7.3 Export laporan ekspo (Excel/PDF).

### FR-7 Export Excel & PDF
- FR-7.1 Export rekap absensi murid (per kelas / per sekolah / per periode) ke **Excel**.
- FR-7.2 Export rekap ke **PDF** yang rapi & siap cetak (kop, nama sekolah, periode, tanda tangan).
- FR-7.3 Export rekap kehadiran trainer ke Excel/PDF.
- FR-7.4 Trainer hanya bisa export data kelas/sekolah miliknya.

### FR-8 Dashboard per Role
- FR-8.1 **Trainer:** sekolah yang dipegang, **jadwal ngajar & jadwal ekspo**, sesi hari ini, ringkas kehadiran kelasnya.
- FR-8.2 **Management:** total sekolah/murid/trainer, kehadiran hari ini lintas sekolah, trainer yang belum absen, grafik tren, + kelola user.

### FR-9 Modul Manajemen Proyek (Developer)
Untuk tim teknis. Terdapat **beberapa team dev berbeda**; tiap team punya proyek & task sendiri.
- FR-9.1 Kelola **team dev** (beberapa team, tiap developer tergabung di satu/lebih team).
- FR-9.2 Kelola **proyek** per team (nama, deskripsi, status: perencanaan/berjalan/selesai, deadline).
- FR-9.3 Kelola **task** per proyek (judul, penanggung jawab/assignee, status, progres).
- FR-9.4 **Board task** gaya kanban: To Do → In Progress → Review → Done (drag/pindah kolom).
- FR-9.5 **Progres task**: status + persentase progres (atau checklist sub-task).
- FR-9.6 **Dashboard team dev**: proyek yang sedang berjalan, ringkas progres task, siapa mengerjakan apa.
- FR-9.7 Developer melihat board **team-nya**; Management melihat **semua team** (oversight).

---

## 6. Kebutuhan Non-Fungsional

| Kode | Kebutuhan | Alasan |
|------|-----------|--------|
| NFR-1 | **Mobile-first / responsif** | Trainer absen dari HP di dalam kelas. Ini wajib, bukan tambahan. |
| NFR-2 | Tahan koneksi lambat / tidak stabil | Banyak sekolah wifi-nya jelek. Minimal: form ringan, hindari reload berat. (Offline penuh = fase lanjut.) |
| NFR-3 | Isolasi data per sekolah | Trainer tak boleh lihat data sekolah lain. |
| NFR-4 | Audit trail | Siapa mengubah absensi & kapan (penting kalau ada sengketa dengan sekolah). |
| NFR-5 | Export cepat | Rekap ratusan baris tidak boleh timeout. |
| NFR-6 | Skalabel menambah role baru | Struktur RBAC harus fleksibel untuk role masa depan. |

---

## 7. Arsitektur Teknis (Usulan)

Disesuaikan dengan stack kamu (Laravel/PHP):

- **Backend & App:** Laravel 11
- **RBAC:** `spatie/laravel-permission` (5 role)
- **Auth API:** Laravel **Sanctum** (token). Login → dapat token → tiap request bawa `Authorization: Bearer <token>`.
- **Backend:** Laravel 11 sebagai **API murni** — endpoint di `routes/api.php`, respons JSON via API Resource. **Keputusan: API-first**, karena ke depan ada mobile app; web sekarang jadi konsumen pertama API yang sama, mobile menyusul tanpa nulis ulang logika.
- **Frontend Web (SPA):** Vue 3 + Vite (alternatif: React/Next) yang konsumsi API. Router menampilkan dashboard & menu per role.
  - Alasan pilih SPA murni (bukan Blade/Livewire/Inertia): API harus bisa dipakai ulang mobile app; Livewire/Inertia terkopel ke web.
- **Mobile (nanti):** Flutter / React Native konsumsi API yang sama.
- **Export Excel:** `maatwebsite/excel` (endpoint yang balikin file).
- **Export PDF:** `barryvdh/laravel-dompdf` (ringan, siap cetak). Layout kompleks → `spatie/browsershot`.
- **Database:** MySQL 8 (atau PostgreSQL).
- **Queue (opsional):** untuk export besar dijalankan di background.

**Konsekuensi API-first:** semua validasi & otorisasi (policy) ada di backend; frontend murni UI. Ini justru menguatkan isolasi data trainer per sekolah (NFR-3) karena tak bisa di-bypass dari sisi klien.

---

## 8. Model Data / Skema Database (Usulan)

Relasi inti:
- `users` —(role via spatie)
- `users (trainer)` ⇄ `schools` : many-to-many lewat `trainer_school`
- `schools` → `classrooms` (level: Beginner/Intermediate) → `students`
- `class_sessions` (pertemuan) menghubungkan sekolah + level + trainer + tanggal
- `student_attendances` : per murid per pertemuan
- `trainer_attendances` : per trainer per pertemuan
- `expo_reports` : laporan ekspo/free-trial per acara (terpisah dari absensi)

> **Catatan penting:** tabel dinamai `class_sessions`, **bukan** `sessions`, karena `sessions` bentrok dengan tabel bawaan Laravel (session driver database).

| Tabel | Kolom utama | Keterangan |
|-------|-------------|------------|
| `users` | id, name, email, password, is_active | Semua user; role dikelola spatie |
| `schools` | id, name, address, pic_name, pic_phone | Data sekolah |
| `trainer_school` | id, user_id (trainer), school_id | Pivot penugasan trainer↔sekolah |
| `classrooms` | id, school_id, name (mis. "Beginner"/"Intermediate"), level | **Level** per sekolah — bukan kelas sekolah. Murid berbagai kelas asal bercampur di sini |
| `students` | id, school_id, classroom_id (level), name, origin_class (kelas asal, mis. "8A"), created_by (trainer/admin) | Murid (trainer boleh menambah) |
| `class_sessions` | id, school_id, classroom_id (level), trainer_id, mode (enum: onsite/online), date, meeting_no, is_locked, note | Satu pertemuan. `meeting_no` = kolom 1..6 di spreadsheet |
| `student_attendances` | id, class_session_id, student_id, is_present (boolean: Hadir/Tidak), note | Absensi murid, per pertemuan (sesuai TRUE/FALSE spreadsheet) |
| `trainer_attendances` | id, class_session_id, trainer_id, check_in_at, status (enum: hadir/terlambat/tidak_hadir), photo_path (foto onsite / screenshot online), latitude, longitude (null utk online), note | Absensi trainer |
| `expo_reports` | id, school_id, trainer_id, date, team_name, rating (1–5), on_schedule (enum: ya/sebagian/tidak), enthusiasm, has_issue (bool), issue_note, doc_url | Laporan ekspo/free-trial (ganti Google Form) |

> Migration + Model Eloquent lengkap (dengan relasi, enum, dan foreign key) bisa saya generate sebagai langkah berikutnya — tinggal bilang.

### 8.1 Skema Modul Manajemen Proyek (Developer) — Fase 3

| Tabel | Kolom utama | Keterangan |
|-------|-------------|------------|
| `teams` | id, name, description | Team dev (bisa banyak team berbeda) |
| `team_user` | id, team_id, user_id, role_in_team (enum: member/lead) | Pivot anggota team; ada **team lead**. Untuk sekarang 1 developer = 1 team; pivot tetap dipakai agar mudah diperluas ke multi-team nanti |
| `projects` | id, team_id, name, description, status (enum: planning/ongoing/done), start_date, due_date | Proyek milik satu team |
| `tasks` | id, project_id, assignee_id (user), title, description, status (enum: todo/in_progress/review/done), progress (int 0–100), due_date | Task dalam proyek |
| `task_updates` | id, task_id, user_id, note, created_at | (Opsional) log progres/komentar task |

Relasi: `teams` → `projects` → `tasks`; `users` ⇄ `teams` via `team_user`; `tasks.assignee_id` → `users`.

---

## 9. Rencana Rilis (Fase)

> **Rekomendasi kuat saya:** JANGAN bangun 5 modul sekaligus — proyek bakal membengkak dan tak pernah rilis. Bangun **shell platform dulu** (yang menampung semua role), lalu **satu modul penuh** (Absensi), baru modul berikutnya bertahap. Tiap modul rilis = nilai nyata langsung dipakai.

### Fase 0 — Platform Shell (fondasi)
- Auth + **RBAC 5 role** (Management, Finance, HR, Trainer, Developer)
- Routing & layout **dashboard per role**
- Struktur kode **modular** (tiap modul bisa ditambah tanpa bongkar yang lain)
- Manajemen user (oleh Management)

### Fase 1 — Modul Absensi (PRIORITAS, ini "MVP" nyata)
- CRUD sekolah, kelas, murid (+ import murid via Excel)
- Penugasan trainer ↔ sekolah
- Absensi murid (H/I/S/A) + absensi trainer (check-in)
- Export Excel & PDF rekap kelas/sekolah
- Dashboard trainer & management (versi dasar)

### Fase 2 — Modul HR + Finance
- HR: data karyawan/trainer, cuti/izin
- Finance: payroll berbasis data absensi trainer, invoice ke sekolah

### Fase 3 — Modul Manajemen Proyek (Developer)
- Task/project board untuk tim teknis

### Fase 4 — Penyempurnaan
- Jadwal sesi berulang, bukti kehadiran (foto/GPS), notifikasi
- Analitik & grafik tren lintas modul
- Mode offline / PWA, aplikasi mobile native (jika perlu)

---

## 10. Asumsi & Open Questions

### Sudah dikonfirmasi ✅
1. **Role:** Management, Finance, HR, Trainer, Developer.
2. **Developer = manajemen proyek**: 1 dev = 1 team, ada team lead, progres task = persen (0–100) + kanban.
3. **Modul pertama:** Absensi.
4. **Arsitektur:** API-first (Laravel API + Sanctum), web dulu, mobile menyusul.
5. **Trainer bisa menambah murid** (sifat eskul).
6. **Nama lembaga:** After Schola.
7. **Struktur data absensi (dari spreadsheet asli):** 1 sheet = 1 sekolah; kolom NAMA + KELAS (kelas asal siswa) + pertemuan 1..6 (tiap kolom ada tanggal). "Kelas" sistem = **level (Beginner/Intermediate)**.
8. **Jadwal** diatur Admin/Management; trainer read-only.
9. **Sesi ada mode onsite & online.** Trainer: onsite = foto + GPS; **online = screenshot** (tanpa GPS).
10. **Ekspo = form laporan** (bukan absensi) — ganti Google Form. Field sesuai form saat ini.
11. **Murid tidak login** — hanya trainer.
12. **Single-organisasi** — khusus After Schola, bukan multi-tenant.

13. **Status absensi murid:** **Hadir / Tidak (boolean)** — ikut spreadsheet. ✅

### Semua open question sudah tertutup. Siap lanjut build.

---

## 11. Langkah Berikutnya (usulan)
1. Kamu jawab Open Questions di atas.
2. Saya kunci daftar role & permission final.
3. Saya generate **migration + model Eloquent** sesuai skema Bab 8 (kode siap pakai).
4. Lanjut ke scaffolding RBAC + routing dashboard per role.
