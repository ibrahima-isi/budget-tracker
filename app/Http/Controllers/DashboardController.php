<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard)
    {
        $user = Auth::user();

        // Period defaults to current month/year; query params override.
        $month = $request->query('month') ? (int) $request->query('month') : now()->month;
        $year  = $request->query('year')  ? (int) $request->query('year')  : now()->year;

        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        // Currency filter closure applied to any query builder.
        $currencyFilter = fn ($q) => $currency !== 'all' ? $q->where('currency_code', $currency) : $q;

        $monthly = $dashboard->monthly($user, $month, $year, $currencyFilter);
        $annual  = $dashboard->annual($user, $year, $currencyFilter);
        $recentExpenses = $dashboard->recentExpenses($user, $currencyFilter);

        return Inertia::render('Dashboard', [
            'monthly'        => $monthly,
            'annual'         => $annual,
            'recentExpenses' => $recentExpenses,
            'month'          => $month,
            'year'           => $year,
            'filters'        => [
                'month'    => $month,
                'year'     => $year,
                'currency' => $currency,
            ],
        ]);
    }
}
