<?php

namespace Tests\Unit\Models;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_amount_is_zero_with_no_expenses(): void
    {
        $budget = Budget::factory()->create(['planned_amount' => 100000]);

        $this->assertEquals(0.0, $budget->expense_amount);
    }

    public function test_expense_amount_sums_all_linked_expenses(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id, 'planned_amount' => 200000]);
        $cat    = Category::factory()->create();

        Expense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'category_id' => $cat->id, 'amount' => 30000]);
        Expense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'category_id' => $cat->id, 'amount' => 20000]);

        $this->assertEquals(50000.0, $budget->fresh()->expense_amount);
    }

    public function test_balance_is_planned_minus_expense(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id, 'planned_amount' => 100000]);
        $cat    = Category::factory()->create();

        Expense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'category_id' => $cat->id, 'amount' => 40000]);

        $fresh = $budget->fresh();
        $this->assertEquals(60000.0, $fresh->balance);
    }

    public function test_balance_is_negative_when_over_budget(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id, 'planned_amount' => 10000]);
        $cat    = Category::factory()->create();

        Expense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'category_id' => $cat->id, 'amount' => 15000]);

        $this->assertLessThan(0, $budget->fresh()->balance);
    }

    public function test_budget_belongs_to_user(): void
    {
        $budget = Budget::factory()->create();
        $this->assertInstanceOf(User::class, $budget->user);
    }

    public function test_budget_has_many_expenses(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id]);
        $cat    = Category::factory()->create();

        Expense::factory()->count(3)->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'category_id' => $cat->id]);

        $this->assertCount(3, $budget->expenses);
    }
}
