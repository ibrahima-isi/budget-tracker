<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBudgetRequest;
use App\Http\Requests\UpdateBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class BudgetController extends Controller
{
    const PER_PAGE = 10;

    public function index(Request $request)
    {
        ['month' => $month, 'year' => $year, 'currency' => $currency] = $this->resolvePeriodFilters($request);

        $query = Budget::where('user_id', Auth::id());

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        if ($year) {
            $query->where('year', $year);
        }

        if ($month) {
            $query->where(function ($q) use ($month) {
                $q->where('type', 'annuel')
                    ->orWhere(fn ($q2) => $q2->where('type', 'mensuel')->where('month', $month));
            });
        }

        $budgetIds = (clone $query)->select('id');
        $totals = [
            'planned' => (clone $query)->sum('planned_amount'),
            'spent' => Expense::whereIn('budget_id', $budgetIds)->sum('amount'),
        ];
        $totals['balance'] = $totals['planned'] - $totals['spent'];

        $budgets = $query
            ->with('category:id,name,color')
            ->withCount('expenses')
            ->withSum('expenses as expense_amount_sum', 'amount')
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Budgets/Index', [
            'budgets' => $budgets,
            'totals' => $totals,
            'categories' => Category::enabledFor(Auth::user())->orderBy('name')->get(['id', 'name', 'color']),
            'filters' => ['month' => $month, 'year' => $year, 'currency' => $currency],
        ]);
    }

    public function show(Budget $budget)
    {
        $this->authorize('view', $budget);

        $budget->load('expenses.category');

        return Inertia::render('Budgets/Show', [
            'budget' => $budget,
            'categories' => Category::enabledFor(Auth::user())->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    public function store(StoreBudgetRequest $request)
    {
        try {
            $data = $request->validated();
            $data['user_id'] = Auth::id();
            $data['currency_code'] ??= $this->currentCurrency();

            Budget::create($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'periode' => [__('flash.budget_period_conflict')],
            ]);
        }

        return redirect()->route('budgets.index')
            ->with('success', __('flash.budget_created'));
    }

    public function update(UpdateBudgetRequest $request, Budget $budget)
    {
        $this->authorize('update', $budget);

        try {
            $data = $request->validated();
            $data['currency_code'] ??= $budget->currency_code ?? $this->currentCurrency();
            $budget->update($data);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'periode' => [__('flash.budget_period_conflict')],
            ]);
        }

        return redirect()->route('budgets.index')
            ->with('success', __('flash.budget_updated'));
    }

    public function destroy(Budget $budget)
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return redirect()->route('budgets.index')
            ->with('success', __('flash.budget_deleted'));
    }
}
