<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRevenuRequest;
use App\Http\Requests\UpdateRevenuRequest;
use App\Models\Revenu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class RevenuController extends Controller
{
    public function index(Request $request)
    {
        $currency = $this->currentCurrency();

        $revenus = Revenu::where('user_id', Auth::id())
            ->where('currency_code', $currency)
            ->latest('date_revenu')
            ->paginate(20);

        return Inertia::render('Revenus/Index', [
            'revenus' => $revenus,
        ]);
    }

    public function store(StoreRevenuRequest $request)
    {
        $date = Carbon::parse($request->date_revenu);
        $data = $request->validated();
        $data['user_id']       = Auth::id();
        $data['mois']          = $date->month;
        $data['annee']         = $date->year;
        $data['currency_code'] ??= $this->currentCurrency();

        Revenu::create($data);

        return redirect()->back()->with('success', 'Revenu ajouté.');
    }

    public function update(UpdateRevenuRequest $request, Revenu $revenu)
    {
        $this->authorize('update', $revenu);

        $date                  = Carbon::parse($request->date_revenu);
        $data                  = $request->validated();
        $data['mois']          = $date->month;
        $data['annee']         = $date->year;
        $data['currency_code'] ??= $revenu->currency_code ?? $this->currentCurrency();

        $revenu->update($data);

        return redirect()->back()->with('success', 'Revenu mis à jour.');
    }

    public function destroy(Revenu $revenu)
    {
        $this->authorize('delete', $revenu);

        $revenu->delete();

        return redirect()->back()->with('success', 'Revenu supprimé.');
    }
}
