<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function monthly(User $user, int $month, int $year, string $currency): array
    {
        return Cache::remember(
            AppCache::financeKey($user->id, 'dashboard:monthly', compact('month', 'year', 'currency')),
            AppCache::DASHBOARD_TTL,
            fn () => $this->buildMonthly($user, $month, $year, $currency),
        );
    }

    public function annual(User $user, int $year, string $currency): array
    {
        return Cache::remember(
            AppCache::financeKey($user->id, 'dashboard:annual', compact('year', 'currency')),
            AppCache::DASHBOARD_TTL,
            fn () => $this->buildAnnual($user, $year, $currency),
        );
    }

    public function recentExpenses(User $user, string $currency): Collection
    {
        return Cache::remember(
            AppCache::financeKey($user->id, 'dashboard:recent-expenses', compact('currency')),
            AppCache::DASHBOARD_TTL,
            fn () => $this->applyCurrency(Expense::where('user_id', $user->id), $currency)
                ->with('category:id,name,color')
                ->latest('expense_date')
                ->take(5)
                ->get(),
        );
    }

    private function buildMonthly(User $user, int $month, int $year, string $currency): array
    {
        $totalBudget = $this->applyCurrency(Budget::where('user_id', $user->id), $currency)
            ->where('type', 'mensuel')
            ->where('month', $month)
            ->where('year', $year)
            ->sum('planned_amount');

        $totalRevenues = $this->applyCurrency(Revenue::where('user_id', $user->id), $currency)
            ->where('month', $month)
            ->where('year', $year)
            ->sum('amount');

        $expensesByCategory = $this->applyDatePeriod(
            $this->applyCurrency(Expense::where('user_id', $user->id), $currency),
            'expense_date',
            $month,
            $year,
        )
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'total' => (float) $item->total,
            ])
            ->values();
        $totalExpenses = $expensesByCategory->sum('total');

        $balance = (float) $totalRevenues - (float) $totalExpenses;

        return [
            'totalBudget' => (float) $totalBudget,
            'totalExpenses' => (float) $totalExpenses,
            'totalRevenues' => (float) $totalRevenues,
            'balance' => $balance,
            'expensesByCategory' => $expensesByCategory,
        ];
    }

    private function buildAnnual(User $user, int $year, string $currency): array
    {
        $budgetTotals = $this->applyCurrency(Budget::where('user_id', $user->id), $currency)
            ->where('year', $year)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'mensuel' THEN planned_amount ELSE 0 END), 0) as monthly
            ")
            ->selectRaw("
                COALESCE(SUM(CASE WHEN type = 'annuel' THEN planned_amount ELSE 0 END), 0) as annual
            ")
            ->first();

        $totalMonthlyBudget = (float) ($budgetTotals->monthly ?? 0);
        $totalAnnualBudget = (float) ($budgetTotals->annual ?? 0);

        $totalRevenues = $this->applyCurrency(Revenue::where('user_id', $user->id), $currency)
            ->where('year', $year)
            ->sum('amount');

        $expensesByCategory = $this->applyDatePeriod(
            $this->applyCurrency(Expense::where('user_id', $user->id), $currency),
            'expense_date',
            null,
            $year,
        )
            ->selectRaw('category_id, SUM(amount) as total')
            ->groupBy('category_id')
            ->with('category:id,name,color')
            ->get()
            ->map(fn ($item) => [
                'category' => $item->category,
                'total' => (float) $item->total,
            ])
            ->values();
        $totalExpenses = $expensesByCategory->sum('total');

        return [
            'totalBudget' => $totalMonthlyBudget + $totalAnnualBudget,
            'totalMonthlyBudget' => $totalMonthlyBudget,
            'totalAnnualBudget' => $totalAnnualBudget,
            'totalExpenses' => (float) $totalExpenses,
            'totalRevenues' => (float) $totalRevenues,
            'balance' => (float) $totalRevenues - (float) $totalExpenses,
            'expensesByCategory' => $expensesByCategory,
        ];
    }

    private function applyCurrency($query, string $currency)
    {
        return $currency !== 'all' ? $query->where('currency_code', $currency) : $query;
    }

    private function applyDatePeriod($query, string $column, ?int $month, ?int $year)
    {
        if ($month && $year) {
            $start = Carbon::create($year, $month, 1)->startOfDay();

            return $query
                ->where($column, '>=', $start->toDateString())
                ->where($column, '<', $start->copy()->addMonth()->toDateString());
        }

        if ($year) {
            $start = Carbon::create($year, 1, 1)->startOfDay();

            return $query
                ->where($column, '>=', $start->toDateString())
                ->where($column, '<', $start->copy()->addYear()->toDateString());
        }

        return $query;
    }
}
