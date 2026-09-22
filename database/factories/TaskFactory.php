<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph,
            'assigned_to' => Employee::factory(),
            'created_by' => User::factory(),
            'status' => Task::STATUS_TODO,
            'priority' => 'normal',
            'due_date' => now()->addDays(3)->toDateString(),
        ];
    }

    /** Open and past its date — the state the board exists to surface. */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => Task::STATUS_TODO,
            'due_date' => now()->subDays(2)->toDateString(),
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => Task::STATUS_DONE,
            'started_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }
}
