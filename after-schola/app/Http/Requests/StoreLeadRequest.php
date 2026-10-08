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
            'name' => ['required', 'string', 'max:255'],
            'education_level' => ['required', 'in:sd,smp,sma'],
            'origin_school' => ['required', 'string', 'max:255'],
            'parent_email' => ['required', 'email', 'max:255'],
            'program_id' => ['nullable', 'integer'],
            'program_trial_schedule_id' => ['nullable', 'integer'],
        ];
    }
}
