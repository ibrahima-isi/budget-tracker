<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Categorie;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $mois  = $request->query('mois')  ? (int) $request->query('mois')  : null;
        $annee = $request->query('annee') ? (int) $request->query('annee') : null;

        $currency = $request->query('currency', '');
        if ($currency === '' || $currency === null) {
            $currency = $this->currentCurrency();
        }

        $query = Budget::where('user_id', Auth::id())
            ->with('categorie')
            ->withCount('depenses')
            ->latest();

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        if ($annee) {
            $query->where('annee', $annee);
        }

        if ($mois) {
            // For mensuel budgets: match the month. For annuel: no month constraint.
            $query->where(function ($q) use ($mois) {
                $q->where('type', 'annuel')
                  ->orWhere(fn ($q2) => $q2->where('type', 'mensuel')->where('mois', $mois));
            });
        }

        $budgets = $query->paginate(10)->withQueryString();

        return Inertia::render('Budgets/Index', [
            'budgets'    => $budgets,
            'categories' => Categorie::enabledFor(Auth::user())->orderBy('nom')->get(['id', 'nom', 'couleur']),
            'filters'    => ['mois' => $mois, 'annee' => $annee, 'currency' => $currency],
        ]);
    }

    public function show(Budget $budget)
    {
        $this->authorize('view', $budget);

        $budget->load('depenses.categorie');

        return Inertia::render('Budgets/Show', [
            'budget'     => $budget,
            'categories' => Categorie::enabledFor(Auth::user())->orderBy('nom')->get(['id', 'nom', 'couleur']),
        ]);
    }

    public function store(StoreBudgetRequest $request)
    {
        try {
            $data                  = $request->validated();
            $data['user_id']       = Auth::id();
            $data['currency_code'] ??= $this->currentCurrency();

            Budget::create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'periode' => ['Un budget de ce type existe déjà pour cette période.'],
            ]);
        }

        return redirect()->route('budgets.index')
            ->with('success', 'Budget créé avec succès.');
    }

    public function update(UpdateBudgetRequest $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        try {
            $data                  = $request->validated();
            $data['currency_code'] ??= $budget->currency_code ?? $this->currentCurrency();
            $budget->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'periode' => ['Un budget de ce type existe déjà pour cette période.'],
            ]);
        }

        return redirect()->route('budgets.index')
            ->with('success', 'Budget mis à jour.');
    }

    public function destroy(Budget $budget)
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return redirect()->route('budgets.index')
            ->with('success', 'Budget supprimé.');
    }
}
