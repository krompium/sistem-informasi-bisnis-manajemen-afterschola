<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TaskUpdate> */
class TaskUpdateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'note' => fake()->sentence(),
        ];
    }
}
