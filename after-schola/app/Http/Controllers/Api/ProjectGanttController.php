<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\TaskDependency;

class ProjectGanttController extends Controller
{
    // Semua task satu proyek sekaligus (tidak dipaginasi: Gantt harus utuh) beserta ketergantungannya.
    public function __invoke(Project $project)
    {
        $this->authorize('view', $project);

        $tasks = $project->tasks()
            ->with('assignee')
            ->orderByRaw('COALESCE(start_date, due_date)')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => [
            'project' => ProjectResource::make($project)->resolve(),
            'tasks' => TaskResource::collection($tasks)->resolve(),
            'dependencies' => TaskDependency::whereIn('task_id', $tasks->pluck('id'))
                ->orderBy('id')
                ->get(['id', 'task_id', 'depends_on_id']),
        ]]);
    }
}
