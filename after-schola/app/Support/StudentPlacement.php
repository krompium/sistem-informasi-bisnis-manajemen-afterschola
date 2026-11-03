<?php

namespace App\Support;

use App\Models\ClassGroup;
use App\Models\Enrollment;
use App\Models\Registration;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menempatkan siswa dari pendaftaran ke kelas (Modul 6b). Semua penolakan aturan bisnis dilempar sebagai
 * ValidationException (422): BR-09 harus Lunas, BR-10 level sama dengan rekomendasi trial, BR-11 kapasitas.
 * Baris students baru dibuat di sini (calon siswa sebelumnya hanya leads); perpanjangan memakai student_id yang sudah ada.
 */
class StudentPlacement
{
    public function place(Registration $registration, ClassGroup $classGroup, ?string $overrideReason): Enrollment
    {
        return DB::transaction(function () use ($registration, $classGroup, $overrideReason) {
            // Kunci baris kelas agar dua penempatan bersamaan tidak sama-sama merebut kursi terakhir (BR-11).
            $classGroup = ClassGroup::lockForUpdate()->findOrFail($classGroup->id);

            if ($registration->status !== 'terverifikasi') {
                $this->reject('registration', 'Pendaftaran belum terverifikasi.');
            }
            if (! $registration->invoices()->where('status', 'paid')->exists()) {
                $this->reject('registration', 'Pembayaran pendaftaran belum lunas.');
            }

            $student = $registration->student;
            if ($student?->enrollments()->where('status', 'aktif')->exists()) {
                $this->reject('registration', 'Siswa ini sudah ditempatkan di sebuah kelas.');
            }

            if (! $classGroup->is_active) {
                $this->reject('class_group_id', 'Kelas sudah diarsipkan.');
            }
            if ($classGroup->program_id !== $registration->program_id) {
                $this->reject('class_group_id', 'Program kelas berbeda dari program pendaftaran.');
            }
            if ($classGroup->branch_id !== $registration->branch_id) {
                $this->reject('class_group_id', 'Cabang kelas berbeda dari cabang pendaftaran.');
            }

            $recommendedLevelId = $registration->decision?->evaluation?->recommended_level_id;
            $overriding = $recommendedLevelId !== null && $recommendedLevelId !== $classGroup->level_id;
            if ($overriding && blank($overrideReason)) {
                $this->reject('level_override_reason', 'Level kelas berbeda dari rekomendasi trial; alasan wajib diisi.');
            }

            if ($classGroup->activeEnrollments()->count() >= $classGroup->capacity) {
                $this->reject('class_group_id', 'Kelas sudah penuh.');
            }

            $student ??= Student::create([
                'name' => $registration->student_name,
                'branch_id' => $registration->branch_id,
                'parent_id' => $registration->lead?->parent_id,
            ]);
            if ($registration->student_id !== $student->id) {
                $registration->update(['student_id' => $student->id]);
            }

            return Enrollment::create([
                'class_group_id' => $classGroup->id,
                'student_id' => $student->id,
                'start_date' => now(config('app.display_timezone'))->toDateString(),
                'status' => 'aktif',
                'level_override_reason' => $overriding ? $overrideReason : null,
            ]);
        });
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
