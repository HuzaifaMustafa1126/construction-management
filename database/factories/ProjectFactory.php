<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('PRJ-####-??')),
            'description' => fake()->sentence(),
            'location' => fake()->city(),
            'start_date' => today(),
            'expected_end_date' => today()->addMonths(6),
            'budget' => fake()->randomFloat(2, 1000, 1000000),
            'currency' => 'PKR',
            'progress_percentage' => 0,
            'status' => 'planning',
            'created_by' => fn (array $attributes) => User::factory()->create([
                'company_id' => $attributes['company_id'], 'status' => User::STATUS_ACTIVE,
            ])->id,
        ];
    }
}
