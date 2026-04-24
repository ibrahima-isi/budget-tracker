<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;

class DashboardService
{
    public function monthly(User $user, int $month, int $year, callable $currencyFilter): array
    {
        $totalBudget = $currencyFilter(Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('month', $month)
            ->where('year', $year))
            ->sum('planned_amount');

        $totalExpenses = $currencyFilter(Expense::where('user_id', $user->id)
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year))
            ->sum('amount');

        $totalRevenues = $currencyFilter(Revenue::where('user_id', $user->id)
            ->where('month', $month)
            ->where('year', $year))
            ->sum('amount');

        $expensesByCategory = $currencyFilter(Expense::where('user_id', $user->id)
            ->whereMonth('expense_date', $month)
            ->whereYear('expense_date', $year))
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'total'    => (float) $item->total,
            ])
            ->values();

        $balance = (float) $totalRevenues - (float) $totalExpenses;

        return [
            'totalBudget'       => (float) $totalBudget,
            'totalExpenses'     => (float) $totalExpenses,
            'totalRevenues'     => (float) $totalRevenues,
            'balance'           => $balance,
            'expensesByCategory' => $expensesByCategory,
        ];
    }

    public function annual(User $user, int $year, callable $currencyFilter): array
    {
        $totalMonthlyBudget = $currencyFilter(Budget::where('user_id', $user->id)
            ->where('type', 'mensuel')
            ->where('year', $year))
            ->sum('planned_amount');

        $totalAnnualBudget = $currencyFilter(Budget::where('user_id', $user->id)
            ->where('type', 'annuel')
            ->where('year', $year))
            ->sum('planned_amount');

        $totalExpenses = $currencyFilter(Expense::where('user_id', $user->id)
            ->whereYear('expense_date', $year))
            ->sum('amount');

        $totalRevenues = $currencyFilter(Revenue::where('user_id', $user->id)
            ->where('year', $year))
            ->sum('amount');

        $expensesByCategory = $currencyFilter(Expense::where('user_id', $user->id)
            ->whereYear('expense_date', $year))
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'total'    => (float) $item->total,
            ])
            ->values();

        return [
            'totalBudget'        => (float) $totalMonthlyBudget + (float) $totalAnnualBudget,
            'totalMonthlyBudget' => (float) $totalMonthlyBudget,
            'totalAnnualBudget'  => (float) $totalAnnualBudget,
            'totalExpenses'      => (float) $totalExpenses,
            'totalRevenues'      => (float) $totalRevenues,
            'balance'            => (float) $totalRevenues - (float) $totalExpenses,
            'expensesByCategory' => $expensesByCategory,
        ];
    }

    public function recentExpenses(User $user, callable $currencyFilter): \Illuminate\Database\Eloquent\Collection
    {
        return $currencyFilter(Expense::where('user_id', $user->id))
            ->with('category:id,name,color')
            ->latest('expense_date')
            ->take(5)
            ->get();
    }
}
