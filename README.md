# After Schola — Starter (Fase 0 + Modul Absensi)

Migration + Model + Role Seeder untuk fondasi platform (RBAC 5 role) dan modul Absensi.
Arsitektur: **API-first** (Laravel 11 + Sanctum + spatie/laravel-permission).
Skema mengikuti file absensi & form expo yang sebenarnya dipakai.

## Isi
```
database/migrations/   -> 9 migration (users.is_active, schools, trainer_school,
                          classrooms, students, class_sessions,
                          student_attendances, trainer_attendances, expo_reports)
app/Models/            -> User, School, Classroom, Student, ClassSession,
                          StudentAttendance, TrainerAttendance, ExpoReport
database/seeders/      -> RoleSeeder (management, finance, hr, trainer, developer)
```

## Langkah pasang (di project Laravel 11 baru)

1. Buat project & masuk foldernya:
   ```bash
   composer create-project laravel/laravel after-schola
   cd after-schola
   ```

2. Install paket:
   ```bash
   composer require laravel/sanctum spatie/laravel-permission maatwebsite/excel barryvdh/laravel-dompdf
   ```

3. Publish konfigurasi:
   ```bash
   php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   ```

4. Salin isi folder ini ke project (timpa `app/Models/User.php`):
   ```bash
   cp -r database/migrations/*   <project>/database/migrations/
   cp -r app/Models/*            <project>/app/Models/
   cp -r database/seeders/*      <project>/database/seeders/
   ```

5. Set database di `.env`, lalu migrate + seed role:
   ```bash
   php artisan migrate
   php artisan db:seed --class=RoleSeeder
   ```

6. Simpan foto/screenshot bukti kehadiran di storage publik:
   ```bash
   php artisan storage:link
   ```

## Catatan desain (final, sesuai file asli)
- Tabel pertemuan dinamai **`class_sessions`** (bukan `sessions`) agar tidak bentrok dgn tabel bawaan Laravel.
- **"Kelas" = level**: `classrooms.name` diisi `Beginner` / `Intermediate`. Murid dari berbagai kelas asal bercampur di satu level.
- **`students.origin_class`** = kelas asal siswa di sekolah (mis. `8A`, `7 Ibnu Sina`) — kolom KELAS di spreadsheet.
- **`class_sessions.meeting_no`** = pertemuan ke-1..6 (kolom di spreadsheet), tiap pertemuan punya `date`.
- **Absensi murid = boolean** `student_attendances.is_present` (Hadir/Tidak), sesuai TRUE/FALSE di spreadsheet.
- **Absensi trainer:** `photo_path` menyimpan foto (onsite) atau screenshot (online); `latitude/longitude` hanya terisi untuk onsite.
- **Expo/Free-Trial** = tabel terpisah `expo_reports` (ganti Google Form): rating, on_schedule, enthusiasm, kendala, doc_url.
- Isolasi data trainer per sekolah divalidasi lewat **Policy** (belum dibuat di paket ini).

## Langkah berikutnya (kalau lanjut)
1. Sanctum auth endpoints (login -> token) + middleware role.
2. Policy: trainer hanya akses sekolah yang dipegang.
3. API Resource + Controller: sekolah, level, murid, pertemuan, absensi, expo report.
4. Export Excel & PDF (endpoint download) meniru layout spreadsheet & form expo.
