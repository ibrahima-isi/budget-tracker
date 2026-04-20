<?php

namespace Tests\Unit\Models;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_montant_depense_is_zero_with_no_depenses(): void
    {
        $budget = Budget::factory()->create(['montant_prevu' => 100000]);

        $this->assertEquals(0.0, $budget->montant_depense);
    }

    public function test_montant_depense_sums_all_linked_depenses(): void
    {
        $user     = User::factory()->create();
        $budget   = Budget::factory()->create(['user_id' => $user->id, 'montant_prevu' => 200000]);
        $cat      = Categorie::factory()->create();

        Depense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'categorie_id' => $cat->id, 'montant' => 30000]);
        Depense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'categorie_id' => $cat->id, 'montant' => 20000]);

        $this->assertEquals(50000.0, $budget->fresh()->montant_depense);
    }

    public function test_solde_is_prevu_minus_depense(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id, 'montant_prevu' => 100000]);
        $cat    = Categorie::factory()->create();

        Depense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'categorie_id' => $cat->id, 'montant' => 40000]);

        $fresh = $budget->fresh();
        $this->assertEquals(60000.0, $fresh->solde);
    }

    public function test_solde_is_negative_when_over_budget(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id, 'montant_prevu' => 10000]);
        $cat    = Categorie::factory()->create();

        Depense::factory()->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'categorie_id' => $cat->id, 'montant' => 15000]);

        $this->assertLessThan(0, $budget->fresh()->solde);
    }

    public function test_budget_belongs_to_user(): void
    {
        $budget = Budget::factory()->create();
        $this->assertInstanceOf(User::class, $budget->user);
    }

    public function test_budget_has_many_depenses(): void
    {
        $user   = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $user->id]);
        $cat    = Categorie::factory()->create();

        Depense::factory()->count(3)->create(['user_id' => $user->id, 'budget_id' => $budget->id, 'categorie_id' => $cat->id]);

        $this->assertCount(3, $budget->depenses);
    }
}
