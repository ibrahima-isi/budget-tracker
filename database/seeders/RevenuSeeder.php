<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RevenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();

        for ($mois = 1; $mois <= 4; $mois++) {
            Revenu::create([
                'user_id'     => $user->id,
                'source'      => 'Salaire',
                'montant'     => 450000,
                'date_revenu' => "2025-$mois-01",
                'mois'        => $mois,
                'annee'       => 2025,
            ]);
        }
    }
}
