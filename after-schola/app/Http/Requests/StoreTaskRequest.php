<?php

namespace App\Http\Requests;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        $teamIds = $this->user()->teams()->pluck('teams.id')->all();
        $projectTeamId = Project::whereKey($this->input('project_id'))->value('team_id');

        return [
            // Create tidak punya record untuk dicek Policy: proyek wajib di team milik user (tdd.md §4).
            'project_id' => ['required', Rule::exists('projects', 'id')->whereIn('team_id', $teamIds)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Q7: assignee wajib dan harus anggota team pemilik proyek.
            'assignee_id' => ['required', Rule::exists('team_user', 'user_id')->where('team_id', $projectTeamId)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'category' => ['sometimes', Rule::enum(TaskCategory::class)],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'due_date' => ['required', 'date'],
            'start_date' => ['nullable', 'date', 'before_or_equal:due_date'],
            // Hanya tautan GitHub ber-https: menolak skema lain (mis. javascript:) yang dirender sebagai <a href>.
            'github_url' => ['nullable', 'url:https', 'starts_with:https://github.com/', 'max:500'],
        ];
    }
}
