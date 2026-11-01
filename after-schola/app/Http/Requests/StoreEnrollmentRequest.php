<?php

namespace App\Http\Requests;

use App\Models\ClassGroup;
use App\Models\Registration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreEnrollmentRequest extends FormRequest
{
    // Otorisasi sebelum validasi: registrasi atau kelas di luar cabang user harus 404, bukan 422.
    public function authorize(): bool
    {
        if ($registration = Registration::find($this->input('registration_id'))) {
            Gate::authorize('place', $registration);
        }
        if ($group = ClassGroup::find($this->input('class_group_id'))) {
            Gate::authorize('view', $group);
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'registration_id' => ['required', 'integer', 'exists:registrations,id'],
            'class_group_id' => ['required', 'integer', 'exists:class_groups,id'],
            'level_override_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
