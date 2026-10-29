<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProgramLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga middleware permission:manage programs
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('program_levels', 'name')
                    ->where('program_id', $this->route('program')->id)
                    ->ignore($this->route('level')?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
