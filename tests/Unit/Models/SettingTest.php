<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_instance_creates_row_when_none_exists(): void
    {
        $this->assertDatabaseCount('settings', 0);

        $setting = Setting::instance();

        $this->assertDatabaseCount('settings', 1);
        $this->assertInstanceOf(Setting::class, $setting);
    }

    public function test_instance_returns_same_row_on_repeated_calls(): void
    {
        $first  = Setting::instance();
        $second = Setting::instance();

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('settings', 1);
    }

    public function test_instance_has_default_values(): void
    {
        $setting = Setting::instance();

        $this->assertEquals('Mon Entreprise', $setting->business_name);
        $this->assertEquals('fr', $setting->language);
        $this->assertEquals('XOF', $setting->default_currency);
    }

    public function test_setting_fields_are_fillable(): void
    {
        $setting = Setting::instance();
        $setting->update([
            'business_name'    => 'Acme Corp',
            'business_email'   => 'contact@acme.com',
            'phone'            => '+221 77 000 00 00',
            'language'         => 'en',
            'default_currency' => 'EUR',
        ]);

        $this->assertEquals('Acme Corp', $setting->fresh()->business_name);
        $this->assertEquals('contact@acme.com', $setting->fresh()->business_email);
    }
}
