<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that the active currencies list is shared via Inertia shared props
 * so the frontend currency switcher has the data it needs.
 */
class CurrencySharedPropTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Shared prop presence ───────────────────────────────────────────────────

    public function test_currencies_prop_is_shared_on_authenticated_pages(): void
    {
        Currency::factory()->create(['code' => 'XOF', 'is_active' => true]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('currencies'));
    }

    public function test_currencies_prop_contains_code_name_symbol(): void
    {
        Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->has('currencies.0.code')
                ->has('currencies.0.name')
                ->has('currencies.0.symbol')
            );
    }

    public function test_only_active_currencies_are_shared(): void
    {
        Currency::factory()->create(['code' => 'USD', 'is_active' => true]);
        Currency::factory()->create(['code' => 'GBP', 'is_active' => false]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('currencies', 1));
    }

    public function test_inactive_currency_is_excluded(): void
    {
        Currency::factory()->inactive()->create(['code' => 'XAF']);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('currencies', fn ($currencies) =>
                    collect($currencies)->every(fn ($c) => $c['code'] !== 'XAF')
                )
            );
    }

    public function test_currencies_are_ordered_default_first_then_alphabetically(): void
    {
        Currency::factory()->create(['code' => 'USD', 'is_default' => false, 'is_active' => true]);
        Currency::factory()->create(['code' => 'EUR', 'is_default' => false, 'is_active' => true]);
        Currency::factory()->create(['code' => 'XOF', 'is_default' => true,  'is_active' => true]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('currencies.0.code', 'XOF')  // default first
            );
    }

    public function test_currencies_prop_is_empty_array_when_no_currencies(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page->has('currencies', 0));
    }

    public function test_currencies_shared_on_budgets_page(): void
    {
        Currency::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->get('/budgets')
            ->assertInertia(fn ($page) => $page->has('currencies'));
    }

    public function test_currencies_shared_on_expenses_page(): void
    {
        Currency::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->get('/expenses')
            ->assertInertia(fn ($page) => $page->has('currencies'));
    }

    public function test_currencies_shared_on_revenues_page(): void
    {
        Currency::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->get('/revenues')
            ->assertInertia(fn ($page) => $page->has('currencies'));
    }

    public function test_currencies_shared_on_categories_page(): void
    {
        Currency::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->get('/categories')
            ->assertInertia(fn ($page) => $page->has('currencies'));
    }

    // ── Prop does not include timestamps ──────────────────────────────────────

    public function test_currencies_prop_does_not_include_timestamps(): void
    {
        Currency::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('currencies', fn ($list) =>
                    collect($list)->every(fn ($c) =>
                        ! array_key_exists('created_at', $c) && ! array_key_exists('updated_at', $c)
                    )
                )
            );
    }
}
