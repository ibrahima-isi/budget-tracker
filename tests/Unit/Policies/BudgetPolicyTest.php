<?php

namespace Tests\Unit\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Policies\BudgetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BudgetPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new BudgetPolicy();
    }

    public function test_owner_can_view_budget(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->view($user, $budget));
    }

    public function test_non_owner_cannot_view_budget(): void
    {
        $owner     = User::factory()->create();
        $intruder  = User::factory()->create();
        $budget    = Budget::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->view($intruder, $budget));
    }

    public function test_owner_can_update_budget(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $budget));
    }

    public function test_non_owner_cannot_update_budget(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $budget   = Budget::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->update($intruder, $budget));
    }

    public function test_owner_can_delete_budget(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $budget));
    }

    public function test_non_owner_cannot_delete_budget(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $budget   = Budget::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->delete($intruder, $budget));
    }
}
