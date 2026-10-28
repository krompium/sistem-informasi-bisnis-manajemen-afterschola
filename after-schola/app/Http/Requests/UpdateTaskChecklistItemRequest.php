<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTaskChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('checklist')->task);

        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'is_done' => ['sometimes', 'boolean'],
        ];
    }
}
