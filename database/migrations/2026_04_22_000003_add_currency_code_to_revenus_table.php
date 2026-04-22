<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenus', function (Blueprint $table) {
            $table->string('currency_code', 10)->nullable()->after('montant');
        });

        $default = DB::table('settings')->value('default_currency') ?? 'XOF';
        DB::table('revenus')->whereNull('currency_code')->update(['currency_code' => $default]);
    }

    public function down(): void
    {
        Schema::table('revenus', function (Blueprint $table) {
            $table->dropColumn('currency_code');
        });
    }
};
