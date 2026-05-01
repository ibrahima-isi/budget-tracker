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

        ['month' => $month, 'year' => $year, 'currency' => $currency] = $this->resolvePeriodFilters($request);

        $monthly = $dashboard->monthly($user, $month, $year, $currency);
        $annual = $dashboard->annual($user, $year, $currency);
        $recentExpenses = $dashboard->recentExpenses($user, $currency, $month, $year);

        return Inertia::render('Dashboard', [
            'monthly' => $monthly,
            'annual' => $annual,
            'recentExpenses' => $recentExpenses,
            'month' => $month,
            'year' => $year,
            'filters' => [
                'month' => $month,
                'year' => $year,
                'currency' => $currency,
            ],
        ]);
    }
}
