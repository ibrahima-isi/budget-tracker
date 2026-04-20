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

        // Créer un user admin de test
        $user = User::factory()->create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'is_admin' => true,
        ]);

        $this->call([BudgetSeeder::class, RevenuSeeder::class, DepenseSeeder::class]);
    }
}
