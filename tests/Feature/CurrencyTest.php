<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function test_user_can_create_currency(): void
    {
        $this->actingAs($this->user)->post('/settings/currencies', [
            'code'   => 'EUR',
            'name'   => 'Euro',
            'symbol' => '€',
        ])->assertRedirect('/settings');

        $this->assertDatabaseHas('currencies', ['code' => 'EUR', 'is_default' => false, 'is_active' => true]);
    }

    public function test_currency_code_is_uppercased_on_store(): void
    {
        $this->actingAs($this->user)->post('/settings/currencies', [
            'code'   => 'eur',
            'name'   => 'Euro',
            'symbol' => '€',
        ]);

        $this->assertDatabaseHas('currencies', ['code' => 'EUR']);
        $this->assertDatabaseMissing('currencies', ['code' => 'eur']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/settings/currencies', [])
            ->assertSessionHasErrors(['code', 'name', 'symbol']);
    }

    public function test_store_rejects_duplicate_code(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->post('/settings/currencies', [
            'code'   => 'USD',
            'name'   => 'Another Dollar',
            'symbol' => '$',
        ])->assertSessionHasErrors(['code']);
    }

    public function test_store_rejects_duplicate_code_case_insensitive_via_uppercase(): void
    {
        Currency::create(['code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_default' => false, 'is_active' => true]);

        // Submitting 'usd' will be uppercased to 'USD' → unique violation
        $this->actingAs($this->user)->post('/settings/currencies', [
            'code'   => 'usd',
            'name'   => 'Another Dollar',
            'symbol' => '$',
        ])->assertSessionHasErrors(['code']);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_currency(): void
    {
        $currency = Currency::create(['code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$currency->id}", [
            'code'   => 'USD',
            'name'   => 'US Dollar',
            'symbol' => '$',
        ])->assertRedirect('/settings');

        $this->assertEquals('US Dollar', $currency->fresh()->name);
    }

    public function test_update_allows_same_code_for_same_currency(): void
    {
        $currency = Currency::create(['code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$currency->id}", [
            'code'   => 'USD',
            'name'   => 'Updated Name',
            'symbol' => '$',
        ])->assertRedirect('/settings');
    }

    public function test_update_rejects_code_already_used_by_another_currency(): void
    {
        Currency::create(['code' => 'EUR', 'name' => 'Euro',   'symbol' => '€', 'is_default' => false, 'is_active' => true]);
        $usd = Currency::create(['code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$usd->id}", [
            'code'   => 'EUR',
            'name'   => 'Euro attempt',
            'symbol' => '€',
        ])->assertSessionHasErrors(['code']);
    }

    public function test_currency_code_is_uppercased_on_update(): void
    {
        $currency = Currency::create(['code' => 'USD', 'name' => 'Dollar', 'symbol' => '$', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$currency->id}", [
            'code'   => 'usd',
            'name'   => 'US Dollar',
            'symbol' => '$',
        ]);

        $this->assertEquals('USD', $currency->fresh()->code);
    }

    // ── Set Default ────────────────────────────────────────────────────────────

    public function test_user_can_set_default_currency(): void
    {
        $c1 = Currency::create(['code' => 'XOF', 'name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true,  'is_active' => true]);
        $c2 = Currency::create(['code' => 'EUR', 'name' => 'Euro',      'symbol' => '€',    'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$c2->id}/default")
            ->assertRedirect('/settings');

        $this->assertFalse($c1->fresh()->is_default);
        $this->assertTrue($c2->fresh()->is_default);
    }

    public function test_set_default_clears_previous_default(): void
    {
        $currencies = collect();
        for ($i = 0; $i < 3; $i++) {
            $currencies->push(Currency::create([
                'code' => "C0{$i}", 'name' => "Currency $i", 'symbol' => "S$i",
                'is_default' => $i === 0, 'is_active' => true,
            ]));
        }

        $this->actingAs($this->user)->patch("/settings/currencies/{$currencies[2]->id}/default");

        $this->assertEquals(1, Currency::where('is_default', true)->count());
        $this->assertTrue($currencies[2]->fresh()->is_default);
    }

    // ── Toggle ─────────────────────────────────────────────────────────────────

    public function test_user_can_toggle_currency_to_inactive(): void
    {
        $currency = Currency::create(['code' => 'GBP', 'name' => 'Pound', 'symbol' => '£', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$currency->id}/toggle")
            ->assertRedirect('/settings');

        $this->assertFalse($currency->fresh()->is_active);
    }

    public function test_user_can_toggle_currency_back_to_active(): void
    {
        $currency = Currency::create(['code' => 'GBP', 'name' => 'Pound', 'symbol' => '£', 'is_default' => false, 'is_active' => false]);

        $this->actingAs($this->user)->patch("/settings/currencies/{$currency->id}/toggle");

        $this->assertTrue($currency->fresh()->is_active);
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_user_can_delete_non_default_currency(): void
    {
        $currency = Currency::create(['code' => 'CAD', 'name' => 'Dollar Canadien', 'symbol' => 'CA$', 'is_default' => false, 'is_active' => true]);

        $this->actingAs($this->user)->delete("/settings/currencies/{$currency->id}")
            ->assertRedirect('/settings');

        $this->assertDatabaseMissing('currencies', ['id' => $currency->id]);
    }

    public function test_user_cannot_delete_default_currency(): void
    {
        $currency = Currency::create(['code' => 'XOF', 'name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user)->delete("/settings/currencies/{$currency->id}")
            ->assertRedirect('/settings');

        $this->assertDatabaseHas('currencies', ['id' => $currency->id]);
    }

    public function test_delete_default_currency_flashes_error_message(): void
    {
        $currency = Currency::create(['code' => 'XOF', 'name' => 'Franc CFA', 'symbol' => 'FCFA', 'is_default' => true, 'is_active' => true]);

        $this->actingAs($this->user)->delete("/settings/currencies/{$currency->id}")
            ->assertRedirect('/settings')
            ->assertSessionHas('error');
    }
}
