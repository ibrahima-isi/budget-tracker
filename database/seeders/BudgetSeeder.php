<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Seeder;

class BudgetSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $year = now()->year;

        Budget::create([
            'user_id'        => $user->id,
            'type'           => 'annuel',
            'month'          => null,
            'year'           => $year,
            'planned_amount' => 3600000,
            'label'          => "Budget Annuel $year",
        ]);

        $monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        for ($m = 1; $m <= now()->month; $m++) {
            Budget::create([
                'user_id'        => $user->id,
                'type'           => 'mensuel',
                'month'          => $m,
                'year'           => $year,
                'planned_amount' => 300000,
                'label'          => "Budget {$monthNames[$m - 1]} $year",
            ]);
        }
    }
}
