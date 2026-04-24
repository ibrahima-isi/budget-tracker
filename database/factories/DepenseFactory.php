<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @deprecated Use ExpenseFactory */
class DepenseFactory extends Factory
{
    protected $model = Depense::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'budget_id'    => Budget::factory(),
            'category_id'  => Category::factory(),
            'label'        => $this->faker->sentence(3),
            'amount'       => $this->faker->randomFloat(2, 1000, 50000),
            'expense_date' => $this->faker->date('Y-m-d'),
            'note'         => $this->faker->optional()->sentence(),
            'currency_code' => 'XOF',
        ];
    }
}
