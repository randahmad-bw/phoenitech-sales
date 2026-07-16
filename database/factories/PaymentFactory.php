<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'payment_date' => $this->faker->date,
            'method' => 'cash',
            'status' => 'paid',
            'notes' => $this->faker->sentence,
        ];
    }
}
