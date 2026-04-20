<?php

namespace Tests\Unit\Policies;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use App\Policies\DepensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepensePolicyTest extends TestCase
{
    use RefreshDatabase;

    private DepensePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DepensePolicy();
    }

    private function makeDepense(User $owner): Depense
    {
        return Depense::factory()->create([
            'user_id'      => $owner->id,
            'budget_id'    => Budget::factory()->create(['user_id' => $owner->id])->id,
            'categorie_id' => Categorie::factory()->create()->id,
        ]);
    }

    public function test_owner_can_update_depense(): void
    {
        $user    = User::factory()->create();
        $depense = $this->makeDepense($user);

        $this->assertTrue($this->policy->update($user, $depense));
    }

    public function test_non_owner_cannot_update_depense(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $depense  = $this->makeDepense($owner);

        $this->assertFalse($this->policy->update($intruder, $depense));
    }

    public function test_owner_can_delete_depense(): void
    {
        $user    = User::factory()->create();
        $depense = $this->makeDepense($user);

        $this->assertTrue($this->policy->delete($user, $depense));
    }

    public function test_non_owner_cannot_delete_depense(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $depense  = $this->makeDepense($owner);

        $this->assertFalse($this->policy->delete($intruder, $depense));
    }
}
