<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'XOF', 'name' => 'Franc CFA (BCEAO)',      'symbol' => 'FCFA', 'is_default' => true],
            ['code' => 'XAF', 'name' => 'Franc CFA (BEAC)',        'symbol' => 'FCFA', 'is_default' => false],
            ['code' => 'EUR', 'name' => 'Euro',                    'symbol' => '€',    'is_default' => false],
            ['code' => 'USD', 'name' => 'Dollar américain',        'symbol' => '$',    'is_default' => false],
            ['code' => 'GBP', 'name' => 'Livre sterling',          'symbol' => '£',    'is_default' => false],
            ['code' => 'GNF', 'name' => 'Franc guinéen',           'symbol' => 'FG',   'is_default' => false],
            ['code' => 'MAD', 'name' => 'Dirham marocain',         'symbol' => 'DH',   'is_default' => false],
            ['code' => 'NGN', 'name' => 'Naira nigérian',          'symbol' => '₦',    'is_default' => false],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(['code' => $currency['code']], $currency + ['is_active' => true]);
        }
    }
}
