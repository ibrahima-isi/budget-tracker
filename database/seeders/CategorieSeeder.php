<?php

namespace Database\Seeders;

use App\Models\Categorie;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['nom' => 'Alimentation',  'couleur' => '#22c55e', 'icone' => 'shopping-cart'],
            ['nom' => 'Transport',     'couleur' => '#3b82f6', 'icone' => 'truck'],
            ['nom' => 'Logement',      'couleur' => '#8b5cf6', 'icone' => 'home'],
            ['nom' => 'Santé',         'couleur' => '#ef4444', 'icone' => 'heart'],
            ['nom' => 'Loisirs',       'couleur' => '#f97316', 'icone' => 'sparkles'],
            ['nom' => 'Éducation',     'couleur' => '#eab308', 'icone' => 'academic-cap'],
            ['nom' => 'Vêtements',     'couleur' => '#ec4899', 'icone' => 'tag'],
            ['nom' => 'Épargne',       'couleur' => '#14b8a6', 'icone' => 'banknotes'],
            ['nom' => 'Factures',      'couleur' => '#64748b', 'icone' => 'document-text'],
            ['nom' => 'Autres',        'couleur' => '#94a3b8', 'icone' => 'ellipsis-horizontal'],
        ];

        foreach ($categories as $cat) {
            Categorie::firstOrCreate(['nom' => $cat['nom']], $cat);
        }
    }
}
