<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Department> */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'company_id' => Company::factory(),
            'name' => Str::title($name),
            'code' => strtoupper(fake()->unique()->lexify('???')).fake()->unique()->numerify('##'),
            'description' => fake()->sentence(),
            'status' => Department::STATUS_ACTIVE,
        ];
    }
}
