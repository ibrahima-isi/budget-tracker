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
        $user  = User::first();
        $annee = now()->year;

        Budget::create([
            'user_id'       => $user->id,
            'type'          => 'annuel',
            'mois'          => null,
            'annee'         => $annee,
            'montant_prevu' => 3600000,
            'libelle'       => "Budget Annuel $annee",
        ]);

        $nomsMois = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        for ($m = 1; $m <= now()->month; $m++) {
            Budget::create([
                'user_id'       => $user->id,
                'type'          => 'mensuel',
                'mois'          => $m,
                'annee'         => $annee,
                'montant_prevu' => 300000,
                'libelle'       => "Budget {$nomsMois[$m - 1]} $annee",
            ]);
        }
    }
}
