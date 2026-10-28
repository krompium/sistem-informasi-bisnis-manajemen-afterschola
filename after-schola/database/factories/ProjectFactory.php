<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'status' => 'planning',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
        ];
    }
}
