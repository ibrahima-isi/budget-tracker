<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate([], [
            'business_name'    => 'Mon Entreprise',
            'language'         => 'fr',
            'default_currency' => 'XOF',
        ]);
    }
}
