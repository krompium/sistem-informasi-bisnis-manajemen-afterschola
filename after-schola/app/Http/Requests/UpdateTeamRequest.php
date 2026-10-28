<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateTeamRequest extends FormRequest
{
    // Gate::authorize (bukan can) supaya denyAsNotFound jadi 404, dan jalan sebelum validasi.
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('team'));

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'members' => ['sometimes', 'array'],
            'members.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'members.*.role_in_team' => ['required', 'in:member,lead'],
        ];
    }
}
