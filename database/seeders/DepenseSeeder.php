<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Seeder;

class DepenseSeeder extends Seeder
{
    public function run(): void
    {
        $user       = User::first();
        $budgets    = Budget::where('type', 'mensuel')->get();
        $categories = Category::all();

        foreach ($budgets as $budget) {
            for ($i = 0; $i < 5; $i++) {
                $cat = $categories->random();
                Expense::create([
                    'user_id'      => $user->id,
                    'budget_id'    => $budget->id,
                    'category_id'  => $cat->id,
                    'label'        => "Dépense test - {$cat->name}",
                    'amount'       => rand(5000, 50000),
                    'expense_date' => "{$budget->year}-{$budget->month}-" . rand(1, 28),
                    'note'         => null,
                ]);
            }
        }
    }
}
