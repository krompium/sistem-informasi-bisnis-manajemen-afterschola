# After Schola — Demo dan Skenario Uji per Modul (jalur reguler)

Basis: `main` commit `83afaa2` (12 Nov 2026). Dokumen ini menggantikan dokumen UAT 5 Nov, yang memakai akun `@afterschola.id`, nama anak, dan seeder lama.

## 1. Status verifikasi

Tanda di kolom Status skenario:

- **✔ API** = dijalankan di sandbox lewat HTTP asli terhadap data seed di bawah, hasilnya sama dengan kolom "Hasil".
- **Test** = dijaga test otomatis (nama test disebut), tidak dijalankan ulang manual.
- **UI** = langkah layar mengikuti path router. **Belum dibuka di browser.** Coba dengan tangan.

Hasil yang sudah terbukti pada `main` 83afaa2 (PHP 8.4, SQLite):

- `php artisan test`: 634 lulus.
- Frontend: `npm test` 12 lulus, `npm run build` berhasil.
- `migrate:fresh` lalu `db:seed --class=RegularDemoSeeder` berhasil di database kosong. 22 akun terbentuk.
- Lebih dari 60 pemeriksaan HTTP (skenario di bagian 4) lulus. Satu temuan perilaku ada di 5.1.

Belum terbukti: tampilan Vue di browser, dan jalannya di MySQL 8 (test memakai SQLite).

## 2. Menyiapkan lingkungan

Prasyarat: PHP 8.4 dengan ekstensi `zip`, Composer, Node, MySQL 8 (Laragon). `composer.json` masih menulis `^8.3`; abaikan, dependensi butuh 8.4.

```
# 1. Simpan pekerjaan lokal dulu
git status
git stash -u            # atau commit ke branch sendiri

# 2. Ambil kode terbaru
git fetch origin
git checkout main
git pull origin main

# 3. Backend
cd after-schola
composer install
cp .env.example .env    # lewati jika .env sudah ada
php artisan key:generate
# buat database kosong di MySQL, isi DB_* di .env
php artisan migrate:fresh
php artisan db:seed --class=RegularDemoSeeder
php artisan storage:link
php artisan serve

# 4. Frontend (terminal lain)
cd after-schola/frontend
npm install
npm run dev
```

Catatan:

- `migrate:fresh` menghapus semua data database itu. Pakai database khusus demo.
- Pakai `db:seed --class=RegularDemoSeeder` saja, **bukan** `migrate:fresh --seed`. `--seed` menambah data lama (cabang "Cabang Utama", akun `@afterschola.id`, sekolah contoh) yang mengotori filter cabang dan dashboard.
- Seeder membuat tanggal relatif terhadap hari ini. **Seed ulang di hari demo.** Sesi "hari ini" berjam 06.00–21.00 supaya tidak terkunci jam.
- Seeder aman diulang: bila `admin@demo.id` sudah ada ia dilewati. Reset penuh = `migrate:fresh` lalu seeder lagi.
- Angka dashboard di dokumen ini dihitung untuk periode awal bulan sampai hari ini (diverifikasi 12 Nov). Di tanggal lain angka funnel bisa bergeser.

Opsi untuk mendemokan piutang jatuh tempo (seed tidak membuat invoice overdue; sudah diuji):

```
php artisan tinker --execute='App\Models\Invoice::where("number","INV/BDG/202611/0001")->update(["due_date"=>now()->subDays(5)->toDateString()]);'
php artisan invoices:send-reminders    # mengirim pengingat ke ortu invoice overdue
```

Nomor invoice mengikuti bulan seed. Bila bukan November 2026, lihat nomor sebenarnya di `/management/invoice`.

## 3. Akun dan data demo

Semua password: `password`.

| Email | Peran | Cakupan | Dipakai untuk |
| --- | --- | --- | --- |
| admin@demo.id | Management | Semua cabang, semua permission | Semua layar Management dan layar Keuangan (menu Keuangan ada di sini) |
| pendaftaran@demo.id | Management | Bandung; slot trial, leads, verifikasi | Uji isolasi cabang dan permission sempit |
| koordinator@demo.id | Management | Bandung; kelas, penugasan, jadwal, tinjau raport, dashboard, absensi | Penempatan, jadwal, raport |
| pimpinan@demo.id | Management | Semua cabang; dashboard, absensi, laporan keuangan | KPI dan laporan |
| keuangan@demo.id | Keuangan | Bandung dan Jakarta | **Hanya API** (lihat 5.2) |
| trainer1@demo.id | Trainer (Rina) | Bandung | Utama kelas Coding Kids A |
| trainer2@demo.id | Trainer (Budi) | Bandung | Asisten Coding Kids A |
| trainer4@demo.id | Trainer (Sari) | Bandung | Pengganti sesi minggu ke-3 |
| trainer3@demo.id | Trainer (Dewi) | Jakarta | Uji isolasi trainer |
| ortu1 … ortu13 @demo.id | Orangtua | Anak sendiri | Tabel di bawah |

