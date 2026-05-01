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
        // With no period filters, default to the current month/year. Once a
        // period filter is present, omitted or "all" values mean no limit for
        // that dimension, e.g. ?year=2025 means the whole year.
        $hasPeriodFilter = $request->has('month') || $request->has('year');

        $month = $hasPeriodFilter ? null : now()->month;
        if ($request->has('month')) {
            $val = $request->query('month');
            $month = ($val === 'all' || $val === '' || $val === '0') ? null : (int) $val;
        }

        $year = $hasPeriodFilter ? null : now()->year;
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
