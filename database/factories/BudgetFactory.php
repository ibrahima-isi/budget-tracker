<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['mensuel', 'annuel']);

        return [
            'user_id'        => User::factory(),
            'type'           => $type,
            'month'          => $type === 'mensuel' ? $this->faker->numberBetween(1, 12) : null,
            'year'           => $this->faker->numberBetween(2024, 2026),
            'planned_amount' => $this->faker->randomFloat(2, 50000, 500000),
            'label'          => $this->faker->optional()->sentence(3),
            'currency_code'  => 'XOF',
        ];
    }

    public function mensuel(): static
    {
        return $this->state(fn () => [
            'type'  => 'mensuel',
            'month' => now()->month,
            'year'  => now()->year,
        ]);
    }

    public function annuel(): static
    {
        return $this->state(fn () => [
            'type'  => 'annuel',
            'month' => null,
            'year'  => now()->year,
        ]);
    }
}
