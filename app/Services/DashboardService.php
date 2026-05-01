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
    public function monthly(User $user, ?int $month, ?int $year, string $currency): array
    {
        return Cache::remember(
            AppCache::financeKey($user->id, 'dashboard:monthly', compact('month', 'year', 'currency')),
            AppCache::DASHBOARD_TTL,
            fn () => $this->buildMonthly($user, $month, $year, $currency),
        );
    }

    public function annual(User $user, ?int $year, string $currency): array
    {
        return Cache::remember(
            AppCache::financeKey($user->id, 'dashboard:annual', [
                'month' => null,
                'year' => $year,
                'currency' => $currency,
            ]),
            AppCache::DASHBOARD_TTL,
            fn () => $this->buildAnnual($user, $year, $currency),
        );
    }

    public function recentExpenses(User $user, string $currency, ?int $month = null, ?int $year = null): Collection
    {
        return Cache::remember(
            AppCache::financeKey($user->id, 'dashboard:recent-expenses', compact('month', 'year', 'currency')),
            AppCache::DASHBOARD_TTL,
            fn () => $this->applyDatePeriod(
                $this->applyCurrency(Expense::where('user_id', $user->id), $currency),
                'expense_date',
                $month,
                $year,
            )
                ->select(['id', 'category_id', 'label', 'amount', 'expense_date', 'currency_code'])
                ->with('category:id,name,color')
                ->latest('expense_date')
                ->take(5)
                ->get(),
        );
    }

    private function buildMonthly(User $user, ?int $month, ?int $year, string $currency): array
    {
        $budgetQuery = $this->applyCurrency(Budget::where('user_id', $user->id), $currency)
            ->where('type', 'mensuel');

        if ($month) {
            $budgetQuery->where('month', $month);
        }

        if ($year) {
            $budgetQuery->where('year', $year);
        }

        $totalBudget = $budgetQuery->sum('planned_amount');

        $revenueQuery = $this->applyCurrency(Revenue::where('user_id', $user->id), $currency);

        if ($month) {
            $revenueQuery->where('month', $month);
        }

        if ($year) {
            $revenueQuery->where('year', $year);
        }

        $totalRevenues = $revenueQuery->sum('amount');

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

    private function buildAnnual(User $user, ?int $year, string $currency): array
    {
        $budgetTotals = $this->applyCurrency(Budget::where('user_id', $user->id), $currency)
            ->when($year, fn ($query) => $query->where('year', $year))
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
            ->when($year, fn ($query) => $query->where('year', $year))
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

        if ($month) {
            $bounds = (clone $query)
                ->setEagerLoads([])
                ->reorder()
                ->select([])
                ->selectRaw("MIN({$column}) as min_date, MAX({$column}) as max_date")
                ->first();

            if (! $bounds?->min_date || ! $bounds?->max_date) {
                return $query->where($column, '<', '0001-01-01');
            }

            return $query->where(function ($q) use ($column, $month, $bounds) {
                $startYear = Carbon::parse($bounds->min_date)->year;
                $endYear = Carbon::parse($bounds->max_date)->year;

                foreach (range($startYear, $endYear) as $candidateYear) {
                    $start = Carbon::create($candidateYear, $month, 1)->startOfDay();

                    $q->orWhere(function ($q2) use ($column, $start) {
                        $q2->where($column, '>=', $start->toDateString())
                            ->where($column, '<', $start->copy()->addMonth()->toDateString());
                    });
                }
            });
        }

        return $query;
    }
}
