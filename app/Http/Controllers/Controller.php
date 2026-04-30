<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Return the currency code the current user has selected (from session),
     * falling back to the app default from Settings.
     */
    protected function currentCurrency(): string
    {
        return session('current_currency')
            ?: (Setting::instance()->default_currency ?? 'XOF');
    }

    /**
     * Extract period and currency filters from the request.
     * Returns ['month' => int|null, 'year' => int|null, 'currency' => string].
     */
    protected function resolvePeriodFilters(Request $request): array
    {
        // If the parameter is missing, we default to current month/year.
        // If it's explicitly 'all' (passed as empty string in some cases or we can use 0), we use null.

        $month = now()->month;
        if ($request->has('month')) {
            $val = $request->query('month');
            $month = ($val === 'all' || $val === '' || $val === '0') ? null : (int) $val;
        }

        $year = now()->year;
        if ($request->has('year')) {
            $val = $request->query('year');
            $year = ($val === 'all' || $val === '' || $val === '0') ? null : (int) $val;
        }

        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        return compact('month', 'year', 'currency');
    }

    /**
     * Apply period filters without wrapping indexed date columns in SQL functions
     * when a year is present. This keeps Postgres/Neon able to use btree indexes.
     */
    protected function applyDatePeriodFilter($query, string $column, ?int $month, ?int $year)
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
            return $query->whereMonth($column, $month);
        }

        return $query;
    }
}
