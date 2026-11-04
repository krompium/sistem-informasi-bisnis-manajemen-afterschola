<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Filter GET /integration/v1/trainer-attendances (PRD 10.4): from dan to wajib. */
class IntegrationTrainerAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ability dan permission dijaga middleware route
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'trainer_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
