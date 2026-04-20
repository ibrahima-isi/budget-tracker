<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);
    }

    // ── Access control ─────────────────────────────────────────────────────────

    public function test_guest_cannot_access_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
        $this->post('/settings')->assertRedirect('/login');
        $this->delete('/settings/logo')->assertRedirect('/login');
    }

    public function test_unverified_user_cannot_access_settings(): void
    {
        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/settings')->assertRedirect('/verify-email');
    }

    public function test_non_admin_user_cannot_access_settings(): void
    {
        $regular = User::factory()->create(['email_verified_at' => now(), 'is_admin' => false]);
        $this->actingAs($regular)->get('/settings')->assertForbidden();
    }

    // ── Index ──────────────────────────────────────────────────────────────────

    public function test_user_can_access_settings_page(): void
    {
        $this->actingAs($this->user)->get('/settings')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/Index'));
    }

    public function test_settings_page_passes_settings_and_currencies(): void
    {
        $this->actingAs($this->user)->get('/settings')
            ->assertInertia(fn ($page) => $page
                ->has('settings')
                ->has('currencies')
            );
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_user_can_update_all_settings_fields(): void
    {
        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Acme Corp',
            'business_email'   => 'contact@acme.com',
            'phone'            => '+221 77 000 00 00',
            'language'         => 'en',
            'default_currency' => 'EUR',
        ])->assertRedirect('/settings');

        $setting = Setting::instance();
        $this->assertEquals('Acme Corp', $setting->business_name);
        $this->assertEquals('contact@acme.com', $setting->business_email);
        $this->assertEquals('+221 77 000 00 00', $setting->phone);
        $this->assertEquals('en', $setting->language);
        $this->assertEquals('EUR', $setting->default_currency);
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test Corp',
            'language'         => 'fr',
            'default_currency' => 'XOF',
            // business_email, phone, logo omitted
        ])->assertRedirect('/settings')
          ->assertSessionHasNoErrors();
    }

    public function test_update_validates_required_fields(): void
    {
        $this->actingAs($this->user)->post('/settings', [])
            ->assertSessionHasErrors(['business_name', 'language', 'default_currency']);
    }

    public function test_update_validates_email_format(): void
    {
        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test',
            'business_email'   => 'not-an-email',
            'language'         => 'fr',
            'default_currency' => 'XOF',
        ])->assertSessionHasErrors(['business_email']);
    }

    public function test_update_validates_language_enum(): void
    {
        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test',
            'language'         => 'zh',  // not supported
            'default_currency' => 'XOF',
        ])->assertSessionHasErrors(['language']);
    }

    public function test_update_accepts_all_supported_languages(): void
    {
        foreach (['fr', 'en', 'es'] as $lang) {
            $this->actingAs($this->user)->post('/settings', [
                'business_name'    => 'Test',
                'language'         => $lang,
                'default_currency' => 'XOF',
            ])->assertRedirect('/settings');
        }
    }

    // ── Logo upload ────────────────────────────────────────────────────────────

    public function test_user_can_upload_logo(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('logo.png', 200, 200);

        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test Corp',
            'language'         => 'fr',
            'default_currency' => 'XOF',
            'logo'             => $file,
        ])->assertRedirect('/settings');

        $setting = Setting::instance();
        $this->assertNotNull($setting->logo_path);
        Storage::disk('local')->assertExists($setting->logo_path);
    }

    public function test_uploading_new_logo_deletes_old_one(): void
    {
        Storage::fake('local');

        $oldFile = UploadedFile::fake()->image('old.png');
        $oldPath = $oldFile->store('logos', 'local');
        Setting::instance()->update(['logo_path' => $oldPath]);

        $newFile = UploadedFile::fake()->image('new.png');
        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test Corp',
            'language'         => 'fr',
            'default_currency' => 'XOF',
            'logo'             => $newFile,
        ]);

        Storage::disk('local')->assertMissing($oldPath);
        $this->assertNotEquals($oldPath, Setting::instance()->logo_path);
    }

    public function test_update_rejects_non_image_file_as_logo(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test',
            'language'         => 'fr',
            'default_currency' => 'XOF',
            'logo'             => $file,
        ])->assertSessionHasErrors(['logo']);
    }

    public function test_update_rejects_logo_larger_than_2mb(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('big.jpg')->size(3000); // 3MB

        $this->actingAs($this->user)->post('/settings', [
            'business_name'    => 'Test',
            'language'         => 'fr',
            'default_currency' => 'XOF',
            'logo'             => $file,
        ])->assertSessionHasErrors(['logo']);
    }

    // ── Delete logo ────────────────────────────────────────────────────────────

    public function test_user_can_delete_logo(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->image('logo.png');
        $path = $file->store('logos', 'local');
        Setting::instance()->update(['logo_path' => $path]);

        $this->actingAs($this->user)->delete('/settings/logo')
            ->assertRedirect('/settings');

        $this->assertNull(Setting::instance()->logo_path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_delete_logo_when_no_logo_exists_does_not_error(): void
    {
        Setting::instance()->update(['logo_path' => null]);

        $this->actingAs($this->user)->delete('/settings/logo')
            ->assertRedirect('/settings');
    }

    public function test_delete_logo_flashes_success_message(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('logo.png')->store('logos', 'local');
        Setting::instance()->update(['logo_path' => $path]);

        $this->actingAs($this->user)->delete('/settings/logo')
            ->assertSessionHas('success');
    }
}
