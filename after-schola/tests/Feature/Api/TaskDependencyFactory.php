<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskDependency> */
class TaskDependencyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'depends_on_id' => Task::factory(),
        ];
    }
}
