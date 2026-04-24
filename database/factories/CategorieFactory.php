<?php

namespace Database\Factories;

use App\Models\Categorie;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @deprecated Use CategoryFactory */
class CategorieFactory extends Factory
{
    protected $model = Categorie::class;

    public function definition(): array
    {
        return [
            'name'  => $this->faker->unique()->word(),
            'color' => $this->faker->hexColor(),
            'icon'  => $this->faker->randomElement(['shopping-cart', 'home', 'car', 'briefcase', 'heart']),
        ];
    }
}
