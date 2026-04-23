<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepenseRequest;
use App\Http\Requests\UpdateDepenseRequest;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DepenseController extends Controller
{
    public function index(Request $request)
    {
        $mois  = $request->query('mois')  ? (int) $request->query('mois')  : null;
        $annee = $request->query('annee') ? (int) $request->query('annee') : null;

        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        $query = Depense::where('user_id', Auth::id())
            ->with('categorie:id,nom,couleur', 'budget:id,libelle,type,mois,annee')
            ->latest('date_depense');

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        if ($request->filled('budget_id')) {
            $query->where('budget_id', $request->integer('budget_id'));
        }

        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->integer('categorie_id'));
        }

        if ($mois) {
            $query->whereMonth('date_depense', $mois);
        }

        if ($annee) {
            $query->whereYear('date_depense', $annee);
        }

        $depenses = $query->paginate(20)->withQueryString();

        $budgets = Budget::where('user_id', Auth::id())
            ->when($currency !== 'all', fn ($q) => $q->where('currency_code', $currency))
            ->orderBy('annee', 'desc')->orderBy('mois', 'desc')
            ->get(['id', 'libelle', 'type', 'mois', 'annee']);

        $categories = Categorie::enabledFor(Auth::user())->orderBy('nom')->get(['id', 'nom', 'couleur']);

        return Inertia::render('Depenses/Index', [
            'depenses'   => $depenses,
            'budgets'    => $budgets,
            'categories' => $categories,
            'filters'    => array_merge(
                $request->only('budget_id', 'categorie_id'),
                ['mois' => $mois, 'annee' => $annee, 'currency' => $currency]
            ),
        ]);
    }

    public function store(StoreDepenseRequest $request)
    {
        $data                  = $request->validated();
        $data['user_id']       = Auth::id();
        $data['currency_code'] ??= $this->currentCurrency();

        Depense::create($data);

        return redirect()->back()->with('success', 'Dépense ajoutée.');
    }

    public function update(UpdateDepenseRequest $request, Depense $depense)
    {
        $this->authorize('update', $depense);

        $data                  = $request->validated();
        $data['currency_code'] ??= $depense->currency_code ?? $this->currentCurrency();
        $depense->update($data);

        return redirect()->back()->with('success', 'Dépense mise à jour.');
    }

    public function destroy(Depense $depense)
    {
        $this->authorize('delete', $depense);

        $depense->delete();

        return redirect()->back()->with('success', 'Dépense supprimée.');
    }
}
