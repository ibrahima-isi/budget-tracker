<?php

namespace Tests\Feature;

use App\Models\Revenu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenuTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_revenus(): void
    {
        $this->get('/revenus')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_revenus(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/revenus')->assertRedirect('/verify-email');
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_own_revenus(): void
    {
        Revenu::factory()->count(3)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/revenus')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Revenus/Index')
                ->has('revenus.data', 3)
            );
    }

    public function test_index_does_not_show_other_users_revenus(): void
    {
        $other = User::factory()->create();
        Revenu::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/revenus')
            ->assertInertia(fn ($page) => $page->has('revenus.data', 0));
    }

    public function test_index_paginates_at_20_per_page(): void
    {
        Revenu::factory()->count(25)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/revenus')
            ->assertInertia(fn ($page) => $page
                ->has('revenus.data', 20)
                ->where('revenus.total', 25)
            );
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_revenu(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source'      => 'Salaire',
            'montant'     => 500000,
            'date_revenu' => '2026-04-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('revenus', [
            'user_id' => $this->user->id,
            'source'  => 'Salaire',
        ]);
    }

    public function test_mois_and_annee_are_derived_from_date_revenu_on_store(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source'      => 'Freelance',
            'montant'     => 100000,
            'date_revenu' => '2025-07-15',
        ]);

        $this->assertDatabaseHas('revenus', [
            'user_id' => $this->user->id,
            'mois'    => 7,
            'annee'   => 2025,
        ]);
    }

    public function test_mois_and_annee_are_derived_correctly_for_december(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source'      => 'Loyer',
            'montant'     => 50000,
            'date_revenu' => '2025-12-31',
        ]);

        $this->assertDatabaseHas('revenus', [
            'user_id' => $this->user->id,
            'mois'    => 12,
            'annee'   => 2025,
        ]);
    }

    public function test_user_id_cannot_be_forged_on_store(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->post('/revenus', [
            'user_id'     => $other->id,
            'source'      => 'Salaire',
            'montant'     => 100000,
            'date_revenu' => '2026-04-01',
        ]);

        $revenu = Revenu::where('user_id', $this->user->id)->first();
        $this->assertNotNull($revenu);
    }

    public function test_note_is_optional_on_store(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source'      => 'Salaire',
            'montant'     => 100000,
            'date_revenu' => '2026-04-01',
        ])->assertRedirect();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/revenus', [])
            ->assertSessionHasErrors(['source', 'montant', 'date_revenu']);
    }

    public function test_montant_must_be_non_negative(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source'      => 'Salaire',
            'montant'     => -1,
            'date_revenu' => '2026-04-01',
        ])->assertSessionHasErrors(['montant']);
    }

    public function test_date_revenu_must_be_a_valid_date(): void
    {
        $this->actingAs($this->user)->post('/revenus', [
            'source'      => 'Salaire',
            'montant'     => 100000,
            'date_revenu' => 'not-a-date',
        ])->assertSessionHasErrors(['date_revenu']);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_own_revenu(): void
    {
        $revenu = Revenu::factory()->create(['user_id' => $this->user->id, 'montant' => 100000]);

        $this->actingAs($this->user)->patch("/revenus/{$revenu->id}", [
            'source'      => $revenu->source,
            'montant'     => 200000,
            'date_revenu' => $revenu->date_revenu->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertEquals(200000, $revenu->fresh()->montant);
    }

    public function test_update_recalculates_mois_and_annee_from_new_date(): void
    {
        $revenu = Revenu::factory()->create([
            'user_id'     => $this->user->id,
            'date_revenu' => '2025-01-15',
            'mois'        => 1,
            'annee'       => 2025,
        ]);

        $this->actingAs($this->user)->patch("/revenus/{$revenu->id}", [
            'source'      => $revenu->source,
            'montant'     => $revenu->montant,
            'date_revenu' => '2026-09-20',
        ]);

        $fresh = $revenu->fresh();
        $this->assertEquals(9, $fresh->mois);
        $this->assertEquals(2026, $fresh->annee);
    }

    public function test_user_cannot_update_other_users_revenu(): void
    {
        $other  = User::factory()->create();
        $revenu = Revenu::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->patch("/revenus/{$revenu->id}", [
            'source'      => 'Hacked',
            'montant'     => 1,
            'date_revenu' => '2026-01-01',
        ])->assertForbidden();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_own_revenu(): void
    {
        $revenu = Revenu::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->delete("/revenus/{$revenu->id}")->assertRedirect();
        $this->assertDatabaseMissing('revenus', ['id' => $revenu->id]);
    }

    public function test_user_cannot_delete_other_users_revenu(): void
    {
        $other  = User::factory()->create();
        $revenu = Revenu::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->delete("/revenus/{$revenu->id}")->assertForbidden();
        $this->assertDatabaseHas('revenus', ['id' => $revenu->id]);
    }

    public function test_delete_returns_404_for_nonexistent_revenu(): void
    {
        $this->actingAs($this->user)->delete('/revenus/99999')->assertNotFound();
    }

    // ── Period & currency filters ──────────────────────────────────────────────

    public function test_filters_prop_is_returned_on_index(): void
    {
        $this->actingAs($this->user)->get('/revenus?mois=4&annee=2025')
            ->assertInertia(fn ($page) => $page
                ->has('filters')
                ->where('filters.mois', 4)
                ->where('filters.annee', 2025)
            );
    }

    public function test_mois_filter_returns_only_matching_month(): void
    {
        // April 2025
        Revenu::factory()->create([
            'user_id'      => $this->user->id,
            'mois'         => 4,
            'annee'        => 2025,
            'date_revenu'  => '2025-04-01',
            'currency_code' => 'XOF',
        ]);
        // June 2025 — must be excluded
        Revenu::factory()->create([
            'user_id'      => $this->user->id,
            'mois'         => 6,
            'annee'        => 2025,
            'date_revenu'  => '2025-06-01',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/revenus?mois=4&annee=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('revenus.data', 1));
    }

    public function test_annee_filter_returns_only_matching_year(): void
    {
        Revenu::factory()->create([
            'user_id'      => $this->user->id,
            'mois'         => 4,
            'annee'        => 2025,
            'date_revenu'  => '2025-04-01',
            'currency_code' => 'XOF',
        ]);
        // 2024 — must be excluded
        Revenu::factory()->create([
            'user_id'      => $this->user->id,
            'mois'         => 4,
            'annee'        => 2024,
            'date_revenu'  => '2024-04-01',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/revenus?annee=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('revenus.data', 1));
    }

    public function test_currency_all_shows_all_currencies(): void
    {
        Revenu::factory()->create([
            'user_id' => $this->user->id, 'mois' => now()->month, 'annee' => now()->year,
            'date_revenu' => now()->format('Y-m-01'), 'currency_code' => 'XOF',
        ]);
        Revenu::factory()->create([
            'user_id' => $this->user->id, 'mois' => now()->month, 'annee' => now()->year,
            'date_revenu' => now()->format('Y-m-01'), 'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/revenus?currency=all')
            ->assertInertia(fn ($page) => $page->has('revenus.data', 2));
    }

    public function test_default_currency_filter_excludes_other_currencies(): void
    {
        Revenu::factory()->create([
            'user_id' => $this->user->id, 'mois' => now()->month, 'annee' => now()->year,
            'date_revenu' => now()->format('Y-m-01'), 'currency_code' => 'XOF',
        ]);
        Revenu::factory()->create([
            'user_id' => $this->user->id, 'mois' => now()->month, 'annee' => now()->year,
            'date_revenu' => now()->format('Y-m-01'), 'currency_code' => 'EUR',
        ]);

        // No currency param → session default (XOF in tests)
        $this->actingAs($this->user)->get('/revenus')
            ->assertInertia(fn ($page) => $page->has('revenus.data', 1));
    }
}
