<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveClassGroupRequest extends FormRequest
{
    // Otorisasi sebelum validasi: kelas di luar scope user harus 404, bukan 422.
    public function authorize(): bool
    {
        if ($group = $this->route('class_group')) {
            Gate::authorize('view', $group);
        }

        return true;
    }

    public function rules(): array
    {
        // Kelas baru hanya untuk cabang & program aktif; kelas lama tetap bisa diedit walau induknya diarsipkan.
        $creating = $this->isMethod('POST');
        $activeOnly = fn ($rule) => $rule->where('is_active', true);
        $group = $this->route('class_group');
        // Program, level, dan cabang tidak boleh berubah setelah kelas punya siswa (riwayat enrollment mengacu ke sana).
        $lockedWhenEnrolled = fn (string $column) => function (string $attribute, mixed $value, \Closure $fail) use ($group, $column) {
            if ($group && (int) $value !== (int) $group->{$column} && $group->enrollments()->exists()) {
                $fail('Tidak dapat diubah karena kelas sudah memiliki siswa.');
            }
        };

        return [
            'branch_id' => [
                'bail', 'required', 'integer',
                Rule::exists('branches', 'id')->when($creating, $activeOnly),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->user()->handlesBranch((int) $value)) {
                        $fail('Cabang di luar cakupan Anda.');
                    }
                },
                $lockedWhenEnrolled('branch_id'),
            ],
            'program_id' => ['bail', 'required', 'integer', Rule::exists('programs', 'id')->when($creating, $activeOnly), $lockedWhenEnrolled('program_id')],
            'level_id' => ['bail', 'required', 'integer', Rule::exists('program_levels', 'id')->where('program_id', $this->input('program_id')), $lockedWhenEnrolled('level_id')],
            'name' => ['required', 'string', 'max:100'],
            'capacity' => [
                'bail', 'required', 'integer', 'min:1', 'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($group) {
                    $active = $group?->activeEnrollments()->count() ?? 0;
                    if ((int) $value < $active) {
                        $fail("Kapasitas tidak boleh kurang dari jumlah siswa aktif ({$active}).");
                    }
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'program_id.exists' => 'Program tidak ditemukan atau nonaktif.',
            'branch_id.exists' => 'Cabang tidak ditemukan atau nonaktif.',
            'level_id.exists' => 'Level tidak ditemukan pada program ini.',
            'capacity.min' => 'Kapasitas minimal 1 siswa.',
            'capacity.max' => 'Kapasitas maksimal 100 siswa.',
        ];
    }
}
