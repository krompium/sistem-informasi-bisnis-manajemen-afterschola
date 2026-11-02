<?php

namespace App\Http\Requests;

use App\Models\ClassSession;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Buat/ubah catatan progres (Modul 8, TRN-08). Sesi dan siswa tidak bisa diganti setelah catatan dibuat. */
class SaveProgressNoteRequest extends FormRequest
{
    // Otorisasi sebelum validasi: sesi atau catatan di luar scope (termasuk yang tidak ada) harus 404, bukan 422.
    public function authorize(): bool
    {
        if ($note = $this->route('progress_note')) {
            Gate::authorize('update', $note);
        } elseif ($this->filled('class_session_id')) {
            $session = ClassSession::find($this->integer('class_session_id'));
            abort_unless($session, 404);
            Gate::authorize('viewRegular', $session);
        }

        return true;
    }

    public function rules(): array
    {
        $rules = ['note' => ['required', 'string', 'max:2000']];

        if (! $this->route('progress_note')) {
            $rules['class_session_id'] = ['required', 'integer'];
            // BR-14 (versi catatan): siswa harus terdaftar di kelas sesi ini pada tanggal sesi.
            $rules['student_id'] = ['bail', 'required', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $session = ClassSession::find($this->integer('class_session_id'));
                if (! $session?->class_group_id) {
                    return;
                }

                $date = $session->date->toDateString();
                $enrolled = Enrollment::where('class_group_id', $session->class_group_id)
                    ->where('student_id', $value)
                    ->whereDate('start_date', '<=', $date)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
                    ->exists();

                if (! $enrolled) {
                    $fail('Siswa tidak terdaftar di kelas ini pada tanggal sesi.');
                }
            }];
        }

        return $rules;
    }
}
