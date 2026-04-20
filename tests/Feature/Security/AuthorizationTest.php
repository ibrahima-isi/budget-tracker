<?php

namespace Tests\Feature\Security;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
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
    private Categorie $categorie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attacker  = User::factory()->create(['email_verified_at' => now()]);
        $this->victim    = User::factory()->create(['email_verified_at' => now()]);
        $this->categorie = Categorie::factory()->create();
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
                'type'          => 'annuel',
                'annee'         => $budget->annee,
                'montant_prevu' => 999999,
            ])
            ->assertForbidden();

        $this->assertNotEquals(999999, $budget->fresh()->montant_prevu);
    }

    public function test_cannot_delete_another_users_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->delete("/budgets/{$budget->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    // ── Depense ────────────────────────────────────────────────────────────────

    public function test_cannot_update_another_users_depense(): void
    {
        $budget  = Budget::factory()->create(['user_id' => $this->victim->id]);
        $depense = Depense::factory()->create([
            'user_id' => $this->victim->id, 'budget_id' => $budget->id,
            'categorie_id' => $this->categorie->id, 'montant' => 5000,
        ]);

        $this->actingAs($this->attacker)
            ->patch("/depenses/{$depense->id}", [
                'budget_id' => $budget->id, 'categorie_id' => $this->categorie->id,
                'libelle' => 'Hacked', 'montant' => 1, 'date_depense' => '2026-01-01',
            ])
            ->assertForbidden();

        $this->assertEquals(5000, $depense->fresh()->montant);
    }

    public function test_cannot_delete_another_users_depense(): void
    {
        $budget  = Budget::factory()->create(['user_id' => $this->victim->id]);
        $depense = Depense::factory()->create([
            'user_id' => $this->victim->id, 'budget_id' => $budget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->attacker)
            ->delete("/depenses/{$depense->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('depenses', ['id' => $depense->id]);
    }

    // ── Revenu ─────────────────────────────────────────────────────────────────

    public function test_cannot_update_another_users_revenu(): void
    {
        $revenu = Revenu::factory()->create(['user_id' => $this->victim->id, 'montant' => 100000]);

        $this->actingAs($this->attacker)
            ->patch("/revenus/{$revenu->id}", [
                'source' => 'Hacked', 'montant' => 1, 'date_revenu' => '2026-01-01',
            ])
            ->assertForbidden();

        $this->assertEquals(100000, $revenu->fresh()->montant);
    }

    public function test_cannot_delete_another_users_revenu(): void
    {
        $revenu = Revenu::factory()->create(['user_id' => $this->victim->id]);

        $this->actingAs($this->attacker)
            ->delete("/revenus/{$revenu->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('revenus', ['id' => $revenu->id]);
    }

    // ── Mass-assignment / user_id forging ─────────────────────────────────────

    public function test_cannot_forge_user_id_in_budget_creation(): void
    {
        $this->actingAs($this->attacker)->post('/budgets', [
            'user_id'       => $this->victim->id,
            'type'          => 'annuel',
            'annee'         => 2026,
            'montant_prevu' => 50000,
        ]);

        $budget = Budget::where('user_id', $this->attacker->id)->latest()->first();
        $this->assertNotNull($budget, 'Budget was not created for attacker.');
        $this->assertEquals($this->attacker->id, $budget->user_id);
    }

    public function test_cannot_forge_user_id_in_depense_creation(): void
    {
        $attackerBudget = Budget::factory()->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->post('/depenses', [
            'user_id'      => $this->victim->id,
            'budget_id'    => $attackerBudget->id,
            'categorie_id' => $this->categorie->id,
            'libelle'      => 'Forge test',
            'montant'      => 1000,
            'date_depense' => '2026-04-01',
        ]);

        $depense = Depense::where('libelle', 'Forge test')->first();
        $this->assertNotNull($depense);
        $this->assertEquals($this->attacker->id, $depense->user_id);
    }

    public function test_cannot_forge_user_id_in_revenu_creation(): void
    {
        $this->actingAs($this->attacker)->post('/revenus', [
            'user_id'     => $this->victim->id,
            'source'      => 'Forge test',
            'montant'     => 1000,
            'date_revenu' => '2026-04-01',
        ]);

        $revenu = Revenu::where('source', 'Forge test')->first();
        $this->assertNotNull($revenu);
        $this->assertEquals($this->attacker->id, $revenu->user_id);
    }

    // ── Index isolation ────────────────────────────────────────────────────────

    public function test_budget_index_only_shows_own_budgets(): void
    {
        Budget::factory()->count(3)->create(['user_id' => $this->victim->id]);
        Budget::factory()->count(2)->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->get('/budgets')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 2));
    }

    public function test_depense_index_only_shows_own_depenses(): void
    {
        $victimBudget   = Budget::factory()->create(['user_id' => $this->victim->id]);
        $attackerBudget = Budget::factory()->create(['user_id' => $this->attacker->id]);

        Depense::factory()->count(3)->create([
            'user_id' => $this->victim->id, 'budget_id' => $victimBudget->id, 'categorie_id' => $this->categorie->id,
        ]);
        Depense::factory()->count(1)->create([
            'user_id' => $this->attacker->id, 'budget_id' => $attackerBudget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->attacker)->get('/depenses')
            ->assertInertia(fn ($page) => $page->has('depenses.data', 1));
    }

    public function test_revenu_index_only_shows_own_revenus(): void
    {
        Revenu::factory()->count(3)->create(['user_id' => $this->victim->id]);
        Revenu::factory()->count(2)->create(['user_id' => $this->attacker->id]);

        $this->actingAs($this->attacker)->get('/revenus')
            ->assertInertia(fn ($page) => $page->has('revenus.data', 2));
    }
}
