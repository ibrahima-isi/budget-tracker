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
        Schema::create('revenus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 150);           // ex: "Salaire", "Freelance"
            $table->decimal('montant', 12, 2);
            $table->date('date_revenu');
            $table->unsignedTinyInteger('mois');     // 1-12 (déduit de date_revenu)
            $table->unsignedSmallInteger('annee');   // (déduit de date_revenu)
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenus');
    }
};