| Ortu | Anak | Cabang | Tahap | Dipakai di |
| --- | --- | --- | --- | --- |
| ortu1 | Andi | BDG | Trial terjadwal (slot 3 hari lagi) | M2 |
| ortu2 | Bima | BDG | Trial dievaluasi, menunggu keputusan | M4 |
| ortu3 | Citra | BDG | Sudah memutuskan lanjut, belum mendaftar | M4 |
| ortu4 | Dian | BDG | Pendaftaran Menunggu Verifikasi | M5 |
| ortu5 | Eka | BDG | Perlu Revisi (ada catatan) | M5 |
| ortu6 | Fajar | BDG | Terverifikasi, invoice unpaid (jatuh tempo 7 hari), INV/BDG/…/0001 | M5 |
| ortu7 | Gita | BDG | Bukti bayar menunggu konfirmasi, INV/BDG/…/0002 | M5, M6 |
| ortu8 | Hari | BDG | Lunas, belum ditempatkan | M6 |
| ortu9 | Kiki | BDG | Memutuskan tidak lanjut (leads) | M4 |
| ortu10 | Hana | BDG | Aktif; raport **terkirim** | M9 |
| ortu11 | Ilham | BDG | Aktif; raport **ditinjau** (antrian koordinator) | M9 |
| ortu12 | Joko | BDG | Aktif; raport **draft**; tidak hadir pertemuan 2 | M7, M9 |
| ortu13 | Lina | JKT | Aktif (cabang lain, untuk isolasi) | M0 |

Data pendukung: dua cabang (BDG, JKT); program Coding Kids (SD, Rp500.000, level Dasar dan Menengah) dan Robotik Junior (SMP, Rp650.000, level Dasar); rubrik Logika, Kreativitas, Kolaborasi (maks 10); kelas Coding Kids A dan B (kapasitas 8) dan Robotik Junior A (6); tiga sesi lalu terkunci dengan absensi, check-in trainer, dan catatan progres; satu sesi hari ini belum di-check-in.

**Angka yang diharapkan pada seed baru** (periode awal bulan–hari ini): siswa aktif 4, income Rp2.650.000, okupansi 18,2% (4 dari kapasitas 22), retensi kosong (belum ada enrollment berakhir). Funnel: calon 13, trial hadir 12, lanjut 10, terverifikasi 7, lunas 5, ditempatkan 1. Panel: sesi hari ini 1, hadir hari ini 0, pendaftaran menunggu 1, pembayaran menunggu 1, invoice overdue 0. Piutang: 2 invoice belum jatuh tempo, total Rp1.000.000.

## 4. Skenario uji per modul

Jalankan berurutan dari seed baru bila skenario memakai data yang berubah (mis. Gita di M5 lalu M6). Reset dengan `migrate:fresh` + seeder.

### Modul 0 — Fondasi (auth, cabang, permission, user)

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M0-01 | Login dan arah per peran | Login admin, trainer1, ortu1 | Admin ke `/management`, trainer ke `/trainer`, ortu ke `/portal` | UI |
| M0-02 | Pembatasan percobaan login | Login `ortu3@demo.id` dengan password salah 6 kali dalam semenit | 5 kali pertama 422, kali ke-6 429. Email lain dari IP yang sama tetap bisa login | ✔ API |
| M0-03 | Isolasi cabang staf | Login pendaftaran@, buka `/management/verifikasi-pendaftaran` | Hanya anak Bandung. Lina (JKT) tidak tampil; menyetujui registrasi Lina lewat API: 404 | ✔ API |
| M0-04 | Isolasi cabang koordinator | koordinator@ buka kelas Robotik Junior A (JKT) | 404; daftar kelas hanya kelas Bandung | ✔ API |
| M0-05 | Permission fungsi | trainer1 memanggil KPI dashboard dan membuat program; pimpinan memanggil KPI | Trainer 403, pimpinan 200 | ✔ API |
| M0-06 | Manajemen user | `/management/manajemen-user` → tambah staf, pilih cabang | User terbentuk (201). Trainer memanggil daftar user: 403 | ✔ API |
| M0-07 | Cabang | `/management/cabang` | BDG dan JKT tampil | ✔ API (daftar) |
| M0-08 | Hot issue | `/management/hot-issue` → buat pengumuman aktif; login ulang | Dibuat (201); popup tampil setelah login dan bisa disembunyikan | ✔ API (buat), UI (popup) |

