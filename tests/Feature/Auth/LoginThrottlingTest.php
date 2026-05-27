<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_locked_for_five_minutes_after_five_failed_attempts(): void
    {
        config([
            'security.login.max_attempts' => 5,
            'security.login.lockout_seconds' => 300,
        ]);

        $user = User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => 'password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->travel(240)->seconds();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_receives_lockout_countdown_timestamp(): void
    {
        config([
            'security.login.max_attempts' => 1,
            'security.login.lockout_seconds' => 300,
        ]);

        $user = User::factory()->create([
            'email' => 'countdown@example.com',
            'password' => 'password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('lockoutUntil', fn ($value) => is_int($value) && $value > now()->timestamp)
            );
    }
}
