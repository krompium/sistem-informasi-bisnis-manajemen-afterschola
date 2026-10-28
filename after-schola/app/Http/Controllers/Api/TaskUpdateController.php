<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskUpdateRequest;
use App\Http\Resources\TaskUpdateResource;
use App\Models\Task;
use App\Notifications\TaskActivityNotification;

class TaskUpdateController extends Controller
{
    // TODO(nanti): UI baru menampilkan halaman pertama (15 entri terbaru) — tambah "muat lebih banyak" bila perlu.
    public function index(Task $task)
    {
        $this->authorize('view', $task);

        return TaskUpdateResource::collection(
            $task->updates()->with('user')->latest()->orderByDesc('id')->paginate()
        );
    }

    public function store(StoreTaskUpdateRequest $request, Task $task)
    {
        $update = $task->updates()->create([
            'user_id' => $request->user()->id,
            'note' => $request->validated('note'),
        ]);

        TaskActivityNotification::notifyAssignee(
            $task, $request->user(), TaskActivityNotification::COMMENTED,
            "{$request->user()->name} mengomentari task yang ditugaskan kepadamu"
        );

        return new TaskUpdateResource($update->load('user'));
    }
}
