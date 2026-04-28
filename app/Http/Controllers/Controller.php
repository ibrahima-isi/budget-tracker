<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

abstract class Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

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
}
