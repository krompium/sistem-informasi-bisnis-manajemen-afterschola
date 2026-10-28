<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('project'));

        return true;
    }

    // team_id sengaja tidak ada: proyek tidak dipindah antar team.
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
            'start_date' => ['sometimes', 'required', 'date',
                'before_or_equal:'.($this->input('due_date') ?? $project->due_date->toDateString())],
            'due_date' => ['sometimes', 'required', 'date',
                'after_or_equal:'.($this->input('start_date') ?? $project->start_date->toDateString())],
        ];
    }
}
