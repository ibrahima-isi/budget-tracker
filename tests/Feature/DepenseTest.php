<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepenseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Budget $budget;
    private Categorie $categorie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user      = User::factory()->create(['email_verified_at' => now()]);
        $this->budget    = Budget::factory()->create(['user_id' => $this->user->id]);
        $this->categorie = Categorie::factory()->create();
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_depenses(): void
    {
        $this->get('/depenses')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_depenses(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/depenses')->assertRedirect('/verify-email');
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_own_depenses(): void
    {
        Depense::factory()->count(3)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->user)->get('/depenses')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Depenses/Index')
                ->has('depenses.data', 3)
            );
    }

    public function test_index_does_not_show_other_users_depenses(): void
    {
        $other       = User::factory()->create();
        $otherBudget = Budget::factory()->create(['user_id' => $other->id]);
        Depense::factory()->count(2)->create([
            'user_id' => $other->id, 'budget_id' => $otherBudget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->user)->get('/depenses')
            ->assertInertia(fn ($page) => $page->has('depenses.data', 0));
    }

    public function test_index_paginates_at_20_per_page(): void
    {
        Depense::factory()->count(25)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->user)->get('/depenses')
            ->assertInertia(fn ($page) => $page
                ->has('depenses.data', 20)
                ->where('depenses.total', 25)
            );
    }

    public function test_index_passes_budgets_and_categories_to_view(): void
    {
        $this->actingAs($this->user)->get('/depenses')
            ->assertInertia(fn ($page) => $page
                ->has('budgets')
                ->has('categories')
                ->has('filters')
            );
    }

    // ── Filters ────────────────────────────────────────────────────────────────

    public function test_index_filters_by_budget_id(): void
    {
        $otherBudget = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat         = Categorie::factory()->create();

        Depense::factory()->count(2)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $cat->id,
        ]);
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $otherBudget->id, 'categorie_id' => $cat->id,
        ]);

        $this->actingAs($this->user)->get("/depenses?budget_id={$this->budget->id}")
            ->assertInertia(fn ($page) => $page->has('depenses.data', 2));
    }

    public function test_index_filters_by_categorie_id(): void
    {
        $otherCat = Categorie::factory()->create();

        Depense::factory()->count(3)->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $this->categorie->id,
        ]);
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $otherCat->id,
        ]);

        $this->actingAs($this->user)->get("/depenses?categorie_id={$this->categorie->id}")
            ->assertInertia(fn ($page) => $page->has('depenses.data', 3));
    }

    public function test_index_filters_by_budget_and_categorie_combined(): void
    {
        $budget2 = Budget::factory()->create(['user_id' => $this->user->id]);
        $cat2    = Categorie::factory()->create();

        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $this->categorie->id,
        ]);
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $budget2->id, 'categorie_id' => $this->categorie->id,
        ]);
        Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $cat2->id,
        ]);

        $this->actingAs($this->user)
            ->get("/depenses?budget_id={$this->budget->id}&categorie_id={$this->categorie->id}")
            ->assertInertia(fn ($page) => $page->has('depenses.data', 1));
    }

    public function test_filters_are_passed_back_to_view(): void
    {
        $this->actingAs($this->user)
            ->get("/depenses?budget_id={$this->budget->id}")
            ->assertInertia(fn ($page) => $page
                ->where('filters.budget_id', (string) $this->budget->id)
            );
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_depense(): void
    {
        $this->actingAs($this->user)->post('/depenses', [
            'budget_id'    => $this->budget->id,
            'categorie_id' => $this->categorie->id,
            'libelle'      => 'Courses alimentaires',
            'montant'      => 15000,
            'date_depense' => '2026-04-10',
        ])->assertRedirect();

        $this->assertDatabaseHas('depenses', [
            'user_id' => $this->user->id,
            'libelle' => 'Courses alimentaires',
        ]);
    }

    public function test_note_is_optional_on_store(): void
    {
        $this->actingAs($this->user)->post('/depenses', [
            'budget_id'    => $this->budget->id,
            'categorie_id' => $this->categorie->id,
            'libelle'      => 'Test',
            'montant'      => 1000,
            'date_depense' => '2026-04-01',
        ])->assertRedirect();
    }

    public function test_user_id_cannot_be_forged_on_store(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->post('/depenses', [
            'user_id'      => $other->id,
            'budget_id'    => $this->budget->id,
            'categorie_id' => $this->categorie->id,
            'libelle'      => 'Forge test',
            'montant'      => 1000,
            'date_depense' => '2026-04-01',
        ]);

        $depense = Depense::where('libelle', 'Forge test')->first();
        $this->assertEquals($this->user->id, $depense->user_id);
    }

    public function test_store_rejects_nonexistent_budget_id(): void
    {
        $this->actingAs($this->user)->post('/depenses', [
            'budget_id'    => 99999,
            'categorie_id' => $this->categorie->id,
            'libelle'      => 'Test',
            'montant'      => 1000,
            'date_depense' => '2026-04-01',
        ])->assertSessionHasErrors(['budget_id']);
    }

    public function test_store_rejects_nonexistent_categorie_id(): void
    {
        $this->actingAs($this->user)->post('/depenses', [
            'budget_id'    => $this->budget->id,
            'categorie_id' => 99999,
            'libelle'      => 'Test',
            'montant'      => 1000,
            'date_depense' => '2026-04-01',
        ])->assertSessionHasErrors(['categorie_id']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/depenses', [])
            ->assertSessionHasErrors(['budget_id', 'libelle', 'montant', 'date_depense']); // categorie_id is nullable
    }

    public function test_montant_must_be_non_negative(): void
    {
        $this->actingAs($this->user)->post('/depenses', [
            'budget_id'    => $this->budget->id,
            'categorie_id' => $this->categorie->id,
            'libelle'      => 'Test',
            'montant'      => -100,
            'date_depense' => '2026-04-01',
        ])->assertSessionHasErrors(['montant']);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_own_depense(): void
    {
        $depense = Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id,
            'categorie_id' => $this->categorie->id, 'montant' => 10000,
        ]);

        $this->actingAs($this->user)->patch("/depenses/{$depense->id}", [
            'budget_id'    => $this->budget->id,
            'categorie_id' => $this->categorie->id,
            'libelle'      => $depense->libelle,
            'montant'      => 20000,
            'date_depense' => $depense->date_depense->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertEquals(20000, $depense->fresh()->montant);
    }

    public function test_user_cannot_update_other_users_depense(): void
    {
        $other   = User::factory()->create();
        $budget  = Budget::factory()->create(['user_id' => $other->id]);
        $depense = Depense::factory()->create([
            'user_id' => $other->id, 'budget_id' => $budget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->user)->patch("/depenses/{$depense->id}", [
            'budget_id' => $budget->id, 'categorie_id' => $this->categorie->id,
            'libelle' => 'Hacked', 'montant' => 1, 'date_depense' => '2026-01-01',
        ])->assertForbidden();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_own_depense(): void
    {
        $depense = Depense::factory()->create([
            'user_id' => $this->user->id, 'budget_id' => $this->budget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->user)->delete("/depenses/{$depense->id}")->assertRedirect();
        $this->assertDatabaseMissing('depenses', ['id' => $depense->id]);
    }

    public function test_user_cannot_delete_other_users_depense(): void
    {
        $other   = User::factory()->create();
        $budget  = Budget::factory()->create(['user_id' => $other->id]);
        $depense = Depense::factory()->create([
            'user_id' => $other->id, 'budget_id' => $budget->id, 'categorie_id' => $this->categorie->id,
        ]);

        $this->actingAs($this->user)->delete("/depenses/{$depense->id}")->assertForbidden();
        $this->assertDatabaseHas('depenses', ['id' => $depense->id]);
    }

    public function test_delete_returns_404_for_nonexistent_depense(): void
    {
        $this->actingAs($this->user)->delete('/depenses/99999')->assertNotFound();
    }

    // ── Period & currency filters ──────────────────────────────────────────────

    public function test_filters_prop_contains_period_and_currency(): void
    {
        $this->actingAs($this->user)->get('/depenses?mois=4&annee=2025')
            ->assertInertia(fn ($page) => $page
                ->has('filters')
                ->where('filters.mois', 4)
                ->where('filters.annee', 2025)
            );
    }

    public function test_mois_filter_returns_only_matching_month(): void
    {
        // April 2025
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'date_depense'  => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        // June 2025 — must be excluded
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'date_depense'  => '2025-06-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/depenses?mois=4&annee=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('depenses.data', 1));
    }

    public function test_annee_filter_returns_only_matching_year(): void
    {
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'date_depense'  => '2025-04-10',
            'currency_code' => 'XOF',
        ]);
        // 2024 — must be excluded
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'date_depense'  => '2024-04-10',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/depenses?annee=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('depenses.data', 1));
    }

    public function test_currency_all_shows_all_currencies(): void
    {
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'currency_code' => 'XOF',
        ]);
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/depenses?currency=all')
            ->assertInertia(fn ($page) => $page->has('depenses.data', 2));
    }

    public function test_default_currency_filter_excludes_other_currencies(): void
    {
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'currency_code' => 'XOF',
        ]);
        Depense::factory()->create([
            'user_id'       => $this->user->id,
            'budget_id'     => $this->budget->id,
            'categorie_id'  => $this->categorie->id,
            'currency_code' => 'EUR',
        ]);

        // No currency param → session default (XOF in tests)
        $this->actingAs($this->user)->get('/depenses')
            ->assertInertia(fn ($page) => $page->has('depenses.data', 1));
    }
}
