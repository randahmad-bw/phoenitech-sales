<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'name' => $this->faker->company,
            'activity' => $this->faker->jobTitle,
            'address' => $this->faker->address,
            'notes' => $this->faker->sentence,
        ];
    }
}
