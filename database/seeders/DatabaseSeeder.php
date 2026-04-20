<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            SettingSeeder::class,
            CategorieSeeder::class,
        ]);

        // Créer un user de test avec données réalistes
        $user = User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([BudgetSeeder::class, RevenuSeeder::class, DepenseSeeder::class]);
    }
}
