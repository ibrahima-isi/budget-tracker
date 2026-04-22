<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('???'));

        return [
            'code'       => $code,
            'name'       => $this->faker->country() . ' Currency',
            'symbol'     => $this->faker->lexify('??'),
            'is_default' => false,
            'is_active'  => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