### Modul 1 — Program dan Promosi

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M1-01 | Halaman publik | Tanpa login buka `/`, `/program`, `/program/:id` | Program aktif tampil dengan level dan harga | ✔ API, UI |
| M1-02 | Program, level, rubrik | `/management/program` → tambah program, level, kriteria rubrik | Semua tersimpan (201) | ✔ API |
| M1-03 | Nonaktifkan program | Ubah `is_active` jadi tidak aktif | Program hilang dari halaman publik, data tetap ada | ✔ API |
| M1-04 | Hak akses | trainer1 mencoba membuat program | 403 | ✔ API |
| M1-05 | Pengaturan pembayaran | `/management/pengaturan-pembayaran` → ubah jatuh tempo, interval reminder, rekening | Tersimpan dan terbaca kembali | ✔ API (simpan) |

### Modul 2 — Trial dan Leads

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M2-01 | Daftar trial lewat form publik | Buka `/daftar-trial`, pilih slot, isi data dengan email baru, centang persetujuan | 201. Akun Orangtua dibuat; email atur password dikirim (mailer `log`: lihat `storage/logs/laravel.log`). Akun baru belum bisa login sebelum atur password | ✔ API |
| M2-02 | Persetujuan data wajib | Kirim form tanpa persetujuan | 422 | Test: TrialRegistrationTest |
| M2-03 | Slot penuh | Penuhi kapasitas slot lalu daftar lagi | 422 berpesan Bahasa Indonesia | Test: TrialRegistrationTest |
| M2-04 | Email sudah terdaftar | Daftar dengan email ortu yang ada | Ditolak 422 dengan arahan untuk login; tidak ada akun atau calon yang dibuat | Test: TrialRegistrationTest |
| M2-05 | Slot trial | `/management/slot-trial` → buat slot (cabang, program, trainer, waktu, kapasitas) | Slot tampil di `/daftar-trial` | UI; Test: TrialSlotTest |
| M2-06 | Calon dan leads | `/management/calon` | Calon dan leads per cabang dengan filter; export Excel | ✔ API (daftar, 13 calon), Test: LeadManagementTest, ExportTest |

### Modul 3 — Evaluasi Trial

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M3-01 | Trainer melihat peserta slotnya | trainer1 → `/trainer/evaluasi-trial` | Hanya peserta slot miliknya | ✔ API |
| M3-02 | Isolasi evaluasi | trainer3 (JKT) membuka evaluasi anak Bandung | 404 | ✔ API |
| M3-03 | Isi evaluasi dan rekomendasi level | Pilih peserta, isi skor, kekuatan, rekomendasi level (level program) | Tersimpan; ortu melihat hasil di portal | UI; Test: TrialEvaluationTest |

### Modul 4 — Keputusan dan Pendaftaran

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M4-01 | Ortu melihat hasil trial | ortu2 → `/portal` → Bima | Skor, rekomendasi level, penawaran program | ✔ API |
| M4-02 | Keputusan Lanjut | ortu2 pilih Lanjut | Tersimpan; tombol pendaftaran muncul. Bisa diubah selama belum terdaftar | ✔ API |
| M4-03 | Tidak lanjut menjadi leads | Lihat Kiki (ortu9) di `/management/calon` | Kiki status leads, data tidak terhapus | ✔ API (status data) |
| M4-04 | Auto-fill pendaftaran | ortu2 → Bima → Pendaftaran | Nama anak, sekolah, program, data ortu terisi otomatis | ✔ API |
| M4-05 | Kirim pendaftaran | Kirim form kosong, lalu isi tanggal lahir dan alamat, kirim | Form kosong 422; setelah diisi status Menunggu Verifikasi | ✔ API |
| M4-06 | Isolasi ortu | ortu1 membuka atau memutuskan untuk anak ortu2 | 404 | ✔ API |

