<?php

namespace Tests\Unit\Policies;

use App\Models\Revenue;
use App\Models\User;
use App\Policies\RevenuePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenuPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RevenuePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RevenuePolicy();
    }

    public function test_owner_can_update_revenue(): void
    {
        $user    = User::factory()->create();
        $revenue = Revenue::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $revenue));
    }

    public function test_non_owner_cannot_update_revenue(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $revenue  = Revenue::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->update($intruder, $revenue));
    }

    public function test_owner_can_delete_revenue(): void
    {
        $user    = User::factory()->create();
        $revenue = Revenue::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $revenue));
    }

    public function test_non_owner_cannot_delete_revenue(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $revenue  = Revenue::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->delete($intruder, $revenue));
    }
}
