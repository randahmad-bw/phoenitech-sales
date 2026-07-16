<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->name,
            'position' => $this->faker->jobTitle,
            'mobile' => $this->faker->phoneNumber,
            'notes' => $this->faker->sentence,
        ];
    }
}