Catatan: staf juga bisa mencatat keputusan di `/management/keputusan-pendaftaran` (keputusan HANDOFF #3). Pilihan `pikir_pikir` hanya ada di sisi staf.

### Modul 5 — Verifikasi, Pembayaran, Piutang

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M5-01 | Minta revisi | admin → `/management/verifikasi-pendaftaran` → Dian → Minta Revisi tanpa catatan, lalu dengan catatan | Tanpa catatan 422. Dengan catatan: status Perlu Revisi, ortu diberi notifikasi | ✔ API |
| M5-02 | Setujui dan invoice otomatis | Setujui pendaftaran Bima (setelah M4-05) | Tepat satu invoice unpaid, nominal Rp500.000, nomor `INV/BDG/YYYYMM/urut`, jatuh tempo 7 hari | ✔ API |
| M5-03 | Bukti bayar | ortu6 → `/portal/tagihan` → unggah bukti. Coba file PDF | PNG/JPG diterima (status menunggu); PDF ditolak 422. Ortu lain mengunggah ke invoice ortu6: 404 | ✔ API |
| M5-04 | Tolak dan konfirmasi | admin → `/management/invoice` → invoice Gita → Tolak tanpa alasan, lalu Konfirmasi | Tolak tanpa alasan 422. Konfirmasi: payment confirmed, invoice Lunas | ✔ API |
| M5-05 | Kuitansi | Unduh kuitansi invoice Gita (admin dan ortu7) | PDF terunduh. Ortu6 mengunduh kuitansi ortu7: 404 | ✔ API |
| M5-06 | Void | Void invoice yang sudah lunas | 422 berpesan hanya invoice belum lunas yang bisa dibatalkan | ✔ API |
| M5-07 | Umur piutang | `/management/umur-piutang` (setelah backdate di bagian 2) | Fajar di bucket 1–30 hari; invoice lain belum jatuh tempo | ✔ API |
| M5-08 | Reminder | Jalankan `php artisan invoices:send-reminders` setelah backdate | "Pengingat terkirim: 1"; ortu6 mendapat notifikasi. Terjadwal harian 01.00 | ✔ API; Test: PaymentReminderTest |
| M5-09 | Laporan pemasukan | `/management/laporan-pemasukan`, export Excel dan PDF | Total sesuai filter cabang dan periode; file terunduh | ✔ API (laporan, Excel); Test: IncomeReportTest |
| M5-10 | Perpanjangan | ortu10 (Hana) → ajukan perpanjangan | Invoice baru terbit tanpa verifikasi; enrollment tetap aktif | ✔ API |
| M5-11 | Token integrasi Keuangan | `/management/integrasi` → buat token `attendance:read` untuk keuangan@ | Dengan token: `/api/integration/v1/trainer-attendances?from=&to=` 200 sesuai kontrak. Ke `/invoices`: 403. Ke `/me`: 403. Tanpa from/to: 422 | ✔ API |

### Modul 6 — Kelas, Penempatan, Jadwal

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M6-01 | Guard belum lunas (BR-09) | koordinator → `/management/penempatan` → tempatkan Fajar | 422 "Pembayaran pendaftaran belum lunas" | ✔ API |
| M6-02 | Penempatan berhasil | Tempatkan Hari ke Coding Kids A | Berhasil; Hari muncul di roster kelas | ✔ API (jalur yang sama diuji dengan Gita) |
| M6-03 | Guard level (BR-10) | Buat kelas level Menengah, tempatkan Hari tanpa alasan, lalu dengan alasan | Tanpa alasan 422; dengan alasan berhasil dan alasan tercatat | ✔ API |
| M6-04 | Guard kapasitas (BR-11) | Kelas kapasitas 1 terisi, tempatkan satu lagi | 422 "Kelas sudah penuh" | ✔ API |
| M6-05 | Lintas cabang | Tempatkan siswa Bandung ke kelas Jakarta | 404 | ✔ API |
| M6-06 | Penugasan trainer | `/management/kelas` → tugaskan trainer sebagai utama atau asisten | Tersimpan | ✔ API |
| M6-07 | Jadwal dan bentrok (BR-12) | `/management/jadwal-kelas` → buat sesi untuk Rina; buat lagi jam beririsan | Pertama 201. Kedua 422 "Trainer sudah memiliki sesi lain pada jam tersebut" | ✔ API |
| M6-08 | Trainer pengganti | Buat sesi dengan trainer pelaksana Budi atau Sari | Tersimpan (201); sesi tampil di jadwalnya | ✔ API |
| M6-09 | Jadwal untuk ortu | ortu10 → `/portal/jadwal` | Sesi anak sendiri | ✔ API |
| M6-10 | Isolasi trainer | trainer3 membuka sesi Bandung atau membuatnya | Buka: 404. Buat: 403 | ✔ API |

### Modul 7 — Absensi

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M7-01 | Check-in onsite | trainer1 → `/trainer/sesi-kelas` → sesi hari ini → Check-in Saya (foto + GPS) | 201; file disimpan dengan nama UUID | ✔ API |
| M7-02 | Validasi check-in | Tanpa foto dan GPS; foto PDF; online dengan GPS | Onsite tanpa foto/GPS 422; PDF 422; online dengan GPS 422 | ✔ API (dua pertama); Test: RegularTrainerCheckInTest |
| M7-03 | Check-in sesi orang lain | trainer2 check-in sesi milik Rina | 403 | ✔ API |
| M7-04 | Absensi siswa | Pada sesi yang sama centang Hadir semua, simpan | Roster berisi siswa dengan enrollment aktif | ✔ API |
| M7-05 | Kunci sesi | Kunci sesi, lalu ubah absensi | Setelah dikunci 422 "Sesi sudah dikunci" | ✔ API |
| M7-06 | Isolasi | trainer3 mengisi absensi kelas Bandung | 404 | ✔ API |
| M7-07 | Monitoring Management | `/management/rekap-absensi`; export Excel/PDF; daftar trainer belum check-in | Rekap dan export sesuai filter (rekap wajib rentang tanggal `from`/`to`) | ✔ API (PDF 200, daftar belum check-in) |
| M7-08 | Rekap trainer untuk honor | keuangan@ memanggil `/api/regular-attendance/trainers?from=&to=`; trainer1 membuka `/trainer/rekap` | Rekap sesuai rentang | ✔ API (Keuangan) |

### Modul 8 — Catatan Progres

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M8-01 | Tulis catatan | trainer1 → `/trainer/catatan-progres` → catatan untuk siswa di satu sesi | 201 | ✔ API |
| M8-02 | Lihat di Management | admin → `/management/catatan-progres` | Catatan terlihat | ✔ API |
| M8-03 | Isolasi | trainer3 menulis catatan untuk sesi Bandung | 404 | ✔ API |

### Modul 9 — Evaluasi dan Raport

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M9-01 | Evaluasi baru | trainer1 → `/trainer/evaluasi-siswa` → buat evaluasi periode untuk Gita | 201; raport draft ikut terbentuk | ✔ API |
| M9-02 | Ajukan sebelum lengkap | Ajukan raport sebelum semua kriteria rubrik dinilai | 422 "Semua kriteria rubrik harus dinilai" | ✔ API |
| M9-03 | Ajukan lengkap | Isi skor tiga kriteria, ajukan | Status Ditinjau | ✔ API |
| M9-04 | Tinjau: kembalikan | koordinator → `/management/raport` → raport Ilham → Kembalikan tanpa catatan, lalu dengan catatan | Tanpa catatan 422. Dengan catatan: kembali ke trainer; ortu11 belum melihat raport | ✔ API |
| M9-05 | Tinjau: terbitkan | Trainer ajukan ulang, koordinator Setujui dan terbitkan | Status terkirim; ortu11 kini melihat raport | ✔ API |
| M9-06 | Hak trainer | trainer menyetujui raport sendiri | 403 | ✔ API |
| M9-07 | Ortu hanya melihat yang terkirim | ortu10 (terkirim), ortu12 (draft) → `/portal/anak/:id/raport` | ortu10 melihat dan mengunduh PDF; ortu12 tidak melihat raport. Ortu lain mengunduh PDF ortu10: 404 | ✔ API |

### Modul 10 — Dashboard dan Laporan

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M10-01 | KPI dan funnel | admin → `/management` | Angka sesuai bagian 3 pada seed baru | ✔ API |
| M10-02 | Filter cabang | Pilih Jakarta | Siswa aktif 1 | ✔ API |
| M10-03 | Dashboard per peran | koordinator@ (Bandung saja) dan pimpinan@ (semua cabang) | Koordinator hanya cabang Bandung; pimpinan semua | ✔ API |
| M10-04 | Panel operasional | Setelah M7 dan M5 | Hadir hari ini, pembayaran menunggu, invoice overdue ikut berubah | ✔ API |
| M10-05 | Export dashboard | Export Excel dan PDF | Terunduh (200) | ✔ API |
| M10-06 | Hak akses | trainer memanggil KPI | 403 | ✔ API |

### Modul 12 — Manajemen Proyek (Management dan Developer)

| ID | Skenario | Langkah | Hasil | Status |
| --- | --- | --- | --- | --- |
| M12-01 | Team dan proyek | `/management/team`, `/management/proyek` | Team terbentuk (201), papan proyek tampil | ✔ API (team); UI |
| M12-02 | Task, checklist, lampiran, dependensi, Gantt, kalender | `/management/proyek/:id`, `/gantt`, `/management/kalender` | Sesuai test | Test: folder `tests/Feature/Api` |

Modul 11 (HR) tidak punya fitur (tidak ada FR di PRD). Role HR hanya terdaftar di RBAC.

## 5. Temuan dan keterbatasan

### 5.1 Pembatasan laju form publik dipakai bersama

`/public/trial-registrations` memakai `throttle:5,1`, dan GET publik memakai `throttle:60,1`. Keduanya tidak bernama, sehingga kuncinya sama (domain + IP). Terbukti: 4 GET publik lalu 1 POST form menghabiskan jatah 5 per menit, sehingga POST berikutnya 429. Dampak: satu orang membuka beranda, daftar program, dan form sudah memakai sebagian jatah. Banyak pendaftar di satu Wi-Fi (booth ekspo) saling memblokir. **Untuk demo:** jangan refresh berulang; bila 429, tunggu semenit. Perbaikannya kecil (limiter bernama untuk form), tetapi mengubah perilaku dan butuh test, jadi belum diterapkan.

### 5.2 Role Keuangan dan HR belum punya layar

`homeRoute` di `stores/auth.js` hanya mengenal Management, Developer, Orangtua; selain itu `/trainer`. Guard router lalu memantulkan role `finance` dan `hr` dari `/trainer`. Dibaca dari kode, **belum dicoba di browser**; kemungkinan redirect berulang. Jangan login UI dengan `keuangan@demo.id` atau `hr@`. Tunjukkan layar Keuangan lewat `admin@demo.id`. API role Keuangan tetap teruji.

### 5.3 Hal lain

- Test berjalan di SQLite; jalankan seluruh skenario sekali di MySQL 8.
- `composer.json` masih `php ^8.3`, dependensi butuh 8.4.
- Absensi sekolah lama (`/sessions`, `/trainer/absensi`) dan reguler (`/class-sessions`, `/trainer/sesi-kelas`) hidup berdampingan; cutover belum dilakukan.
- Ekspor Excel memakai `openspout` langsung, bukan `maatwebsite/excel` seperti PRD D-05.
- Dokumen UAT 5 Nov menyebut `RegularLifecycleTest` dan `RegularDemoSeederTest`; keduanya tidak ada di `main`. Hanya `RegularDemoSeeder` yang masuk.
- Pertanyaan terbuka PRD yang mempengaruhi UAT: OQ-10 (GPS kelas reguler), OQ-14 (format nomor invoice).

## 6. Skrip demo (±25 menit)

Siapkan dua jendela browser: satu admin, satu sesuai peran. Seed ulang di hari demo.

| Menit | Peran | Yang ditunjukkan | Skenario |
| --- | --- | --- | --- |
| 0–2 | Publik | `/`, `/program`, detail program | M1-01 |
| 2–5 | Publik → ortu1 | Form `/daftar-trial` (email baru), lalu login ortu1 → `/portal` | M2-01 |
| 5–8 | ortu2 | Hasil trial Bima, Lanjut, pendaftaran terisi otomatis, kirim | M4-01, 02, 04, 05 |
| 8–11 | admin | Verifikasi: Dian revisi (tanpa catatan ditolak), Bima disetujui, invoice terbit | M5-01, 02 |
| 11–14 | ortu6 / admin | Unggah bukti; konfirmasi invoice Gita; kuitansi; piutang | M5-03 s.d. 05, 07 |
| 14–17 | koordinator | Penempatan: Fajar ditolak, Hari berhasil; sesi bentrok ditolak | M6-01, 02, 07 |
| 17–20 | trainer1 (lebar 360 px) | Check-in sesi hari ini, absen, kunci sesi | M7-01, 04, 05 |
| 20–23 | trainer1 → koordinator → ortu10 | Ajukan raport Gita; tinjau Ilham; ortu10 unduh PDF raport | M9-03, 05, 07 |
| 23–25 | admin / pendaftaran@ | KPI dan funnel; login pendaftaran@ hanya melihat Bandung | M10-01, M0-03 |

Menjalankan test otomatis:

```
cd after-schola
php artisan test                      # 634 test
cd frontend && npm test               # 12 test
```
