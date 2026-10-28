<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

// Notifikasi dalam aplikasi (database) untuk assignee task: ditugaskan, dikomentari, tenggat diubah.
class TaskActivityNotification extends Notification
{
    public const ASSIGNED = 'assigned';

    public const COMMENTED = 'commented';

    public const DUE_DATE_CHANGED = 'due_date_changed';

    public function __construct(
        private Task $task,
        private User $actor,
        private string $kind,
        private string $message,
    ) {}

    /** Beri tahu assignee task — kecuali assignee-nya adalah orang yang melakukan aksi itu sendiri. */
    public static function notifyAssignee(Task $task, User $actor, string $kind, string $message): void
    {
        if (! $task->assignee_id || (int) $task->assignee_id === (int) $actor->id) {
            return;
        }

        User::find($task->assignee_id)?->notify(new self($task, $actor, $kind, $message));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'message' => $this->message,
            'task_id' => $this->task->id,
            'project_id' => $this->task->project_id,
            'task_title' => $this->task->title,
            'actor_name' => $this->actor->name,
        ];
    }
}
