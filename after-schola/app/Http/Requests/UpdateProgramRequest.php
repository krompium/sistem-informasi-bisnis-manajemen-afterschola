<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramRequest extends FormRequest

{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'string', 'max:255'],
            'tipe' => ['sometimes', 'in:online,offline'],
            'deskripsi' => ['nullable', 'string'],
            'biaya' => ['sometimes', 'numeric', 'min:0'],
            'status_aktif' => ['sometimes', 'boolean'],
        ];
    }
}