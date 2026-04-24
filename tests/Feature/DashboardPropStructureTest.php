<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests the nested monthly/annual prop structure used by the Vue dashboard.
 */
class DashboardPropStructureTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_monthly_prop_contains_required_keys(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('monthly.totalBudget')
                ->has('monthly.totalExpenses')
                ->has('monthly.totalRevenues')
                ->has('monthly.balance')
                ->has('monthly.expensesByCategory')
            );
    }

    public function test_annual_prop_contains_required_keys(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('annual.totalBudget')
                ->has('annual.totalExpenses')
                ->has('annual.totalRevenues')
                ->has('annual.balance')
                ->has('annual.expensesByCategory')
            );
    }

    public function test_monthly_total_budget_sums_monthly_budgets(): void
    {
        Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'planned_amount' => 100000]);
        Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'planned_amount' => 50000]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalBudget', 150000));
    }

    public function test_annual_balance_equals_revenues_minus_expenses(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat    = Category::factory()->create();

        Revenue::factory()->create([
            'user_id'      => $this->user->id,
            'amount'       => 1200000,
            'month'        => now()->month,
            'year'         => now()->year,
            'revenue_date' => now()->format('Y-m-01'),
        ]);
        Expense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'category_id'  => $cat->id,
            'amount'       => 300000,
            'expense_date' => now()->format('Y-m-10'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('annual.balance', 900000));
    }

    public function test_recent_expenses_includes_category_relation(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat    = Category::factory()->create(['name' => 'Alimentation']);

        Expense::factory()->create([
            'user_id'     => $this->user->id,
            'budget_id'   => $budget->id,
            'category_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('recentExpenses', 1)
                ->where('recentExpenses.0.category.name', 'Alimentation')
            );
    }
}
