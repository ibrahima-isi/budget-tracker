<?php

namespace Tests\Unit\Models;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_default_and_is_active_cast_to_boolean(): void
    {
        $currency = Currency::create([
            'code'       => 'XOF',
            'name'       => 'Franc CFA',
            'symbol'     => 'FCFA',
            'is_default' => true,
            'is_active'  => true,
        ]);

        $this->assertIsBool($currency->is_default);
        $this->assertIsBool($currency->is_active);
        $this->assertTrue($currency->is_default);
        $this->assertTrue($currency->is_active);
    }

    public function test_currency_fields_are_fillable(): void
    {
        $currency = Currency::create([
            'code'       => 'EUR',
            'name'       => 'Euro',
            'symbol'     => '€',
            'is_default' => false,
            'is_active'  => true,
        ]);

        $this->assertDatabaseHas('currencies', ['code' => 'EUR']);
    }

    public function test_currency_can_be_updated(): void
    {
        $currency = Currency::create([
            'code'       => 'USD',
            'name'       => 'US Dollar',
            'symbol'     => '$',
            'is_default' => false,
            'is_active'  => true,
        ]);

        $currency->update(['is_active' => false]);

        $this->assertFalse($currency->fresh()->is_active);
    }
}
