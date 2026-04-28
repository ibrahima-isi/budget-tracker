<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevenueRequest;
use App\Http\Requests\UpdateRevenueRequest;
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RevenueController extends Controller
{
    const PER_PAGE = 20;

    public function index(Request $request)
    {
        ['month' => $month, 'year' => $year, 'currency' => $currency] = $this->resolvePeriodFilters($request);

        $query = Revenue::where('user_id', Auth::id())
            ->latest('revenue_date');

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        if ($month) {
            $query->where('month', $month);
        }

        if ($year) {
            $query->where('year', $year);
        }

        $totalAmount = (clone $query)->sum('montant');
        $revenues = $query->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Revenues/Index', [
            'revenues'    => $revenues,
            'totalAmount' => $totalAmount,
            'filters'     => ['month' => $month, 'year' => $year, 'currency' => $currency],
        ]);
    }

    public function store(StoreRevenueRequest $request)
    {
        $date = Carbon::parse($request->revenue_date);
        $data = $request->validated();
        $data['user_id']       = Auth::id();
        $data['month']         = $date->month;
        $data['year']          = $date->year;
        $data['currency_code'] ??= $this->currentCurrency();

        Revenue::create($data);

        return redirect()->back()->with('success', __('flash.revenue_created'));
    }

    public function update(UpdateRevenueRequest $request, Revenue $revenue)
    {
        $this->authorize('update', $revenue);

        $date                  = Carbon::parse($request->revenue_date);
        $data                  = $request->validated();
        $data['month']         = $date->month;
        $data['year']          = $date->year;
        $data['currency_code'] ??= $revenue->currency_code ?? $this->currentCurrency();

        $revenue->update($data);

        return redirect()->back()->with('success', __('flash.revenue_updated'));
    }

    public function destroy(Revenue $revenue)
    {
        $this->authorize('delete', $revenue);

        $revenue->delete();

        return redirect()->back()->with('success', __('flash.revenue_deleted'));
    }
}
