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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();

            // Who — nullable so system/guest events (failed login, etc.) can also be stored
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name', 255)->nullable();    // redacted actor reference, e.g. user#123

            // What happened
            $table->string('event', 50);                     // created|updated|deleted|login|logout|registered|password_reset
            $table->string('subject_type', 100)->nullable(); // e.g. "Budget", "Depense"
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label', 255)->nullable(); // human-readable: libelle, source, nom…

            // Diff payload  { "old": {…}, "new": {…} }
            $table->json('properties')->nullable();

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            // Only created_at — logs are never updated
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id',      'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['event',        'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
