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
        $month  = $request->query('month')  ? (int) $request->query('month')  : null;
        $year   = $request->query('year')   ? (int) $request->query('year')   : null;

        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        return compact('month', 'year', 'currency');
    }
}
