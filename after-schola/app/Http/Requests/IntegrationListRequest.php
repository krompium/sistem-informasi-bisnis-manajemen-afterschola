<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Filter API integrasi (PRD 10.4). Akses dicek di route (ability + permission). */
class IntegrationListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $statuses = $this->is('*/payments') ? ['pending', 'confirmed', 'rejected'] : ['unpaid', 'paid', 'void'];

        return [
            'status' => ['nullable', Rule::in($statuses)],
            'branch_id' => ['nullable', 'integer'],
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
