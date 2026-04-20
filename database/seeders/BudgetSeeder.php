<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        // Budget annuel 2025
        Budget::create([
            'user_id'       => $user->id,
            'type'          => 'annuel',
            'mois'          => null,
            'annee'         => 2025,
            'montant_prevu' => 3600000,
            'libelle'       => 'Budget Annuel 2025',
        ]);

        // Budgets mensuels jan-avril 2025
        $mois = ['Janvier', 'Février', 'Mars', 'Avril'];
        foreach ($mois as $i => $nom) {
            Budget::create([
                'user_id'       => $user->id,
                'type'          => 'mensuel',
                'mois'          => $i + 1,
                'annee'         => 2025,
                'montant_prevu' => 300000,
                'libelle'       => "Budget $nom 2025",
            ]);
        }
    }
}
