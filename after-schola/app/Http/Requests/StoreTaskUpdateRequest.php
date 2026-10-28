<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTaskUpdateRequest extends FormRequest
{
    // Komentar = menulis ke task, jadi memakai izin 'update' (Management read-only, non-anggota 404).
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

        return true;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
        ];
    }
}
