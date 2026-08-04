<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->city().' Branch',
            'code' => strtoupper(fake()->unique()->bothify('BR-###')),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'is_main' => false,
            'is_active' => true,
        ];
    }
}
