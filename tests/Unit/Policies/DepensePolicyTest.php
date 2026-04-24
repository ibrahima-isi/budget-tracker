<?php

namespace Tests\Unit\Policies;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Policies\ExpensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepensePolicyTest extends TestCase
{
    use RefreshDatabase;

    private ExpensePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ExpensePolicy();
    }

    private function makeExpense(User $owner): Expense
    {
        return Expense::factory()->create([
            'user_id'     => $owner->id,
            'budget_id'   => Budget::factory()->create(['user_id' => $owner->id])->id,
            'category_id' => Category::factory()->create()->id,
        ]);
    }

    public function test_owner_can_update_expense(): void
    {
        $user    = User::factory()->create();
        $expense = $this->makeExpense($user);

        $this->assertTrue($this->policy->update($user, $expense));
    }

    public function test_non_owner_cannot_update_expense(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $expense  = $this->makeExpense($owner);

        $this->assertFalse($this->policy->update($intruder, $expense));
    }

    public function test_owner_can_delete_expense(): void
    {
        $user    = User::factory()->create();
        $expense = $this->makeExpense($user);

        $this->assertTrue($this->policy->delete($user, $expense));
    }

    public function test_non_owner_cannot_delete_expense(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $expense  = $this->makeExpense($owner);

        $this->assertFalse($this->policy->delete($intruder, $expense));
    }
}
