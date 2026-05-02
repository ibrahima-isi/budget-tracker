<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'user_name' => 'user#'.$this->faker->numberBetween(1, 9999),
            'event' => $this->faker->randomElement(['created', 'updated', 'deleted', 'login', 'logout']),
            'subject_type' => $this->faker->randomElement(['Budget', 'Revenu', 'Depense', 'Categorie', null]),
            'subject_id' => $this->faker->optional()->randomNumber(),
            'subject_label' => $this->faker->optional()->word(),
            'properties' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
