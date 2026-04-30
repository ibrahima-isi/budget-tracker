<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['user_id', 'currency_code', 'year', 'month'], 'budgets_user_currency_period_idx');
            $table->index(['user_id', 'created_at'], 'budgets_user_created_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'currency_code', 'expense_date'], 'expenses_user_currency_date_idx');
            $table->index(['user_id', 'budget_id', 'category_id'], 'expenses_user_budget_category_idx');
            $table->index(['budget_id', 'expense_date'], 'expenses_budget_date_idx');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->index(['user_id', 'currency_code', 'year', 'month'], 'revenues_user_currency_period_idx');
            $table->index(['user_id', 'currency_code', 'revenue_date'], 'revenues_user_currency_date_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['user_id', 'name'], 'categories_user_name_idx');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->index(['is_active', 'is_default', 'code'], 'currencies_active_default_code_idx');
        });
    }

    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropIndex('currencies_active_default_code_idx');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_user_name_idx');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->dropIndex('revenues_user_currency_date_idx');
            $table->dropIndex('revenues_user_currency_period_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_budget_date_idx');
            $table->dropIndex('expenses_user_budget_category_idx');
            $table->dropIndex('expenses_user_currency_date_idx');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_created_idx');
            $table->dropIndex('budgets_user_currency_period_idx');
        });
    }
};
