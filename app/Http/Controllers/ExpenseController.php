<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    const PER_PAGE = 20;

    public function index(Request $request)
    {
        ['month' => $month, 'year' => $year, 'currency' => $currency] = $this->resolvePeriodFilters($request);

        $query = Expense::where('user_id', Auth::id())
            ->with('category:id,name,color', 'budget:id,label,type,month,year')
            ->latest('expense_date');

        if ($currency !== 'all') {
            $query->where('currency_code', $currency);
        }

        if ($request->filled('budget_id')) {
            $query->where('budget_id', $request->integer('budget_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($month) {
            $query->whereMonth('expense_date', $month);
        }

        if ($year) {
            $query->whereYear('expense_date', $year);
        }

        $totalAmount = (clone $query)->sum('amount');
        $expenses = $query->paginate(self::PER_PAGE)->withQueryString();

        $budgets = Budget::where('user_id', Auth::id())
            ->when($currency !== 'all', fn ($q) => $q->where('currency_code', $currency))
            ->orderBy('year', 'desc')->orderBy('month', 'desc')
            ->get(['id', 'label', 'type', 'month', 'year']);

        $categories = Category::enabledFor(Auth::user())->orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'totalAmount' => $totalAmount,
            'budgets' => $budgets,
            'categories' => $categories,
            'filters' => array_merge(
                $request->only('budget_id', 'category_id'),
                ['month' => $month, 'year' => $year, 'currency' => $currency]
            ),
        ]);
    }

    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();
        $data['currency_code'] ??= $this->currentCurrency();

        Expense::create($data);

        return redirect()->back()->with('success', __('flash.expense_created'));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->authorize('update', $expense);

        $data = $request->validated();
        $data['currency_code'] ??= $expense->currency_code ?? $this->currentCurrency();
        $expense->update($data);

        return redirect()->back()->with('success', __('flash.expense_updated'));
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return redirect()->back()->with('success', __('flash.expense_deleted'));
    }
}
