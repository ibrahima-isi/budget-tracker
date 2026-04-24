<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Alimentation',  'color' => '#22c55e', 'icon' => 'shopping-cart'],
            ['name' => 'Transport',     'color' => '#3b82f6', 'icon' => 'truck'],
            ['name' => 'Logement',      'color' => '#8b5cf6', 'icon' => 'home'],
            ['name' => 'Santé',         'color' => '#ef4444', 'icon' => 'heart'],
            ['name' => 'Loisirs',       'color' => '#f97316', 'icon' => 'sparkles'],
            ['name' => 'Éducation',     'color' => '#eab308', 'icon' => 'academic-cap'],
            ['name' => 'Vêtements',     'color' => '#ec4899', 'icon' => 'tag'],
            ['name' => 'Épargne',       'color' => '#14b8a6', 'icon' => 'banknotes'],
            ['name' => 'Factures',      'color' => '#64748b', 'icon' => 'document-text'],
            ['name' => 'Autres',        'color' => '#94a3b8', 'icon' => 'ellipsis-horizontal'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
