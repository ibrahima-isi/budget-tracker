<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Budget $budget;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
        $this->budget = Budget::factory()->create(['user_id' => $this->user->id]);
        $this->category = Category::factory()->create();
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_expenses(): void
    {
        $this->get('/expenses')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_expenses(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/expenses')->assertRedirect('/verify-email');
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_own_expenses(): void
    {
        Expense::factory()->currentPeriod()->count(3)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)->get('/expenses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expenses/Index')
                ->has('expenses.data', 3)
            );
    }

    public function test_index_does_not_show_other_users_expenses(): void
    {
        $other = User::factory()->create();
        $otherBudget = Budget::factory()->create(['user_id' => $other->id]);
        Expense::factory()->currentPeriod()->count(2)->create([
            'user_id' => $other->id, 'budget_id' => $otherBudget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)->get('/expenses')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 0));
    }

    public function test_index_paginates_at_20_per_page(): void
    {
        Expense::factory()->count(25)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)->get('/expenses?month=all&year=all')
            ->assertInertia(fn ($page) => $page
                ->has('expenses.data', 20)
                ->where('expenses.total', 25)
            );
    }

    public function test_index_total_amount_sums_matching_expense_amounts(): void
    {
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'amount' => 1500,
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'amount' => 2500,
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'amount' => 9999,
            'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/expenses')
            ->assertInertia(fn ($page) => $page
                ->where('totalAmount', 4000)
            );
    }

    public function test_index_passes_budgets_and_categories_to_view(): void
    {
        $this->actingAs($this->user)->get('/expenses')
            ->assertInertia(fn ($page) => $page
                ->has('budgets')
                ->has('categories')
                ->has('filters')
            );
    }

    // ── Filters ────────────────────────────────────────────────────────────────

    public function test_index_filters_by_budget_id(): void
    {
        $otherBudget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat = Category::factory()->create();

        Expense::factory()->currentPeriod()->count(2)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $cat->id,
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id, 'budget_id' => $otherBudget->id, 'category_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get("/expenses?budget_id={$this->budget->id}")
            ->assertInertia(fn ($page) => $page->has('expenses.data', 2));
    }

    public function test_index_filters_by_category_id(): void
    {
        $otherCat = Category::factory()->create();

        Expense::factory()->currentPeriod()->count(3)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $this->category->id,
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $otherCat->id,
        ]);

        $this->actingAs($this->user)->get("/expenses?category_id={$this->category->id}")
            ->assertInertia(fn ($page) => $page->has('expenses.data', 3));
    }

    public function test_index_filters_by_budget_and_category_combined(): void
    {
        $budget2 = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat2 = Category::factory()->create();

        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $this->category->id,
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget2->id, 'category_id' => $this->category->id,
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $cat2->id,
        ]);

        $this->actingAs($this->user)
            ->get("/expenses?budget_id={$this->budget->id}&category_id={$this->category->id}")
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    public function test_filters_are_passed_back_to_view(): void
    {
        $this->actingAs($this->user)
            ->get("/expenses?budget_id={$this->budget->id}")
            ->assertInertia(fn ($page) => $page
                ->where('filters.budget_id', (string) $this->budget->id)
            );
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_expense(): void
    {
        $this->actingAs($this->user)->post('/expenses', [
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'label' => 'Courses alimentaires',
            'amount' => 15000,
            'expense_date' => '2026-04-10',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', [
            'user_id' => $this->user->id,
            'label' => 'Courses alimentaires',
        ]);
    }

    public function test_note_is_optional_on_store(): void
    {
        $this->actingAs($this->user)->post('/expenses', [
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'label' => 'Test',
            'amount' => 1000,
            'expense_date' => '2026-04-01',
        ])->assertRedirect();
    }

    public function test_user_id_cannot_be_forged_on_store(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->post('/expenses', [
            'user_id' => $other->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'label' => 'Forge test',
            'amount' => 1000,
            'expense_date' => '2026-04-01',
        ]);

        $expense = Expense::where('label', 'Forge test')->first();
        $this->assertEquals($this->user->id, $expense->user_id);
    }

    public function test_store_rejects_nonexistent_budget_id(): void
    {
        $this->actingAs($this->user)->post('/expenses', [
            'budget_id' => 99999,
            'category_id' => $this->category->id,
            'label' => 'Test',
            'amount' => 1000,
            'expense_date' => '2026-04-01',
        ])->assertSessionHasErrors(['budget_id']);
    }

    public function test_store_rejects_nonexistent_category_id(): void
    {
        $this->actingAs($this->user)->post('/expenses', [
            'budget_id' => $this->budget->id,
            'category_id' => 99999,
            'label' => 'Test',
            'amount' => 1000,
            'expense_date' => '2026-04-01',
        ])->assertSessionHasErrors(['category_id']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/expenses', [])
            ->assertSessionHasErrors(['budget_id', 'label', 'amount', 'expense_date']); // category_id is nullable
    }

    public function test_amount_must_be_non_negative(): void
    {
        $this->actingAs($this->user)->post('/expenses', [
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'label' => 'Test',
            'amount' => -100,
            'expense_date' => '2026-04-01',
        ])->assertSessionHasErrors(['amount']);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_own_expense(): void
    {
        $expense = Expense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id,
            'category_id' => $this->category->id, 'amount' => 10000,
        ]);

        $this->actingAs($this->user)->patch("/expenses/{$expense->id}", [
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'label' => $expense->label,
            'amount' => 20000,
            'expense_date' => $expense->expense_date->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertEquals(20000, $expense->fresh()->amount);
    }

    public function test_user_cannot_update_other_users_expense(): void
    {
        $other = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $other->id]);
        $expense = Expense::factory()->create([
            'user_id' => $other->id, 'budget_id' => $budget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)->patch("/expenses/{$expense->id}", [
            'budget_id' => $budget->id, 'category_id' => $this->category->id,
            'label' => 'Hacked', 'amount' => 1, 'expense_date' => '2026-01-01',
        ])->assertForbidden();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_own_expense(): void
    {
        $expense = Expense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)->delete("/expenses/{$expense->id}")->assertRedirect();
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_user_cannot_delete_other_users_expense(): void
    {
        $other = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $other->id]);
        $expense = Expense::factory()->create([
            'user_id' => $other->id, 'budget_id' => $budget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->user)->delete("/expenses/{$expense->id}")->assertForbidden();
        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    public function test_delete_returns_404_for_nonexistent_expense(): void
    {
        $this->actingAs($this->user)->delete('/expenses/99999')->assertNotFound();
    }

    // ── Period & currency filters ──────────────────────────────────────────────

    public function test_filters_prop_contains_period_and_currency(): void
    {
        $this->actingAs($this->user)->get('/expenses?month=4&year=2025')
            ->assertInertia(fn ($page) => $page
                ->has('filters')
                ->where('filters.month', 4)
                ->where('filters.year', 2025)
            );
    }

    public function test_month_filter_returns_only_matching_month(): void
    {
        // April 2025
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        // June 2025 — must be excluded
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-06-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/expenses?month=4&year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    public function test_month_filter_without_year_returns_matching_month_across_years(): void
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2024-04-10',
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-06-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/expenses?month=4&year=all&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 2));
    }

    public function test_year_filter_returns_only_matching_year(): void
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        // 2024 — must be excluded
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2024-04-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/expenses?year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    public function test_all_months_filter_returns_whole_matching_year(): void
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2025-06-10',
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'expense_date' => '2024-04-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/expenses?month=all&year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page
                ->where('filters.month', null)
                ->where('filters.year', 2025)
                ->has('expenses.data', 2)
            );
    }

    public function test_currency_all_shows_all_currencies(): void
    {
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/expenses?currency=all')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 2));
    }

    public function test_default_currency_filter_excludes_other_currencies(): void
    {
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'currency_code' => 'XOF',
        ]);
        Expense::factory()->currentPeriod()->create([
            'user_id' => $this->user->id,
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'currency_code' => 'EUR',
        ]);

        // No currency param → session default (XOF in tests)
        $this->actingAs($this->user)->get('/expenses')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }
}
