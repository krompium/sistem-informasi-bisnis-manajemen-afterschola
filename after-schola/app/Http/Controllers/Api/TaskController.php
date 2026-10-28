<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        // Board memuat semua task satu proyek sekaligus, jadi ukuran halaman boleh diubah (maks 100).
        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        // Filter opsional: prioritas, dan rentang tenggat (dipakai kalender; batas bawah dan atas termasuk).
        // whereDate: di SQLite (test) kolom date disimpan sebagai 'Y-m-d 00:00:00', jadi '<=' biasa salah di batas atas.
        $filters = $request->validate([
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'category' => ['nullable', Rule::enum(TaskCategory::class)],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date', 'after_or_equal:due_from'],
        ]);

        return TaskResource::collection(
            Task::visibleTo($request->user())
                ->with('assignee')
                ->withCount($this->cardCounts())
                ->when($request->query('project_id'), fn ($q, $projectId) => $q->where('project_id', $projectId))
                ->when($filters['priority'] ?? null, fn ($q, $priority) => $q->where('priority', $priority))
                ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
                ->when($filters['due_from'] ?? null, fn ($q, $from) => $q->whereDate('due_date', '>=', $from))
                ->when($filters['due_to'] ?? null, fn ($q, $to) => $q->whereDate('due_date', '<=', $to))
                ->orderBy('id')
                ->paginate($perPage)
        );
    }

    public function store(StoreTaskRequest $request)
    {
        $task = Task::create($request->validated());

        TaskActivityNotification::notifyAssignee(
            $task, $request->user(), TaskActivityNotification::ASSIGNED, "{$request->user()->name} menugaskan task kepadamu"
        );

        return new TaskResource($task->load('assignee')->loadCount($this->cardCounts()));
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load('assignee')->loadCount($this->cardCounts()));
    }

    // Status dan progres diubah lewat sini (tanpa endpoint khusus pindah kolom).
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $before = $task->only(['status', 'progress', 'assignee_id', 'due_date']);

        // Update dan log satu transaksi: log tidak boleh tertinggal bila salah satunya gagal.
        DB::transaction(function () use ($request, $task, $before) {
            $task->update($request->validated());
            $this->logChanges($task, $before, $request->user());
            $this->notifyChanges($task, $before, $request->user());
        });

        return new TaskResource($task->load('assignee')->loadCount($this->cardCounts()));
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();
        // Baris lampiran ikut terhapus lewat cascade; file di disk dihapus di sini.
        Storage::disk('local')->deleteDirectory("task-attachments/{$task->id}");

        return response()->noContent();
    }

    // Assignee diberi tahu bila ditugaskan atau tenggatnya diubah orang lain (bukan dirinya sendiri).
    private function notifyChanges(Task $task, array $before, User $actor): void
    {
        if ((int) $before['assignee_id'] !== (int) $task->assignee_id) {
            TaskActivityNotification::notifyAssignee(
                $task, $actor, TaskActivityNotification::ASSIGNED, "{$actor->name} menugaskan task kepadamu"
            );
        } elseif ($before['due_date']->toDateString() !== $task->due_date->toDateString()) {
            TaskActivityNotification::notifyAssignee(
                $task, $actor, TaskActivityNotification::DUE_DATE_CHANGED,
                "{$actor->name} mengubah tenggat menjadi {$task->due_date->format('d/m/Y')}"
            );
        }
    }

    /** Angka untuk kartu di board: checklist (total dan selesai), jumlah lampiran, dan prasyarat yang belum selesai. */
    private function cardCounts(): array
    {
        return ['checklistItems', 'checklistItems as checklist_done_count' => fn ($q) => $q->where('is_done', true), 'attachments', 'dependencies as blocked_count' => fn ($q) => $q->whereHas('prerequisite', fn ($p) => $p->where('status', '!=', 'done'))];
    }

    // Hanya status, progres, dan assignee yang dicatat.
    // TODO(nanti): ekstrak ke class sendiri saat webhook GitHub ikut menulis log (pemakaian kedua).
    private function logChanges(Task $task, array $before, User $user): void
    {
        $notes = [];

        if ($before['status'] !== $task->status) {
            $notes[] = "Status: {$before['status']->value} → {$task->status->value}";
        }

        if ($before['progress'] !== $task->progress) {
            $notes[] = "Progress: {$before['progress']}% → {$task->progress}%";
        }

        if ((int) $before['assignee_id'] !== (int) $task->assignee_id) {
            $names = User::whereIn('id', array_filter([$before['assignee_id'], $task->assignee_id]))->pluck('name', 'id');
            $label = fn ($id) => $id ? ($names[$id] ?? 'tanpa assignee') : 'tanpa assignee';
            $notes[] = 'Assignee: '.$label($before['assignee_id']).' → '.$label($task->assignee_id);
        }

        foreach ($notes as $note) {
            $task->updates()->create(['user_id' => $user->id, 'note' => $note]);
        }
    }
}
