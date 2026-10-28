<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Team::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'members' => ['nullable', 'array'],
            'members.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'members.*.role_in_team' => ['required', 'in:member,lead'],
        ];
    }
}
