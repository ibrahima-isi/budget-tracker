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
            'user_id'       => User::factory(),
            'type'          => $type,
            'mois'          => $type === 'mensuel' ? $this->faker->numberBetween(1, 12) : null,
            'annee'         => $this->faker->numberBetween(2024, 2026),
            'montant_prevu' => $this->faker->randomFloat(2, 50000, 500000),
            'libelle'       => $this->faker->optional()->sentence(3),
        ];
    }

    public function mensuel(): static
    {
        return $this->state(fn () => [
            'type' => 'mensuel',
            'mois' => now()->month,
            'annee' => now()->year,
        ]);
    }

    public function annuel(): static
    {
        return $this->state(fn () => [
            'type' => 'annuel',
            'mois' => null,
            'annee' => now()->year,
        ]);
    }
}
