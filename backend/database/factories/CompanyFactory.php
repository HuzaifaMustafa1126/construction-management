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
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => fake()->country(),
            'currency' => 'PKR',
            'timezone' => 'Asia/Karachi',
            'subscription_status' => Company::SUBSCRIPTION_STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(14),
            'status' => Company::STATUS_ACTIVE,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => Company::STATUS_ACTIVE,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => Company::STATUS_SUSPENDED,
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'subscription_status' => Company::SUBSCRIPTION_STATUS_TRIAL,
            'trial_ends_at' => now()->addDays(14),
            'status' => Company::STATUS_ACTIVE,
        ]);
    }
}
