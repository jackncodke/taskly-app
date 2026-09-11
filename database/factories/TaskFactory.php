<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\TaskStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'short_description' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'due_at' => fake()->dateTimeBetween('now', '+1 month'),
            'tags' => fake()->randomElements(['urgente', 'bug', 'backend', 'ui'], 2),
            'status' => TaskStatus::NotStarted,
        ];
    }

    /**
     * A task sitting at the given status.
     */
    public function status(TaskStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    /**
     * A task with only the required field filled in.
     */
    public function minimal(): static
    {
        return $this->state(fn (): array => [
            'short_description' => null,
            'description' => null,
            'due_at' => null,
            'tags' => [],
        ]);
    }
}
