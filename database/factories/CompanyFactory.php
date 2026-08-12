<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'currency_code' => 'USD',
            'timezone' => 'UTC',
            'is_active' => true,
            // Companies created through this factory (every test's createCompanyWithMainBranch())
            // need to be immediately usable — 'pending' (the schema default, meant for the new
            // platform-onboarding flow) would fail every login/company-active check in existing
            // tests that don't know or care about the new platform-admin feature.
            'status' => 'active',
        ];
    }
}
