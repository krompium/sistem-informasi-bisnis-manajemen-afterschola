<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class AttendanceImportController extends Controller
{
    private const TEMP_DIR = 'attendance-imports';

    private const TEMP_TTL_MINUTES = 60;

    /**
     * Baca file Excel/CSV, cocokkan nama murid & deteksi tanggal pertemuan
     * dari header, lalu simpan hasil parsing sementara (belum ditulis ke
     * database) dan kembalikan ringkasannya untuk dicek admin dulu.
     *
     * Format yang didukung (sesuai rekap asli After Schola):
     * - Kolom "NO" (opsional, diabaikan), "NAMA", "KELAS" — urutan & posisi
     *   kolomnya dideteksi otomatis dari teks header, tidak harus kolom 1/2/3.
     * - Kolom pertemuan bisa berupa 1 baris header (langsung tanggal) atau
     *   2 baris header (baris nomor pertemuan + baris tanggal di bawahnya).
     * - Baris "JUMLAH ..." di bagian bawah otomatis dilewati.
     */
    public function preview(Request $request)
    {
        $this->authorize('create', ClassSession::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
            'start_meeting_no' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->ensureClassroomInSchool((int) $data['classroom_id'], (int) $data['school_id']);

        $path = $request->file('file')->getRealPath();
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $reader = $extension === 'csv' || $extension === 'txt' ? new CsvReader : new XlsxReader;

        $rows = [];
        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    try {
                        $rows[] = $row->toArray();
                    } catch (\Throwable $rowError) {
                        // Baris ini gagal dibaca (mis. berisi rumus yang rewel) — lewati
                        // baris ini saja, jangan gagalkan seluruh import karenanya.
                        Log::warning('Baris dilewati saat import absensi: '.$rowError->getMessage());
                        $rows[] = [];
                    }
                }
                break; // hanya sheet pertama
            }
            $reader->close();
        } catch (\Throwable $e) {
            Log::error('Gagal baca file import absensi: '.$e->getMessage(), ['exception' => $e]);
            throw ValidationException::withMessages([
                'file' => ['Gagal membaca isi file. Pastikan formatnya benar (.xlsx/.csv) dan tidak corrupt.'],
            ]);
        }

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'file' => ['File kosong atau tidak punya baris data.'],
            ]);
        }

        // ---- 1. Cari posisi kolom NAMA & KELAS, dan baris mana yang berisi label ini ----
        $nameCol = null;
        $classCol = null;
        $labelRow = 0;

        for ($r = 0; $r < min(3, count($rows)); $r++) {
            foreach ($rows[$r] as $c => $val) {
                $text = Str::lower($this->cellToString($val));
                if ($nameCol === null && str_contains($text, 'nama')) {
                    $nameCol = $c;
                    $labelRow = $r;
                }
                if ($classCol === null && str_contains($text, 'kelas')) {
                    $classCol = $c;
                }
            }
            if ($nameCol !== null) {
                break;
            }
        }

        if ($nameCol === null) {
            // Fallback: asumsikan kolom pertama = nama kalau label "NAMA" tidak ketemu.
            $nameCol = 0;
            $labelRow = 0;
        }

        $lastLabelCol = max($nameCol, $classCol ?? $nameCol);

        // ---- 2. Cari baris tanggal untuk kolom-kolom pertemuan (bisa sama dengan labelRow, bisa 1 baris di bawahnya) ----
        $dateRow = null;
        for ($r = $labelRow; $r < min($labelRow + 3, count($rows)); $r++) {
            $hits = 0;
            foreach ($rows[$r] as $c => $val) {
                if ($c <= $lastLabelCol) {
                    continue;
                }
                if ($this->parseDateFromHeader($this->cellToString($val))) {
                    $hits++;
                }
            }
            if ($hits > 0) {
                $dateRow = $r;
                break;
            }
        }

        if ($dateRow === null) {
            throw ValidationException::withMessages([
                'file' => ['Tidak ada kolom pertemuan dengan tanggal yang terbaca. Pastikan ada baris berisi tanggal pertemuan (mis. 27/7/2026).'],
            ]);
        }

        // Baris nomor pertemuan (kalau ada), biasanya 1 baris di atas baris tanggal.
        $meetingNoRow = $dateRow > 0 ? $dateRow - 1 : null;

        // ---- 3. Kumpulkan kolom pertemuan ----
        $meetingColumns = [];
        $skippedColumns = [];
        $startNo = $data['start_meeting_no'] ?? 1;
        $meetingIndex = 0;
        $maxCol = max(array_map('count', $rows)) - 1;

        for ($col = $lastLabelCol + 1; $col <= $maxCol; $col++) {
            $headerText = $this->cellToString($rows[$dateRow][$col] ?? '');
            $date = $this->parseDateFromHeader($headerText);

            if (! $date) {
                if ($headerText !== '') {
                    $skippedColumns[] = [
                        'column' => $col + 1,
                        'header' => $headerText,
                        'reason' => 'Tanggal tidak terbaca dari kolom ini',
                    ];
                }

                continue;
            }

            $explicitNo = null;
            if ($meetingNoRow !== null) {
                $noText = trim($this->cellToString($rows[$meetingNoRow][$col] ?? ''));
                if ($noText !== '' && ctype_digit($noText)) {
                    $explicitNo = (int) $noText;
                }
            }

            $existingSession = ClassSession::where('school_id', $data['school_id'])
                ->where('classroom_id', $data['classroom_id'])
                ->whereDate('date', $date)
                ->first();

            $meetingColumns[] = [
                'column' => $col,
                'header' => $headerText,
                'date' => $date->toDateString(),
                'meeting_no' => $existingSession->meeting_no ?? ($explicitNo ?? ($startNo + $meetingIndex)),
                'existing_session_id' => $existingSession?->id,
            ];
            $meetingIndex++;
        }

        if (! count($meetingColumns)) {
            throw ValidationException::withMessages([
                'file' => ['Tidak ada kolom pertemuan dengan tanggal yang valid ditemukan.'],
            ]);
        }

        // ---- 4. Cocokkan baris murid ----
        $existingStudents = Student::where('classroom_id', $data['classroom_id'])->get(['id', 'name']);
        $lookup = $existingStudents->keyBy(fn ($s) => Str::lower(trim($s->name)));

        $matched = [];
        $unmatched = [];
        $dataStartRow = $dateRow + 1;

        for ($r = $dataStartRow; $r < count($rows); $r++) {
            $row = $rows[$r];
            $name = trim($this->cellToString($row[$nameCol] ?? ''));

            if ($name === '' || str_starts_with(Str::lower($name), 'jumlah')) {
                continue; // baris kosong atau baris "JUMLAH ... HADIR" di bagian bawah
            }

            $originClass = $classCol !== null ? trim($this->cellToString($row[$classCol] ?? '')) : null;
            $student = $lookup->get(Str::lower($name));

            $attendance = [];
            foreach ($meetingColumns as $mc) {
                $cell = $row[$mc['column']] ?? null;
                $attendance[$mc['column']] = $this->isPresentValue($cell);
            }

            if ($student) {
                $matched[] = [
                    'row' => $r + 1,
                    'name' => $name,
                    'student_id' => $student->id,
                    'attendance' => $attendance,
                ];
            } else {
                $unmatched[] = [
                    'row' => $r + 1,
                    'name' => $name,
                    'origin_class' => $originClass ?: null,
                    'attendance' => $attendance,
                ];
            }
        }

        $token = (string) Str::uuid();
        Storage::makeDirectory(self::TEMP_DIR);
        Storage::put(self::TEMP_DIR."/{$token}.json", json_encode([
            'school_id' => $data['school_id'],
            'classroom_id' => $data['classroom_id'],
            'meetings' => $meetingColumns,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'created_at' => now()->toIso8601String(),
        ]));

        return response()->json([
            'token' => $token,
            'meetings' => $meetingColumns,
            'skipped_columns' => $skippedColumns,
            'matched_count' => count($matched),
            'unmatched_names' => $unmatched,
        ]);
    }

    /**
     * Tulis hasil preview (yang sudah dicek admin) ke database beneran:
     * buat pertemuan baru yang belum ada, lalu isi absensi murid yang cocok.
     */
    public function commit(Request $request, string $token)
    {
        $this->authorize('create', ClassSession::class);

        $data = $request->validate([
            'trainer_id' => ['required', 'exists:users,id'],
            'mode' => ['required', 'in:onsite,online'],
            'create_missing_students' => ['sometimes', 'boolean'],
        ]);

        $path = self::TEMP_DIR."/{$token}.json";
        if (! Storage::exists($path)) {
            throw ValidationException::withMessages([
                'token' => ['Sesi import sudah kedaluwarsa atau tidak ditemukan. Silakan upload ulang filenya.'],
            ]);
        }

        $payload = json_decode(Storage::get($path), true);

        if (Carbon::parse($payload['created_at'])->addMinutes(self::TEMP_TTL_MINUTES)->isPast()) {
            Storage::delete($path);
            throw ValidationException::withMessages([
                'token' => ['Sesi import sudah kedaluwarsa (lebih dari 1 jam). Silakan upload ulang filenya.'],
            ]);
        }

        $createdSessions = 0;
        $updatedAttendance = 0;
        $createdStudents = 0;

        DB::transaction(function () use ($payload, $data, $request, &$createdSessions, &$updatedAttendance, &$createdStudents) {
            $sessionByColumn = [];

            foreach ($payload['meetings'] as $meeting) {
                if (! empty($meeting['existing_session_id'])) {
                    $sessionByColumn[$meeting['column']] = $meeting['existing_session_id'];

                    continue;
                }

                $session = ClassSession::create([
                    'school_id' => $payload['school_id'],
                    'classroom_id' => $payload['classroom_id'],
                    'trainer_id' => $data['trainer_id'],
                    'mode' => $data['mode'],
                    'date' => $meeting['date'],
                    'meeting_no' => $meeting['meeting_no'],
                ]);
                $sessionByColumn[$meeting['column']] = $session->id;
                $createdSessions++;
            }

            $rowsToApply = $payload['matched'];

            if (! empty($data['create_missing_students'])) {
                foreach ($payload['unmatched'] ?? [] as $row) {
                    $student = Student::create([
                        'school_id' => $payload['school_id'],
                        'classroom_id' => $payload['classroom_id'],
                        'name' => $row['name'],
                        'origin_class' => $row['origin_class'] ?? null,
                        'created_by' => $request->user()->id,
                    ]);
                    $createdStudents++;

                    $rowsToApply[] = [
                        'student_id' => $student->id,
                        'attendance' => $row['attendance'],
                    ];
                }
            }

            foreach ($rowsToApply as $row) {
                foreach ($row['attendance'] as $column => $isPresent) {
                    if (! isset($sessionByColumn[$column])) {
                        continue;
                    }

                    StudentAttendance::updateOrCreate(
                        ['class_session_id' => $sessionByColumn[$column], 'student_id' => $row['student_id']],
                        ['is_present' => $isPresent],
                    );
                    $updatedAttendance++;
                }
            }
        });

        Storage::delete($path);

        return response()->json([
            'message' => 'Import absensi selesai.',
            'created_sessions' => $createdSessions,
            'created_students' => $createdStudents,
            'updated_attendance' => $updatedAttendance,
        ]);
    }

    private function cellToString($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('n/j/Y');
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) ($value ?? ''));
    }

    private function parseDateFromHeader(string $text): ?Carbon
    {
        if ($text === '') {
            return null;
        }

        // Urutan penting: format Amerika (bulan/tanggal) dicoba duluan karena
        // rekap After Schola pakai format itu (mis. 7/27/2026 = 27 Juli).
        foreach (['n/j/Y', 'j/n/Y', 'm/d/Y', 'd/m/Y', 'Y-m-d', 'm-d-Y', 'd-m-Y'] as $pattern) {
            try {
                $date = Carbon::createFromFormat('!'.$pattern, $text);
                if ($date) {
                    return $date->startOfDay();
                }
            } catch (\Throwable) {
                // lanjut coba format lain
            }
        }

        return null;
    }

    private function isPresentValue($cell): bool
    {
        if (is_bool($cell)) {
            return $cell;
        }

        $text = Str::lower(trim((string) $cell));

        return in_array($text, ['1', 'true', 'v', '✓', 'ya', 'y', 'h', 'hadir'], true);
    }

    private function ensureClassroomInSchool(int $classroomId, int $schoolId): void
    {
        $belongs = Classroom::where('id', $classroomId)->where('school_id', $schoolId)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([
                'classroom_id' => ['Mata pelajaran tidak berada di sekolah tersebut.'],
            ]);
        }
    }
}