<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user      = User::first();
        $budgets   = Budget::where('type', 'mensuel')->get();
        $categories = Categorie::all();

        foreach ($budgets as $budget) {
            // 5 dépenses par budget mensuel
            for ($i = 0; $i < 5; $i++) {
                $cat = $categories->random();
                Depense::create([
                    'user_id'      => $user->id,
                    'budget_id'    => $budget->id,
                    'categorie_id' => $cat->id,
                    'libelle'      => "Dépense test - {$cat->nom}",
                    'montant'      => rand(5000, 50000),
                    'date_depense' => "{$budget->annee}-{$budget->mois}-" . rand(1, 28),
                    'note'         => null,
                ]);
            }
        }
    }
}
