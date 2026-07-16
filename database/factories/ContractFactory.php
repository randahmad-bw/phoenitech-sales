<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        return [
            'contract_number' => 'CNT-' . $this->faker->year . '-' . $this->faker->unique()->numberBetween(1000, 9999),
            'company_id' => Company::factory(),
            'employee_id' => Employee::factory(),
            'service_id' => Service::factory(),
            'contract_value' => $this->faker->randomFloat(2, 1000, 50000),
            'currency' => 'USD',
            'start_date' => $this->faker->date,
            'end_date' => $this->faker->date,
            'status' => 'draft',
            'progress_percentage' => $this->faker->numberBetween(0, 100),
            'notes' => $this->faker->sentence,
        ];
    }
}
