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
use Illuminate\Support\Facades\DB;
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

        $filteredBudgets = (clone $query)->select(['id', 'planned_amount']);
        $expenseTotals = Expense::query()
            ->whereIn('budget_id', (clone $query)->select('id'))
            ->selectRaw('budget_id, SUM(amount) as spent')
            ->groupBy('budget_id');

        $totalsRow = DB::query()
            ->fromSub($filteredBudgets, 'filtered_budgets')
            ->leftJoinSub($expenseTotals, 'expense_totals', 'expense_totals.budget_id', '=', 'filtered_budgets.id')
            ->selectRaw('COALESCE(SUM(filtered_budgets.planned_amount), 0) as planned')
            ->selectRaw('COALESCE(SUM(expense_totals.spent), 0) as spent')
            ->first();

        $totals = [
            'planned' => (float) ($totalsRow->planned ?? 0),
            'spent' => (float) ($totalsRow->spent ?? 0),
        ];
        $totals['balance'] = $totals['planned'] - $totals['spent'];

        $budgets = $query
            ->select(['id', 'type', 'month', 'year', 'planned_amount', 'label', 'category_id', 'currency_code'])
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

        $budget->load([
            'expenses:id,budget_id,category_id,label,amount,expense_date,note,currency_code',
            'expenses.category:id,name,color',
        ]);

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
