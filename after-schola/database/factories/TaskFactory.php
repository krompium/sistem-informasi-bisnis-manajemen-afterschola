<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'assignee_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'status' => 'todo',
            'progress' => 0,
            'due_date' => now()->addWeek()->toDateString(),
        ];
    }
}
