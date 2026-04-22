<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Categorie;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index()
    {
        $currency = $this->currentCurrency();

        $budgets = Budget::where('user_id', Auth::id())
            ->where('currency_code', $currency)
            ->with('categorie')
            ->withCount('depenses')
            ->latest()
            ->paginate(10);

        return Inertia::render('Budgets/Index', [
            'budgets'    => $budgets,
            'categories' => Categorie::enabledFor(Auth::user())->orderBy('nom')->get(['id', 'nom', 'couleur']),
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
