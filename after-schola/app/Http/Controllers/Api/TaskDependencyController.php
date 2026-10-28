<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskDependencyRequest;
use App\Http\Resources\TaskDependencyResource;
use App\Models\Task;
use App\Models\TaskDependency;

class TaskDependencyController extends Controller
{
    // Prasyarat satu task (task yang harus selesai lebih dulu).
    public function index(Task $task)
    {
        $this->authorize('view', $task);

        return TaskDependencyResource::collection(
            $task->dependencies()->with('prerequisite')->orderBy('id')->get()
        );
    }

    public function store(StoreTaskDependencyRequest $request, Task $task)
    {
        $dependency = $task->dependencies()->create($request->validated());

        return new TaskDependencyResource($dependency->load('prerequisite'));
    }

    public function destroy(TaskDependency $dependency)
    {
        $this->authorize('update', $dependency->task);

        $dependency->delete();

        return response()->noContent();
    }
}
