# Fitur per Modul — After Schola Platform

> Rincian kebutuhan fitur tiap modul. Dipakai sebagai acuan build & pembagian tugas.
> **Legenda prioritas:** `[MVP]` dibangun sekarang · `[v2]` fase berikutnya · `[v3]` nanti
> Modul **Absensi** paling detail (siap dikerjakan). Modul lain masih **usulan awal** — perlu digali lebih lanjut sebelum dibangun.

---

## 0. Fondasi Platform (dipakai semua modul)

Ini lapisan dasar sebelum modul apa pun jalan.

**Autentikasi & Akses**
- `[MVP]` Login (email + password) via API + token (Sanctum)
- `[MVP]` Logout & refresh token
- `[MVP]` Reset / lupa password
- `[MVP]` 5 role: Management, Finance, HR, Trainer, Developer
- `[MVP]` Middleware role + Policy (backend yang menentukan hak akses, bukan UI)
- `[v2]` Ganti password & edit profil sendiri

**Kerangka Aplikasi**
- `[MVP]` Routing dashboard per role (login → diarahkan ke dashboard sesuai role)
- `[MVP]` Menu & fitur dirender sesuai hak akses
- `[MVP]` Manajemen user (oleh Management): buat, nonaktifkan, atur role
- `[v2]` Audit trail (siapa mengubah apa & kapan)
- `[v2]` Notifikasi dalam aplikasi

---

## 1. Modul Absensi `[MVP]` — PRIORITAS

Modul inti. Dipecah jadi 6 bagian.

### 1.1 Data Master (Sekolah, Level, Murid)
> **Pemilik data:** semua data master (sekolah, alamat, level, penugasan trainer) dibuat & dikelola oleh **Management**. Trainer hanya melihat sekolah yang dipegang dan boleh menambah murid.

- `[MVP]` CRUD sekolah (nama, alamat, PIC, kontak) — oleh Management
- `[MVP]` Penugasan trainer ↔ sekolah (satu trainer banyak sekolah, sebaliknya juga)
- `[MVP]` CRUD level per sekolah (Beginner / Intermediate)
- `[MVP]` CRUD murid: nama, kelas asal (mis. `8A`), level, sekolah
- `[MVP]` Trainer boleh **menambah murid** di sekolah yang ia pegang
- `[MVP]` Import murid dari Excel (onboarding sekolah baru cepat)
- `[v2]` Pindah/promosi murid antar level

### 1.2 Jadwal & Pertemuan
- `[MVP]` Admin/Management buat pertemuan: sekolah + level + trainer + tanggal + pertemuan ke-berapa + mode (onsite/online)
- `[MVP]` Trainer melihat jadwal & pertemuannya (read-only)
- `[v2]` Jadwal berulang otomatis (mis. tiap Senin)
- `[v2]` Kalender pertemuan di dashboard trainer

### 1.3 Absensi Murid
- `[MVP]` Pilih pertemuan → daftar murid → centang **Hadir / Tidak**
- `[MVP]` Simpan & edit selama pertemuan belum dikunci
- `[MVP]` Ringkasan otomatis "jumlah hadir" per pertemuan
- `[MVP]` Kunci pertemuan (agar tidak diubah lagi)
- `[MVP]` Catatan opsional per murid
- `[v2]` Riwayat kehadiran per murid (rekap lintas pertemuan)

### 1.4 Absensi Trainer
- `[MVP]` Check-in per pertemuan (jam tercatat otomatis)
- `[MVP]` Status: Hadir / Terlambat / Tidak Hadir
- `[MVP]` Onsite: upload **foto** + rekam **GPS** (lat/long)
- `[MVP]` Online: upload **screenshot** (tanpa GPS)
- `[v2]` Rekap kehadiran trainer per periode (untuk HR/Finance)

### 1.5 Laporan Ekspo / Free-Trial
- `[MVP]` Form laporan (ganti Google Form): tanggal, sekolah, nama tim, rating 1–5, sesuai jadwal (Ya/Sebagian/Tidak), antusiasme, ada kendala + penjelasan, link/upload dokumentasi
- `[MVP]` Daftar & lihat semua laporan (Admin/Management)
- `[v2]` Export laporan ekspo (Excel/PDF)

### 1.6 Export & Dashboard
- `[MVP]` Export rekap absensi murid ke **Excel** (per level / sekolah / periode) — meniru layout spreadsheet
- `[MVP]` Export rekap ke **PDF** siap cetak (kop, nama sekolah, periode)
- `[MVP]` Trainer hanya bisa export data sekolahnya
- `[MVP]` Dashboard trainer: sekolah yang dipegang, pertemuan hari ini, ringkas kehadiran
- `[MVP]` Dashboard Management: total sekolah/murid/trainer, kehadiran hari ini, trainer yang belum absen
- `[v2]` Grafik tren kehadiran

---

## 2. Modul Management `[v2]` — *usulan awal*

- Dashboard oversight lintas modul (KPI, ringkasan)
- Lihat semua data absensi, HR, finance, proyek (read-only lintas modul)
- Kelola user & role (sudah ada di Fondasi)
- Laporan agregat & export lintas sekolah
- `[v3]` Target/KPI per trainer atau per sekolah

> Perlu digali: KPI apa yang mau dipantau? Laporan apa yang rutin dibutuhkan manajemen?

---

## 3. Modul HR `[v2]` — *usulan awal*

- Data karyawan & trainer (profil, kontak, status)
- Pengajuan & persetujuan **cuti / izin**
- Rekap kehadiran trainer (tarik dari modul Absensi) sebagai basis payroll
- Rekrutmen / kandidat trainer (opsional)
- `[v3]` Evaluasi / penilaian kinerja trainer

> Perlu digali: alur cuti (siapa yang menyetujui?), data karyawan apa saja yang disimpan?

---

## 4. Modul Finance `[v2]` — *usulan awal*

- **Payroll** trainer dihitung dari data absensi/pertemuan trainer
- Slip gaji (trainer lihat slipnya sendiri)
- **Invoice** ke sekolah (berbasis program yang berjalan)
- Pencatatan pemasukan & pengeluaran
- `[v3]` Laporan keuangan sederhana (rekap bulanan)

> Perlu digali: rumus payroll (per pertemuan? per jam? per sekolah?), format invoice ke sekolah.

---

## 5. Modul Developer — Manajemen Proyek `[v3]` — *usulan awal*

Untuk tim teknis. Ada beberapa team dev berbeda.

- Kelola **team** (1 developer = 1 team untuk sekarang, ada **team lead**)
- Kelola **proyek** per team (nama, deskripsi, status, deadline)
- Kelola **task** per proyek (judul, assignee, status, progres)
- **Board kanban**: To Do → In Progress → Review → Done
- **Progres task**: persen (0–100) + posisi kolom kanban
- Dashboard team: proyek berjalan, ringkas progres, siapa mengerjakan apa
- Developer lihat board team-nya; Management lihat semua team

> Perlu digali: apakah butuh komentar/lampiran di task, integrasi dengan Git/Jenkins?

---

## Ringkasan Prioritas Build

| Tahap | Isi |
|-------|-----|
| **Fase 0** | Fondasi Platform (auth, RBAC 5 role, dashboard per role) |
| **Fase 1** | **Modul Absensi lengkap** (bagian 1.1–1.6) |
| **Fase 2** | Modul HR + Finance |
| **Fase 3** | Modul Manajemen Proyek (Developer) |
| **Fase 4** | Penyempurnaan (jadwal berulang, analitik, mobile) |

> Fokus sekarang: **Fase 0 + Modul Absensi**. Modul lain baru digali requirement-nya saat gilirannya tiba.
