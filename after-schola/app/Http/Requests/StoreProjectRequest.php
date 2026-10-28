<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Project::class);
    }

    public function rules(): array
    {
        return [
            // Create tidak punya record untuk dicek Policy: team_id wajib milik user (tdd.md §4).
            'team_id' => ['required', Rule::exists('team_user', 'team_id')->where('user_id', $this->user()->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
