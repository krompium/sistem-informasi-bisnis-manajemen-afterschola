<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTaskChecklistItemRequest extends FormRequest
{
    // Mengubah checklist = mengubah task: Management read-only, non-anggota 404.
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
        ];
    }
}
