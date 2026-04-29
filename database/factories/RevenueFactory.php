<?php

namespace Database\Factories;

use App\Models\Revenue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RevenueFactory extends Factory
{
    protected $model = Revenue::class;

    public function definition(): array
    {
        $date = $this->faker->date('Y-m-d');

        return [
            'user_id' => User::factory(),
            'source' => $this->faker->randomElement(['Salaire', 'Freelance', 'Loyer', 'Dividendes']),
            'amount' => $this->faker->randomFloat(2, 50000, 1000000),
            'revenue_date' => $date,
            'month' => (int) date('n', strtotime($date)),
            'year' => (int) date('Y', strtotime($date)),
            'note' => $this->faker->optional()->sentence(),
            'currency_code' => 'XOF',
        ];
    }

    public function currentPeriod(): static
    {
        return $this->state(fn () => [
            'revenue_date' => now()->format('Y-m-d'),
            'month' => now()->month,
            'year' => now()->year,
        ]);
    }
}
