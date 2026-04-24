<?php

namespace Tests\Feature\Security;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that users cannot read, modify, or delete resources belonging to other users,
 * and that mass-assignment cannot be exploited to forge resource ownership.
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker;
    private User $victim;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attacker = User::factory()->create(['email_verified_at' => now()]);
        $this->victim   = User::factory()->create(['email_verified_at' => now()]);
        $this->category = Category::factory()->create();
    }

    // ── Budget ─────────────────────────────────────────────────────────────────

    public function test_cannot_view_another_users_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->get("/budgets/{$budget->id}")
            ->assertForbidden();
    }

    public function test_cannot_update_another_users_budget(): void
    {
        $budget = Budget::factory()->annuel()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->patch("/budgets/{$budget->id}", [
                'type'           => 'annuel',
                'year'           => $budget->year,
                'planned_amount' => 999999,
            ])
            ->assertForbidden();

        $this->assertNotEquals(999999, $budget->fresh()->planned_amount);
    }

    public function test_cannot_delete_another_users_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->delete("/budgets/{$budget->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    // ── Expense ────────────────────────────────────────────────────────────────

    public function test_cannot_update_another_users_expense(): void
    {
        $budget  = Budget::factory()->create(['user_id' => $this->victim->id]);
        $expense = Expense::factory()->create([
            'user_id' => $this->victim->id, 'budget_id' => $budget->id,
            'category_id' => $this->category->id, 'amount' => 5000,
        ]);

        $this->actingAs($this->attacker)
            ->patch("/expenses/{$expense->id}", [
                'budget_id' => $budget->id, 'category_id' => $this->category->id,
                'label' => 'Hacked', 'amount' => 1, 'expense_date' => '2026-01-01',
            ])
            ->assertForbidden();

        $this->assertEquals(5000, $expense->fresh()->amount);
    }

    public function test_cannot_delete_another_users_expense(): void
    {
        $budget  = Budget::factory()->create(['user_id' => $this->victim->id]);
        $expense = Expense::factory()->create([
            'user_id' => $this->victim->id, 'budget_id' => $budget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->attacker)
            ->delete("/expenses/{$expense->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    // ── Revenue ────────────────────────────────────────────────────────────────

    public function test_cannot_update_another_users_revenue(): void
    {
        $revenue = Revenue::factory()->create(['user_id' => $this->victim->id, 'amount' => 100000]);

        $this->actingAs($this->attacker)
            ->patch("/revenues/{$revenue->id}", [
                'source' => 'Hacked', 'amount' => 1, 'revenue_date' => '2026-01-01',
            ])
            ->assertForbidden();

        $this->assertEquals(100000, $revenue->fresh()->amount);
    }

    public function test_cannot_delete_another_users_revenue(): void
    {
        $revenue = Revenue::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->delete("/revenues/{$revenue->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('revenues', ['id' => $revenue->id]);
    }

    // ── Mass-assignment / user_id forging ─────────────────────────────────────

    public function test_cannot_forge_user_id_in_budget_creation(): void
    {
        $this->actingAs($this->attacker)->post('/budgets', [
            'user_id'        => $this->victim->id,
            'type'           => 'annuel',
            'year'           => 2026,
            'planned_amount' => 50000,
        ]);

        $budget = Budget::where('user_id', $this->attacker->id)->latest()->first();
        $this->assertNotNull($budget, 'Budget was not created for attacker.');
        $this->assertEquals($this->attacker->id, $budget->user_id);
    }

    public function test_cannot_forge_user_id_in_expense_creation(): void
    {
        $attackerBudget = Budget::factory()->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->post('/expenses', [
            'user_id'      => $this->victim->id,
            'budget_id'    => $attackerBudget->id,
            'category_id'  => $this->category->id,
            'label'        => 'Forge test',
            'amount'       => 1000,
            'expense_date' => '2026-04-01',
        ]);

        $expense = Expense::where('label', 'Forge test')->first();
        $this->assertNotNull($expense);
        $this->assertEquals($this->attacker->id, $expense->user_id);
    }

    public function test_cannot_forge_user_id_in_revenue_creation(): void
    {
        $this->actingAs($this->attacker)->post('/revenues', [
            'user_id'      => $this->victim->id,
            'source'       => 'Forge test',
            'amount'       => 1000,
            'revenue_date' => '2026-04-01',
        ]);

        $revenue = Revenue::where('source', 'Forge test')->first();
        $this->assertNotNull($revenue);
        $this->assertEquals($this->attacker->id, $revenue->user_id);
    }

    // ── Index isolation ────────────────────────────────────────────────────────

    public function test_budget_index_only_shows_own_budgets(): void
    {
        Budget::factory()->count(3)->create(['user_id' => $this->victim->id]);
        Budget::factory()->count(2)->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->get('/budgets')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 2));
    }

    public function test_expense_index_only_shows_own_expenses(): void
    {
        $victimBudget   = Budget::factory()->create(['user_id' => $this->victim->id]);
        $attackerBudget = Budget::factory()->create(['user_id' => $this->attacker->id]);

        Expense::factory()->count(3)->create([
            'user_id' => $this->victim->id, 'budget_id' => $victimBudget->id, 'category_id' => $this->category->id,
        ]);
        Expense::factory()->count(1)->create([
            'user_id' => $this->attacker->id, 'budget_id' => $attackerBudget->id, 'category_id' => $this->category->id,
        ]);

        $this->actingAs($this->attacker)->get('/expenses')
            ->assertInertia(fn ($page) => $page->has('expenses.data', 1));
    }

    public function test_revenue_index_only_shows_own_revenues(): void
    {
        Revenue::factory()->count(3)->create(['user_id' => $this->victim->id]);
        Revenue::factory()->count(2)->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->get('/revenues')
            ->assertInertia(fn ($page) => $page->has('revenues.data', 2));
    }
}
