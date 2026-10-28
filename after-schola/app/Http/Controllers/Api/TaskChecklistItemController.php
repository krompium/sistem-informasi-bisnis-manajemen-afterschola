<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskChecklistItemRequest;
use App\Http\Requests\UpdateTaskChecklistItemRequest;
use App\Http\Resources\TaskChecklistItemResource;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use Illuminate\Validation\ValidationException;

class TaskChecklistItemController extends Controller
{
    private const MAX_ITEMS = 50;

    // Daftar tidak dipaginasi karena dibatasi MAX_ITEMS per task.
    public function index(Task $task)
    {
        $this->authorize('view', $task);

        return TaskChecklistItemResource::collection($task->checklistItems()->orderBy('id')->get());
    }

    public function store(StoreTaskChecklistItemRequest $request, Task $task)
    {
        if ($task->checklistItems()->count() >= self::MAX_ITEMS) {
            throw ValidationException::withMessages([
                'title' => ['Checklist maksimal '.self::MAX_ITEMS.' item per task.'],
            ]);
        }

        return new TaskChecklistItemResource($task->checklistItems()->create($request->validated()));
    }

    public function update(UpdateTaskChecklistItemRequest $request, TaskChecklistItem $checklist)
    {
        $checklist->update($request->validated());

        return new TaskChecklistItemResource($checklist);
    }

    public function destroy(TaskChecklistItem $checklist)
    {
        $this->authorize('update', $checklist->task);

        $checklist->delete();

        return response()->noContent();
    }
}
