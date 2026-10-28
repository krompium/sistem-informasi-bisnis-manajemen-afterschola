<?php

namespace App\Http\Requests;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

        return true;
    }

    // project_id sengaja tidak ada: task tidak dipindah antar proyek.
    // TODO(nanti): Q4 — belum ada aturan transisi status; Done juga belum memaksa progress 100.
    public function rules(): array
    {
        $task = $this->route('task');
        $teamId = $task->project->team_id;
        // Tanggal mulai tidak boleh melewati tenggat: bandingkan dengan nilai baru bila dikirim, kalau tidak dengan nilai tersimpan.
        $due = $this->input('due_date') ?? $task->due_date->toDateString();
        $start = $this->has('start_date') ? $this->input('start_date') : $task->start_date?->toDateString();

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignee_id' => ['sometimes', 'required', Rule::exists('team_user', 'user_id')->where('team_id', $teamId)],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'category' => ['sometimes', Rule::enum(TaskCategory::class)],
            'progress' => ['sometimes', 'integer', 'between:0,100'],
            'due_date' => array_filter(['sometimes', 'required', 'date', $start ? 'after_or_equal:'.$start : null]),
            'start_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:'.$due],
            'github_url' => ['sometimes', 'nullable', 'url:https', 'starts_with:https://github.com/', 'max:500'],
        ];
    }
}
