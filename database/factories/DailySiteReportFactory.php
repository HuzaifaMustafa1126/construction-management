<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DailySiteReport;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DailySiteReport> */
class DailySiteReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'project_id' => function (array $attributes) {
                return Project::factory()->create(['company_id' => $attributes['company_id']])->id;
            },
            'report_date' => fake()->unique()->date(),
            'title' => fake()->sentence(4),
            'work_completed' => fake()->paragraph(),
            'total_workers_present' => 0,
            'status' => 'draft',
            'is_client_visible' => false,
            'created_by' => function (array $attributes) {
                return Project::findOrFail($attributes['project_id'])->created_by;
            },
        ];
    }
}
