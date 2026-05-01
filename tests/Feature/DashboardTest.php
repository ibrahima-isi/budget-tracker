<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_from_dashboard(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/dashboard')->assertRedirect('/verify-email');
    }

    public function test_authenticated_verified_user_can_access_dashboard(): void
    {
        $this->actingAs($this->user)->get('/dashboard')->assertOk();
    }

    // ── Inertia component and prop keys ───────────────────────────────────────

    public function test_dashboard_renders_correct_inertia_component(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->component('Dashboard'));
    }

    public function test_dashboard_passes_all_required_prop_keys(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('monthly')
                ->has('annual')
                ->has('recentExpenses')
                ->has('month')
                ->has('year')
                ->has('filters')
            );
    }

    // ── Empty state ────────────────────────────────────────────────────────────

    public function test_monthly_total_budget_is_zero_when_no_budget_for_current_month(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalBudget', 0));
    }

    public function test_totals_are_zero_when_user_has_no_data(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('monthly.totalExpenses', 0)
                ->where('monthly.totalRevenues', 0)
                ->where('monthly.balance', 0)
            );
    }

    public function test_recent_expenses_is_empty_when_user_has_no_data(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('recentExpenses', 0));
    }

    // ── Budget mensuel lookup ──────────────────────────────────────────────────

    public function test_monthly_total_budget_reflects_created_budget(): void
    {
        Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'planned_amount' => 100000]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('monthly.totalBudget', 100000)
            );
    }

    public function test_monthly_total_budget_excludes_annual_budget(): void
    {
        Budget::factory()->annuel()->create(['user_id' => $this->user->id, 'planned_amount' => 200000]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalBudget', 0));
    }

    public function test_monthly_total_budget_excludes_other_users_budget(): void
    {
        $other = User::factory()->create(['email_verified_at' => now()]);
        Budget::factory()->mensuel()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalBudget', 0));
    }

    // ── Totals calculations ────────────────────────────────────────────────────

    public function test_total_expenses_sums_current_month_only(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Category::factory()->create();

        // Current month
        Expense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'category_id'  => $cat->id,
            'amount'       => 20000,
            'expense_date' => now()->format('Y-m-15'),
        ]);
        Expense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'category_id'  => $cat->id,
            'amount'       => 30000,
            'expense_date' => now()->format('Y-m-10'),
        ]);

        // Previous month — must NOT be included
        Expense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'category_id'  => $cat->id,
            'amount'       => 999999,
            'expense_date' => now()->subMonth()->format('Y-m-01'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalExpenses', 50000));
    }

    public function test_total_revenues_sums_current_month_only(): void
    {
        Revenue::factory()->create([
            'user_id'      => $this->user->id,
            'amount'       => 300000,
            'month'        => now()->month,
            'year'         => now()->year,
            'revenue_date' => now()->format('Y-m-01'),
        ]);

        // Previous month — must NOT be included
        Revenue::factory()->create([
            'user_id'      => $this->user->id,
            'amount'       => 999999,
            'month'        => now()->subMonth()->month,
            'year'         => now()->subMonth()->year,
            'revenue_date' => now()->subMonth()->format('Y-m-01'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalRevenues', 300000));
    }

    public function test_balance_equals_revenues_minus_expenses(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Category::factory()->create();

        Revenue::factory()->create([
            'user_id'      => $this->user->id,
            'amount'       => 500000,
            'month'        => now()->month,
            'year'         => now()->year,
            'revenue_date' => now()->format('Y-m-01'),
        ]);
        Expense::factory()->create([
            'user_id'      => $this->user->id,
            'budget_id'    => $budget->id,
            'category_id'  => $cat->id,
            'amount'       => 150000,
            'expense_date' => now()->format('Y-m-05'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.balance', 350000));
    }

    public function test_expenses_by_category_groups_correctly(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat1   = Category::factory()->create();
        $cat2   = Category::factory()->create();

        Expense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'category_id' => $cat1->id, 'amount' => 10000,
            'expense_date' => now()->format('Y-m-01'),
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'category_id' => $cat1->id, 'amount' => 5000,
            'expense_date' => now()->format('Y-m-02'),
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'category_id' => $cat2->id, 'amount' => 8000,
            'expense_date' => now()->format('Y-m-03'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('monthly.expensesByCategory', 2));
    }

    public function test_expenses_by_category_excludes_other_users_data(): void
    {
        $other       = User::factory()->create();
        $otherBudget = Budget::factory()->mensuel()->create(['user_id' => $other->id]);
        $cat         = Category::factory()->create();

        Expense::factory()->create([
            'user_id' => $other->id, 'budget_id' => $otherBudget->id,
            'category_id' => $cat->id, 'amount' => 50000,
            'expense_date' => now()->format('Y-m-01'),
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('monthly.expensesByCategory', 0));
    }

    // ── Recent expenses ────────────────────────────────────────────────────────

    public function test_recent_expenses_returns_at_most_5(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Category::factory()->create();

        Expense::factory()->currentPeriod()->count(8)->create([
            'user_id'     => $this->user->id,
            'budget_id'   => $budget->id,
            'category_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('recentExpenses', 5));
    }

    public function test_recent_expenses_only_shows_current_user_data(): void
    {
        $other       = User::factory()->create();
        $otherBudget = Budget::factory()->create(['user_id' => $other->id]);
        $cat         = Category::factory()->create();

        Expense::factory()->count(3)->create([
            'user_id' => $other->id, 'budget_id' => $otherBudget->id, 'category_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('recentExpenses', 0));
    }

    public function test_month_and_year_are_current_date(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('month', now()->month)
                ->where('year', now()->year)
            );
    }

    // ── Period filter (month / year query params) ──────────────────────────────

    public function test_filters_prop_is_returned(): void
    {
        $this->actingAs($this->user)->get('/dashboard?month=4&year=2025')
            ->assertInertia(fn ($page) => $page
                ->has('filters')
                ->where('filters.month', 4)
                ->where('filters.year', 2025)
            );
    }

    public function test_month_param_overrides_current_month(): void
    {
        $this->actingAs($this->user)->get('/dashboard?month=4&year=2025')
            ->assertInertia(fn ($page) => $page
                ->where('month', 4)
                ->where('year', 2025)
            );
    }

    public function test_expenses_filtered_by_requested_month(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'type'    => 'mensuel',
            'month'   => 4,
            'year'    => 2025,
        ]);
        $cat = Category::factory()->create();

        // April 2025 expense — should be counted
        Expense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $budget->id,
            'category_id'   => $cat->id,
            'amount'        => 50000,
            'expense_date'  => '2025-04-15',
            'currency_code' => 'XOF',
        ]);
        // Current month expense — must NOT be counted
        Expense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $budget->id,
            'category_id'   => $cat->id,
            'amount'        => 999999,
            'expense_date'  => now()->format('Y-m-10'),
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/dashboard?month=4&year=2025')
            ->assertInertia(fn ($page) => $page->where('monthly.totalExpenses', 50000));
    }

    public function test_revenues_filtered_by_requested_month(): void
    {
        // April 2025 revenue
        Revenue::factory()->create([
            'user_id'       => $this->user->id,
            'amount'        => 300000,
            'month'         => 4,
            'year'          => 2025,
            'revenue_date'  => '2025-04-01',
            'currency_code' => 'XOF',
        ]);
        // Current month — must NOT be counted
        Revenue::factory()->create([
            'user_id'       => $this->user->id,
            'amount'        => 999999,
            'month'         => now()->month,
            'year'          => now()->year,
            'revenue_date'  => now()->format('Y-m-01'),
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/dashboard?month=4&year=2025')
            ->assertInertia(fn ($page) => $page->where('monthly.totalRevenues', 300000));
    }

    public function test_year_filter_returns_totals_for_whole_matching_year(): void
    {
        $budget = Budget::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'mensuel',
            'month' => 4,
            'year' => 2025,
            'planned_amount' => 100000,
            'currency_code' => 'XOF',
        ]);
        $cat = Category::factory()->create();

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $budget->id,
            'category_id' => $cat->id,
            'amount' => 40000,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 150000,
            'month' => 4,
            'year' => 2025,
            'revenue_date' => '2025-04-01',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/dashboard?year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page
                ->where('filters.month', null)
                ->where('filters.year', 2025)
                ->where('monthly.totalBudget', 100000)
                ->where('monthly.totalExpenses', 40000)
                ->where('monthly.totalRevenues', 150000)
                ->where('annual.totalBudget', 100000)
                ->where('annual.totalExpenses', 40000)
                ->where('annual.totalRevenues', 150000)
            );
    }

    public function test_month_filter_without_year_returns_matching_month_across_years(): void
    {
        $budget2024 = Budget::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'mensuel',
            'month' => 4,
            'year' => 2024,
            'planned_amount' => 100000,
            'currency_code' => 'XOF',
        ]);
        $budget2025 = Budget::factory()->create([
            'user_id' => $this->user->id,
            'type' => 'mensuel',
            'month' => 4,
            'year' => 2025,
            'planned_amount' => 200000,
            'currency_code' => 'XOF',
        ]);
        $cat = Category::factory()->create();

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $budget2024->id,
            'category_id' => $cat->id,
            'amount' => 40000,
            'expense_date' => '2024-04-10',
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $budget2025->id,
            'category_id' => $cat->id,
            'amount' => 60000,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 150000,
            'month' => 4,
            'year' => 2024,
            'revenue_date' => '2024-04-01',
            'currency_code' => 'XOF',
        ]);
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 250000,
            'month' => 4,
            'year' => 2025,
            'revenue_date' => '2025-04-01',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/dashboard?month=4&year=all&currency=XOF')
            ->assertInertia(fn ($page) => $page
                ->where('filters.month', 4)
                ->where('filters.year', null)
                ->where('monthly.totalBudget', 300000)
                ->where('monthly.totalExpenses', 100000)
                ->where('monthly.totalRevenues', 400000)
            );
    }

    public function test_recent_expenses_respect_year_filter(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat = Category::factory()->create();

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $budget->id,
            'category_id' => $cat->id,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $budget->id,
            'category_id' => $cat->id,
            'expense_date' => '2024-04-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/dashboard?year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page
                ->has('recentExpenses', 1)
                ->where('recentExpenses.0.expense_date', '2025-04-10T00:00:00.000000Z')
            );
    }

    // ── Currency filter ────────────────────────────────────────────────────────

    public function test_currency_all_includes_all_currencies(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id]);
        $cat    = Category::factory()->create();

        Expense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $budget->id,
            'category_id'   => $cat->id,
            'amount'        => 10000,
            'expense_date'  => now()->format('Y-m-10'),
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $budget->id,
            'category_id'   => $cat->id,
            'amount'        => 200,
            'expense_date'  => now()->format('Y-m-11'),
            'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/dashboard?currency=all')
            ->assertInertia(fn ($page) => $page->where('monthly.totalExpenses', 10200));
    }

    public function test_currency_filter_excludes_other_currencies(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'currency_code' => 'XOF']);
        $cat    = Category::factory()->create();

        Expense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $budget->id,
            'category_id'   => $cat->id,
            'amount'        => 10000,
            'expense_date'  => now()->format('Y-m-10'),
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $budget->id,
            'category_id'   => $cat->id,
            'amount'        => 999,
            'expense_date'  => now()->format('Y-m-11'),
            'currency_code' => 'EUR',
        ]);

        // Only XOF (the session default) should be counted
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('monthly.totalExpenses', 10000));
    }
}
