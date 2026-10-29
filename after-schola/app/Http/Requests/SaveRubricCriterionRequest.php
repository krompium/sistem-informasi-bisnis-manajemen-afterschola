<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveRubricCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga middleware permission:manage programs
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
