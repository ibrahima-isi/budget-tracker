<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BudgetController extends Controller
{
    public function index()
    {
        $budgets = Budget::where('user_id', Auth::id())
            ->withCount('depenses')
            ->latest()
            ->paginate(10);

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
        ]);
    }

    public function show(Budget $budget)
    {
        $this->authorize('view', $budget);

        $budget->load('depenses.categorie');

        return Inertia::render('Budgets/Show', [
            'budget' => $budget,
        ]);
    }

    public function store(StoreBudgetRequest $request)
    {
        Budget::create([
            ...$request->validated(),
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('budgets.index')
            ->with('success', 'Budget créé avec succès.');
    }

    public function update(UpdateBudgetRequest $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        $budget->update($request->validated());

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
