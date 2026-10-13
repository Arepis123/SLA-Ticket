<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement(['HR Portal', 'Inventory System', 'E-Commerce Site', 'Booking App', 'Finance Dashboard', 'CRM', 'Payroll System']);

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'code' => Str::upper(Str::substr(Str::replace(' ', '', $name), 0, 4)),
            'url' => fake()->url(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
