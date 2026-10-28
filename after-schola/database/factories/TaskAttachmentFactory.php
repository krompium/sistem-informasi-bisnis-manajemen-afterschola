<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TaskAttachment> */
class TaskAttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'original_name' => 'dokumen.pdf',
            'path' => 'task-attachments/0/'.Str::uuid().'.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
