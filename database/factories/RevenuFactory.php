<?php

namespace Database\Factories;

use App\Models\Revenu;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RevenuFactory extends Factory
{
    protected $model = Revenu::class;

    public function definition(): array
    {
        $date = $this->faker->date('Y-m-d');

        return [
            'user_id'     => User::factory(),
            'source'      => $this->faker->randomElement(['Salaire', 'Freelance', 'Loyer', 'Dividendes']),
            'montant'     => $this->faker->randomFloat(2, 50000, 1000000),
            'date_revenu' => $date,
            'mois'        => (int) date('n', strtotime($date)),
            'annee'       => (int) date('Y', strtotime($date)),
            'note'        => $this->faker->optional()->sentence(),
        ];
    }
}
