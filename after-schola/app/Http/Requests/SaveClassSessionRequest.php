<?php

namespace App\Http\Requests;

use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Buat/ubah sesi kelas reguler (Modul 6c, MGT-34). Kelas tidak bisa dipindah setelah sesi dibuat. */
class SaveClassSessionRequest extends FormRequest
{
    // Otorisasi sebelum validasi: kelas atau sesi di luar scope user harus 404, bukan 422.
    public function authorize(): bool
    {
        if ($session = $this->route('class_session')) {
            Gate::authorize('viewRegular', $session);
        } elseif ($group = ClassGroup::find($this->input('class_group_id'))) {
            Gate::authorize('view', $group);
        }

        return true;
    }

    public function rules(): array
    {
        $session = $this->route('class_session');
        $group = $session?->classGroup ?? ClassGroup::find($this->input('class_group_id'));

        $rules = [
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'meeting_no' => ['required', 'integer', 'min:1', 'max:200'],
            'mode' => ['required', Rule::in(['onsite', 'online'])],
            // Pelaksana boleh trainer yang tidak ditugaskan ke kelas: itulah trainer pengganti (MGT-36, versi minimal).
            'trainer_id' => [
                'bail', 'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($group) {
                    $trainer = User::role('trainer')->where('is_active', true)->find($value);
                    if (! $trainer) {
                        $fail('Trainer tidak valid.');
                    } elseif ($group && ! $trainer->handlesBranch($group->branch_id)) {
                        $fail('Trainer tidak ditugaskan di cabang kelas ini.');
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];

        if (! $session) {
            $rules['class_group_id'] = ['bail', 'required', 'integer', Rule::exists('class_groups', 'id')->where('is_active', true)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'class_group_id.exists' => 'Kelas tidak ditemukan atau sudah diarsipkan.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }

    // Bentrok dicek setelah semua isian valid. BR-12: satu trainer tidak boleh punya dua sesi yang jamnya beririsan.
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // Dibandingkan di PHP (jam "HH:MM"): kolom time tersimpan beda format di tiap database, dan baris
            // seorang trainer pada satu hari hanya segelintir. Sesi cabang lain dan sesi sekolah ikut dihitung.
            $clash = ClassSession::where('trainer_id', $this->integer('trainer_id'))
                ->whereDate('date', $this->input('date'))
                ->whereNotNull('start_time')->whereNotNull('end_time')
                ->when($this->route('class_session'), fn ($q, $session) => $q->whereKeyNot($session->id))
                ->get(['start_time', 'end_time'])
                ->contains(fn (ClassSession $other) => substr($other->start_time, 0, 5) < $this->input('end_time')
                    && substr($other->end_time, 0, 5) > $this->input('start_time'));

            if ($clash) {
                $validator->errors()->add('trainer_id', 'Trainer sudah memiliki sesi lain pada jam tersebut.');
            }
        }];
    }
}
