<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskItem>
 */
class TaskItemFactory extends Factory
{
    protected $model = TaskItem::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'title' => $this->faker->sentence(3),
            'position' => 1,
            'completed_at' => null,
            'completed_by' => null,
            'created_by' => null,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
