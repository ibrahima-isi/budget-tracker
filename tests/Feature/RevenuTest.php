<?php

namespace Tests\Feature;

use App\Models\Revenue;
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

    public function test_guest_cannot_access_revenues(): void
    {
        $this->get('/revenues')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_revenues(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/revenues')->assertRedirect('/verify-email');
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_list_own_revenues(): void
    {
        Revenue::factory()->currentPeriod()->count(3)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/revenues')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Revenues/Index')
                ->has('revenues.data', 3)
            );
    }

    public function test_index_does_not_show_other_users_revenues(): void
    {
        $other = User::factory()->create();
        Revenue::factory()->currentPeriod()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->get('/revenues')
            ->assertInertia(fn ($page) => $page->has('revenues.data', 0));
    }

    public function test_index_paginates_at_20_per_page(): void
    {
        Revenue::factory()->count(25)->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->get('/revenues?month=all&year=all')
            ->assertInertia(fn ($page) => $page
                ->has('revenues.data', 20)
                ->where('revenues.total', 25)
            );
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_revenue(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Salaire',
            'amount' => 500000,
            'revenue_date' => '2026-04-01',
        ])->assertRedirect();

        $this->assertDatabaseHas('revenues', [
            'user_id' => $this->user->id,
            'source' => 'Salaire',
        ]);
    }

    public function test_month_and_year_are_derived_from_revenue_date_on_store(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Freelance',
            'amount' => 100000,
            'revenue_date' => '2025-07-15',
        ]);

        $this->assertDatabaseHas('revenues', [
            'user_id' => $this->user->id,
            'month' => 7,
            'year' => 2025,
        ]);
    }

    public function test_month_and_year_are_derived_correctly_for_december(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Loyer',
            'amount' => 50000,
            'revenue_date' => '2025-12-31',
        ]);

        $this->assertDatabaseHas('revenues', [
            'user_id' => $this->user->id,
            'month' => 12,
            'year' => 2025,
        ]);
    }

    public function test_user_id_cannot_be_forged_on_store(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->user)->post('/revenues', [
            'user_id' => $other->id,
            'source' => 'Salaire',
            'amount' => 100000,
            'revenue_date' => '2026-04-01',
        ]);

        $revenue = Revenue::where('user_id', $this->user->id)->first();
        $this->assertNotNull($revenue);
    }

    public function test_note_is_optional_on_store(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Salaire',
            'amount' => 100000,
            'revenue_date' => '2026-04-01',
        ])->assertRedirect();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/revenues', [])
            ->assertSessionHasErrors(['source', 'amount', 'revenue_date']);
    }

    public function test_amount_must_be_non_negative(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Salaire',
            'amount' => -1,
            'revenue_date' => '2026-04-01',
        ])->assertSessionHasErrors(['amount']);
    }

    public function test_revenue_date_must_be_a_valid_date(): void
    {
        $this->actingAs($this->user)->post('/revenues', [
            'source' => 'Salaire',
            'amount' => 100000,
            'revenue_date' => 'not-a-date',
        ])->assertSessionHasErrors(['revenue_date']);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_own_revenue(): void
    {
        $revenue = Revenue::factory()->create(['user_id' => $this->user->id, 'amount' => 100000]);

        $this->actingAs($this->user)->patch("/revenues/{$revenue->id}", [
            'source' => $revenue->source,
            'amount' => 200000,
            'revenue_date' => $revenue->revenue_date->format('Y-m-d'),
        ])->assertRedirect();

        $this->assertEquals(200000, $revenue->fresh()->amount);
    }

    public function test_update_recalculates_month_and_year_from_new_date(): void
    {
        $revenue = Revenue::factory()->create([
            'user_id' => $this->user->id,
            'revenue_date' => '2025-01-15',
            'month' => 1,
            'year' => 2025,
        ]);

        $this->actingAs($this->user)->patch("/revenues/{$revenue->id}", [
            'source' => $revenue->source,
            'amount' => $revenue->amount,
            'revenue_date' => '2026-09-20',
        ]);

        $fresh = $revenue->fresh();
        $this->assertEquals(9, $fresh->month);
        $this->assertEquals(2026, $fresh->year);
    }

    public function test_user_cannot_update_other_users_revenue(): void
    {
        $other = User::factory()->create();
        $revenue = Revenue::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->patch("/revenues/{$revenue->id}", [
            'source' => 'Hacked',
            'amount' => 1,
            'revenue_date' => '2026-01-01',
        ])->assertForbidden();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_own_revenue(): void
    {
        $revenue = Revenue::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)->delete("/revenues/{$revenue->id}")->assertRedirect();
        $this->assertDatabaseMissing('revenues', ['id' => $revenue->id]);
    }

    public function test_user_cannot_delete_other_users_revenue(): void
    {
        $other = User::factory()->create();
        $revenue = Revenue::factory()->create(['user_id' => $other->id]);

        $this->actingAs($this->user)->delete("/revenues/{$revenue->id}")->assertForbidden();
        $this->assertDatabaseHas('revenues', ['id' => $revenue->id]);
    }

    public function test_delete_returns_404_for_nonexistent_revenue(): void
    {
        $this->actingAs($this->user)->delete('/revenues/99999')->assertNotFound();
    }

    // ── Period & currency filters ──────────────────────────────────────────────

    public function test_filters_prop_is_returned_on_index(): void
    {
        $this->actingAs($this->user)->get('/revenues?month=4&year=2025')
            ->assertInertia(fn ($page) => $page
                ->has('filters')
                ->where('filters.month', 4)
                ->where('filters.year', 2025)
            );
    }

    public function test_month_filter_returns_only_matching_month(): void
    {
        // April 2025
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'month' => 4,
            'year' => 2025,
            'revenue_date' => '2025-04-01',
            'currency_code' => 'XOF',
        ]);
        // June 2025 — must be excluded
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'month' => 6,
            'year' => 2025,
            'revenue_date' => '2025-06-01',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/revenues?month=4&year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('revenues.data', 1));
    }

    public function test_year_filter_returns_only_matching_year(): void
    {
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'month' => 4,
            'year' => 2025,
            'revenue_date' => '2025-04-01',
            'currency_code' => 'XOF',
        ]);
        // 2024 — must be excluded
        Revenue::factory()->create([
            'user_id' => $this->user->id,
            'month' => 4,
            'year' => 2024,
            'revenue_date' => '2024-04-01',
            'currency_code' => 'XOF',
        ]);

        $this->actingAs($this->user)->get('/revenues?year=2025&currency=XOF')
            ->assertInertia(fn ($page) => $page->has('revenues.data', 1));
    }

    public function test_currency_all_shows_all_currencies(): void
    {
        Revenue::factory()->create([
            'user_id' => $this->user->id, 'month' => now()->month, 'year' => now()->year,
            'revenue_date' => now()->format('Y-m-01'), 'currency_code' => 'XOF',
        ]);
        Revenue::factory()->create([
            'user_id' => $this->user->id, 'month' => now()->month, 'year' => now()->year,
            'revenue_date' => now()->format('Y-m-01'), 'currency_code' => 'EUR',
        ]);

        $this->actingAs($this->user)->get('/revenues?currency=all')
            ->assertInertia(fn ($page) => $page->has('revenues.data', 2));
    }

    public function test_default_currency_filter_excludes_other_currencies(): void
    {
        Revenue::factory()->create([
            'user_id' => $this->user->id, 'month' => now()->month, 'year' => now()->year,
            'revenue_date' => now()->format('Y-m-01'), 'currency_code' => 'XOF',
        ]);
        Revenue::factory()->create([
            'user_id' => $this->user->id, 'month' => now()->month, 'year' => now()->year,
            'revenue_date' => now()->format('Y-m-01'), 'currency_code' => 'EUR',
        ]);

        // No currency param → session default (XOF in tests)
        $this->actingAs($this->user)->get('/revenues')
            ->assertInertia(fn ($page) => $page->has('revenues.data', 1));
    }
}
