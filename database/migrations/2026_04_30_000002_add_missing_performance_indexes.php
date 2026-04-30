<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'expense_date'], 'expenses_user_date_idx');
            $table->index(['user_id', 'budget_id'], 'expenses_user_budget_idx');
            $table->index(['user_id', 'category_id'], 'expenses_user_category_idx');
            $table->index(['budget_id'], 'expenses_budget_id_idx');
            $table->index(['category_id'], 'expenses_category_id_idx');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->index(['user_id', 'year', 'month'], 'revenues_user_period_idx');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->index(['user_id', 'currency_code', 'year', 'month', 'type'], 'budgets_user_currency_period_type_idx');
            $table->index(['user_id', 'year', 'month', 'type'], 'budgets_user_period_type_idx');
        });

        if (
            Schema::hasColumn('categories', 'user_id')
            && Schema::hasColumn('categories', 'enabled')
            && Schema::hasColumn('categories', 'name')
        ) {
            Schema::table('categories', function (Blueprint $table) {
                $table->index(['user_id', 'enabled', 'name'], 'categories_user_enabled_name_idx');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('categories', 'user_id')
            && Schema::hasColumn('categories', 'enabled')
            && Schema::hasColumn('categories', 'name')
        ) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropIndex('categories_user_enabled_name_idx');
            });
        }

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex('budgets_user_period_type_idx');
            $table->dropIndex('budgets_user_currency_period_type_idx');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->dropIndex('revenues_user_period_idx');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_category_id_idx');
            $table->dropIndex('expenses_budget_id_idx');
            $table->dropIndex('expenses_user_category_idx');
            $table->dropIndex('expenses_user_budget_idx');
            $table->dropIndex('expenses_user_date_idx');
        });
    }
};
