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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['mensuel', 'annuel']);
            $table->unsignedTinyInteger('mois')->nullable(); // 1-12, null si annuel
            $table->unsignedSmallInteger('annee');           // ex: 2025
            $table->decimal('montant_prevu', 12, 2);
            $table->string('libelle', 150)->nullable();      // ex: "Budget Avril 2025"
            $table->timestamps();
            // Contrainte : un seul budget mensuel par user/mois/année
            // et un seul budget annuel par user/année
            $table->unique(['user_id', 'type', 'mois', 'annee'], 'unique_budget_periode');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
