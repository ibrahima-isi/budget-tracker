<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique('unique_budget_periode');
            $table->unique(['user_id', 'type', 'mois', 'annee', 'categorie_id'], 'unique_budget_categorie_periode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique('unique_budget_categorie_periode');
            $table->unique(['user_id', 'type', 'mois', 'annee'], 'unique_budget_periode');
        });
    }
};
