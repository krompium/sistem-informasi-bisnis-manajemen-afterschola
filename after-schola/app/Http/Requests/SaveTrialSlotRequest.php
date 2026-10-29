<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveTrialSlotRequest extends FormRequest
{
    // Otorisasi sebelum validasi: slot di luar cabang user harus 404, bukan 422.
    public function authorize(): bool
    {
        if ($slot = $this->route('trial_slot')) {
            Gate::authorize('update', $slot);
        }

        return true;
    }

    public function rules(): array
    {
        // Slot baru hanya untuk cabang & program aktif; slot lama tetap bisa diedit walau induknya sudah diarsipkan.
        $creating = $this->isMethod('POST');
        $activeOnly = fn ($rule) => $rule->where('is_active', true);

        return [
            'branch_id' => [
                'bail', 'required', 'integer',
                Rule::exists('branches', 'id')->when($creating, $activeOnly),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $this->user()->handlesBranch((int) $value)) {
                        $fail('Cabang di luar cakupan Anda.');
                    }
                },
            ],
            'program_id' => ['bail', 'required', 'integer', Rule::exists('programs', 'id')->when($creating, $activeOnly)],
            'trainer_id' => [
                'bail', 'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $trainer = User::role('trainer')->where('is_active', true)->find($value);
                    if (! $trainer) {
                        $fail('Trainer tidak valid.');
                    } elseif ($this->integer('branch_id') && ! $trainer->handlesBranch($this->integer('branch_id'))) {
                        $fail('Trainer tidak ditugaskan di cabang ini.');
                    }
                },
            ],
            'starts_at' => array_filter(['required', 'date', $creating ? 'after:now' : null]),
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'program_id.exists' => 'Program tidak ditemukan atau nonaktif.',
            'branch_id.exists' => 'Cabang tidak ditemukan atau nonaktif.',
            'starts_at.after' => 'Waktu slot harus di masa depan.',
            'capacity.min' => 'Kapasitas minimal 1 peserta.',
            'capacity.max' => 'Kapasitas maksimal 100 peserta.',
        ];
    }
}
