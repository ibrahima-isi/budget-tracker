<?php

namespace Tests\Unit\Policies;

use App\Models\Revenu;
use App\Models\User;
use App\Policies\RevenuPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenuPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RevenuPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new RevenuPolicy();
    }

    public function test_owner_can_update_revenu(): void
    {
        $user   = User::factory()->create();
        $revenu = Revenu::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->update($user, $revenu));
    }

    public function test_non_owner_cannot_update_revenu(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $revenu   = Revenu::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->update($intruder, $revenu));
    }

    public function test_owner_can_delete_revenu(): void
    {
        $user   = User::factory()->create();
        $revenu = Revenu::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($this->policy->delete($user, $revenu));
    }

    public function test_non_owner_cannot_delete_revenu(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $revenu   = Revenu::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($this->policy->delete($intruder, $revenu));
    }
}
