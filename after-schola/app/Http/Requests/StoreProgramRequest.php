<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:online,offline'],
            'deskripsi' => ['nullable', 'string'],
            'biaya' => ['required', 'numeric', 'min:0'],
            'status_aktif' => ['boolean'],
        ];
    }
}