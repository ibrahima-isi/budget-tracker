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
        $mois  = $request->query('mois')  ? (int) $request->query('mois')  : null;
        $annee = $request->query('annee') ? (int) $request->query('annee') : null;

        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        $query = Revenu::where('user_id', Auth::id())
            ->latest('date_revenu');

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        if ($mois) {
            $query->where('mois', $mois);
        }

        if ($annee) {
            $query->where('annee', $annee);
        }

        $revenus = $query->paginate(20)->withQueryString();

        return Inertia::render('Revenus/Index', [
            'revenus' => $revenus,
            'filters' => ['mois' => $mois, 'annee' => $annee, 'currency' => $currency],
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
