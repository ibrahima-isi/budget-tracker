<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Rename tables ──────────────────────────────────────────────────
        Schema::rename('depenses', 'expenses');
        Schema::rename('revenus', 'revenues');
        Schema::rename('categorie_user_settings', 'category_user_settings');

        // ── 2. Rename columns in `categories` ─────────────────────────────────
        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('nom',    'name');
            $table->renameColumn('couleur', 'color');
            $table->renameColumn('icone',  'icon');
        });

        // ── 3. Rename columns in `budgets` ────────────────────────────────────
        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('mois',          'month');
            $table->renameColumn('annee',         'year');
            $table->renameColumn('montant_prevu', 'planned_amount');
            $table->renameColumn('libelle',       'label');
            $table->renameColumn('categorie_id',  'category_id');
        });

        // ── 4. Rename columns in `expenses` (was depenses) ────────────────────
        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('categorie_id',  'category_id');
            $table->renameColumn('libelle',       'label');
            $table->renameColumn('montant',       'amount');
            $table->renameColumn('date_depense',  'expense_date');
        });

        // ── 5. Rename columns in `revenues` (was revenus) ─────────────────────
        Schema::table('revenues', function (Blueprint $table) {
            $table->renameColumn('montant',     'amount');
            $table->renameColumn('date_revenu', 'revenue_date');
            $table->renameColumn('mois',        'month');
            $table->renameColumn('annee',       'year');
        });

        // ── 6. Rename columns in `category_user_settings` ─────────────────────
        Schema::table('category_user_settings', function (Blueprint $table) {
            $table->renameColumn('categorie_id', 'category_id');
        });
    }

    public function down(): void
    {
        Schema::table('category_user_settings', function (Blueprint $table) {
            $table->renameColumn('category_id', 'categorie_id');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->renameColumn('amount',       'montant');
            $table->renameColumn('revenue_date', 'date_revenu');
            $table->renameColumn('month',        'mois');
            $table->renameColumn('year',         'annee');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->renameColumn('category_id',  'categorie_id');
            $table->renameColumn('label',        'libelle');
            $table->renameColumn('amount',       'montant');
            $table->renameColumn('expense_date', 'date_depense');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('month',          'mois');
            $table->renameColumn('year',           'annee');
            $table->renameColumn('planned_amount', 'montant_prevu');
            $table->renameColumn('label',          'libelle');
            $table->renameColumn('category_id',   'categorie_id');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('name',  'nom');
            $table->renameColumn('color', 'couleur');
            $table->renameColumn('icon',  'icone');
        });

        Schema::rename('category_user_settings', 'categorie_user_settings');
        Schema::rename('revenues', 'revenus');
        Schema::rename('expenses', 'depenses');
    }
};
