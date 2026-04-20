<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepenseFactory extends Factory
{
    protected $model = Depense::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'budget_id'    => Budget::factory(),
            'categorie_id' => Categorie::factory(),
            'libelle'      => $this->faker->sentence(3),
            'montant'      => $this->faker->randomFloat(2, 1000, 50000),
            'date_depense' => $this->faker->date('Y-m-d'),
            'note'         => $this->faker->optional()->sentence(),
        ];
    }
}
