<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    // Endpoint publik (form trial) — siapa saja boleh mengisi.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'parent_phone' => ['required', 'regex:/^[0-9+\-\s()]{8,20}$/'],
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
            'parent_email.unique' => 'Email sudah terdaftar. Silakan masuk untuk menambah data anak.',
            'parent_phone.regex' => 'Nomor telepon tidak valid.',
            'trial_slot_id.exists' => 'Slot trial tidak ditemukan.',
            'consent.accepted' => 'Persetujuan penggunaan data pribadi wajib dicentang.',
        ];
    }
}
