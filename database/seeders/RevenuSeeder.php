<?php

namespace Database\Seeders;

use App\Models\Revenue;
use App\Models\User;
use Illuminate\Database\Seeder;

class RevenuSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        $year = now()->year;

        for ($month = 1; $month <= now()->month; $month++) {
            Revenue::create([
                'user_id'      => $user->id,
                'source'       => 'Salaire',
                'amount'       => 450000,
                'revenue_date' => "$year-$month-01",
                'month'        => $month,
                'year'         => $year,
            ]);
        }
    }
}
