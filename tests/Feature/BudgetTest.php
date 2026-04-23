<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_budget_routes(): void
    {
        $this->get('/budgets')->assertRedirect('/login');
        $this->post('/budgets')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_budgets(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($user)->get('/budgets')->assertRedirect('/verify-email');
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_own_budgets(): void
    {
        Budget::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/budgets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Budgets/Index')
                ->has('budgets.data', 3)
            );
    }

    public function test_index_does_not_show_other_users_budgets(): void
    {
        $other = User::factory()->create();
        Budget::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/budgets')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 0));
    }

    public function test_index_paginates_at_10_per_page(): void
    {
        Budget::factory()->count(15)->sequence(fn ($seq) => [
            'type' => 'annuel', 'mois' => null, 'annee' => 2000 + $seq->index,
        ])->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/budgets')
            ->assertInertia(fn ($page) => $page
                ->has('budgets.data', 10)
                ->where('budgets.total', 15)
            );
    }

    // ── Store (annuel) ─────────────────────────────────────────────────────────

    public function test_user_can_create_annuel_budget(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'annuel',
            'annee'         => 2026,
            'montant_prevu' => 1200000,
            'libelle'       => 'Budget annuel 2026',
        ])->assertRedirect('/budgets');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'type'    => 'annuel',
            'mois'    => null,
            'annee'   => 2026,
        ]);
    }

    public function test_user_can_create_mensuel_budget_with_mois(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'mensuel',
            'mois'          => 4,
            'annee'         => 2026,
            'montant_prevu' => 150000,
        ])->assertRedirect('/budgets');

        $this->assertDatabaseHas('budgets', [
            'user_id' => $this->user->id,
            'type'    => 'mensuel',
            'mois'    => 4,
            'annee'   => 2026,
        ]);
    }

    public function test_libelle_is_optional(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'annuel',
            'annee'         => 2026,
            'montant_prevu' => 100000,
        ])->assertRedirect('/budgets');
    }

    public function test_budget_user_id_is_always_set_to_authenticated_user(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->post('/budgets', [
            'user_id'       => $other->id,   // attacker forges user_id
            'type'          => 'annuel',
            'annee'         => 2026,
            'montant_prevu' => 100000,
        ]);

        $budget = Budget::where('montant_prevu', 100000)->first();
        $this->assertEquals($this->user->id, $budget->user_id);
    }

    // ── Store validation ───────────────────────────────────────────────────────

    public function test_store_requires_type_annee_montant(): void
    {
        $this->actingAs($this->user)->post('/budgets', [])
            ->assertSessionHasErrors(['type', 'annee', 'montant_prevu']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'hebdomadaire',
            'annee'         => 2026,
            'montant_prevu' => 100000,
        ])->assertSessionHasErrors(['type']);
    }

    public function test_mensuel_budget_requires_mois(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'mensuel',
            'annee'         => 2026,
            'montant_prevu' => 100000,
            // mois missing
        ])->assertSessionHasErrors(['mois']);
    }

    public function test_mois_must_be_between_1_and_12(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'mensuel',
            'mois'          => 13,
            'annee'         => 2026,
            'montant_prevu' => 100000,
        ])->assertSessionHasErrors(['mois']);
    }

    public function test_montant_prevu_must_be_non_negative(): void
    {
        $this->actingAs($this->user)->post('/budgets', [
            'type'          => 'annuel',
            'annee'         => 2026,
            'montant_prevu' => -1,
        ])->assertSessionHasErrors(['montant_prevu']);
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function test_user_can_view_own_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get("/budgets/{$budget->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Budgets/Show'));
    }

    public function test_show_loads_depenses_with_categorie(): void
    {
        $budget    = Budget::factory()->create(['user_id' => $this->user->id]);
        $categorie = Categorie::factory()->create();
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id, 'categorie_id' => $categorie->id,
        ]);

        $this->actingAs($this->user)->get("/budgets/{$budget->id}")
            ->assertInertia(fn ($page) => $page
                ->has('budget.depenses', 1)
                ->has('budget.depenses.0.categorie')
            );
    }

    public function test_show_budget_includes_appended_attributes(): void
    {
        $budget    = Budget::factory()->create(['user_id' => $this->user->id, 'montant_prevu' => 100000]);
        $categorie = Categorie::factory()->create();
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget->id,
            'categorie_id' => $categorie->id, 'montant' => 25000,
        ]);

        $this->actingAs($this->user)->get("/budgets/{$budget->id}")
            ->assertInertia(fn ($page) => $page
                ->where('budget.montant_depense', 25000)
                ->where('budget.solde', 75000)
            );
    }

    public function test_user_cannot_view_other_users_budget(): void
    {
        $other  = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get("/budgets/{$budget->id}")->assertForbidden();
    }

    public function test_show_returns_404_for_nonexistent_budget(): void
    {
        $this->actingAs($this->user)->get('/budgets/99999')->assertNotFound();
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_own_annuel_budget(): void
    {
        $budget = Budget::factory()->annuel()->create(['user_id' => $this->user->id, 'montant_prevu' => 100000]);

        $this->actingAs($this->user)->patch("/budgets/{$budget->id}", [
            'type'          => 'annuel',
            'annee'         => $budget->annee,
            'montant_prevu' => 200000,
        ])->assertRedirect('/budgets');

        $this->assertEquals(200000, $budget->fresh()->montant_prevu);
    }

    public function test_user_can_update_own_mensuel_budget(): void
    {
        $budget = Budget::factory()->mensuel()->create(['user_id' => $this->user->id, 'montant_prevu' => 100000]);

        $this->actingAs($this->user)->patch("/budgets/{$budget->id}", [
            'type'          => 'mensuel',
            'mois'          => $budget->mois,
            'annee'         => $budget->annee,
            'montant_prevu' => 200000,
        ])->assertRedirect('/budgets');

        $this->assertEquals(200000, $budget->fresh()->montant_prevu);
    }

    public function test_user_cannot_update_other_users_budget(): void
    {
        $other  = User::factory()->create();
        $budget = Budget::factory()->annuel()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->patch("/budgets/{$budget->id}", [
            'type'          => 'annuel',
            'annee'         => $budget->annee,
            'montant_prevu' => 999999,
        ])->assertForbidden();

        $this->assertNotEquals(999999, $budget->fresh()->montant_prevu);
    }

    public function test_update_validates_required_fields(): void
    {
        $budget = Budget::factory()->annuel()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->patch("/budgets/{$budget->id}", [])
            ->assertSessionHasErrors(['type', 'annee', 'montant_prevu']);
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_own_budget(): void
    {
        $budget = Budget::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->delete("/budgets/{$budget->id}")
            ->assertRedirect('/budgets');

        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    public function test_user_cannot_delete_other_users_budget(): void
    {
        $other  = User::factory()->create();
        $budget = Budget::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->delete("/budgets/{$budget->id}")->assertForbidden();
        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    public function test_delete_returns_404_for_nonexistent_budget(): void
    {
        $this->actingAs($this->user)->delete('/budgets/99999')->assertNotFound();
    }

    // ── Period & currency filters ──────────────────────────────────────────────

    public function test_filters_prop_is_returned_on_index(): void
    {
        $this->actingAs($this->user)->get('/budgets?annee=2025')
            ->assertInertia(fn ($page) => $page
                ->has('filters')
                ->where('filters.annee', 2025)
            );
    }

    public function test_annee_filter_excludes_other_years(): void
    {
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'annee' => 2025, 'currency_code' => 'XOF',
        ]);
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'annee' => 2024, 'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/budgets?annee=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 1));
    }

    public function test_mois_filter_shows_matching_mensuel_and_all_annuel_for_year(): void
    {
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'mensuel', 'mois' => 4, 'annee' => 2025, 'currency_code' => 'XOF',
        ]);
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'mensuel', 'mois' => 6, 'annee' => 2025, 'currency_code' => 'XOF',
        ]);
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'mois' => null, 'annee' => 2025, 'currency_code' => 'XOF',
        ]);

        // Month 4 + year 2025: April mensuel + annuel 2025 (not June mensuel)
        $this->actingAs($this->user)->get('/budgets?mois=4&annee=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 2));
    }

    public function test_currency_all_shows_budgets_across_currencies(): void
    {
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'annee' => now()->year, 'currency_code' => 'XOF',
        ]);
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'annee' => now()->year, 'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/budgets?currency=all')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 2));
    }

    public function test_default_currency_filter_excludes_other_currencies(): void
    {
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'annee' => now()->year, 'currency_code' => 'XOF',
        ]);
        Budget::factory()->create([
            'user_id' => $this->user->id, 'type' => 'annuel', 'annee' => now()->year, 'currency_code' => 'EUR',
        ]);

        // No currency param → session default (XOF in tests)
        $this->actingAs($this->user)->get('/budgets')
            ->assertInertia(fn ($page) => $page->has('budgets.data', 1));
    }
}
