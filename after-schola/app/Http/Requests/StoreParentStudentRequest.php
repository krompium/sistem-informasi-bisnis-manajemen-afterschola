<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** ORT-03: orang tua menambah anak untuk trial. Hak akses (role parent) dicek di route. */
class StoreParentStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'education_level' => ['required', 'in:sd,smp,sma'],
            'origin_school' => ['required', 'string', 'max:255'],
            'trial_slot_id' => ['required', 'integer', 'exists:trial_slots,id'],
            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'trial_slot_id.exists' => 'Slot trial tidak ditemukan.',
            'consent.accepted' => 'Persetujuan penggunaan data pribadi wajib dicentang.',
        ];
    }
}
